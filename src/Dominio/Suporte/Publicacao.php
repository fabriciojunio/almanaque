<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Repositorio\PublicacaoRepositorio;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A subida de uma versão nova num portal, com a conferência que vem depois.
 *
 * Publicar é a parte fácil. O que dá trabalho é responder, meia hora depois,
 * se o ambiente do cliente ficou de pé: a página abre, a busca responde, o
 * pagamento processa. Por isso a publicação só termina depois das
 * verificações, e uma verificação reprovada devolve o portal para a versão
 * anterior.
 */
#[ORM\Entity(repositoryClass: PublicacaoRepositorio::class)]
#[ORM\Table(name: 'publicacoes')]
#[ORM\Index(name: 'publicacao_portal', columns: ['portal_id', 'iniciada_em'])]
class Publicacao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portal::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 20)]
    private string $versaoAnterior;

    #[ORM\Column(length: 20)]
    private string $versaoNova;

    #[ORM\Column(length: 120)]
    private string $publicadaPor;

    #[ORM\Column(length: 20, enumType: SituacaoPublicacao::class)]
    private SituacaoPublicacao $situacao = SituacaoPublicacao::EM_ANDAMENTO;

    /**
     * Resultado de cada verificação, no formato nome => aprovada.
     *
     * @var array<string, bool>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $verificacoes = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacao = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $iniciadaEm;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $encerradaEm = null;

    public function __construct(Portal $portal, string $versaoNova, string $publicadaPor)
    {
        if (version_compare($versaoNova, $portal->versao(), '<=')) {
            throw new \DomainException(sprintf(
                'O portal já está na versão %s; publicar %s seria voltar no tempo.',
                $portal->versao(),
                $versaoNova,
            ));
        }

        $this->portal = $portal;
        $this->versaoAnterior = $portal->versao();
        $this->versaoNova = $versaoNova;
        $this->publicadaPor = $publicadaPor;
        $this->iniciadaEm = new \DateTimeImmutable();

        $portal->publicarVersao($versaoNova);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function portal(): Portal
    {
        return $this->portal;
    }

    public function versaoAnterior(): string
    {
        return $this->versaoAnterior;
    }

    public function versaoNova(): string
    {
        return $this->versaoNova;
    }

    public function publicadaPor(): string
    {
        return $this->publicadaPor;
    }

    public function situacao(): SituacaoPublicacao
    {
        return $this->situacao;
    }

    /** @return array<string, bool> */
    public function verificacoes(): array
    {
        return $this->verificacoes;
    }

    public function observacao(): ?string
    {
        return $this->observacao;
    }

    public function iniciadaEm(): \DateTimeImmutable
    {
        return $this->iniciadaEm;
    }

    public function encerradaEm(): ?\DateTimeImmutable
    {
        return $this->encerradaEm;
    }

    public function registrarVerificacao(string $nome, bool $aprovada): void
    {
        if (SituacaoPublicacao::EM_ANDAMENTO !== $this->situacao) {
            throw new \DomainException('A publicação já foi encerrada.');
        }

        $this->verificacoes[$nome] = $aprovada;
    }

    public function todasAsVerificacoesPassaram(): bool
    {
        return [] !== $this->verificacoes && !in_array(false, $this->verificacoes, true);
    }

    /** @return list<string> */
    public function verificacoesReprovadas(): array
    {
        return array_keys(array_filter($this->verificacoes, static fn (bool $ok) => !$ok));
    }

    public function concluir(): void
    {
        if (!$this->todasAsVerificacoesPassaram()) {
            throw new \DomainException(
                'Publicação com verificação reprovada não se conclui: '
                .implode(', ', $this->verificacoesReprovadas())
            );
        }

        $this->situacao = SituacaoPublicacao::CONCLUIDA;
        $this->encerradaEm = new \DateTimeImmutable();
    }

    public function reverter(string $porque): void
    {
        if (SituacaoPublicacao::EM_ANDAMENTO !== $this->situacao) {
            throw new \DomainException('Só publicação em andamento pode ser revertida.');
        }

        $this->portal->publicarVersao($this->versaoAnterior);
        $this->situacao = SituacaoPublicacao::REVERTIDA;
        $this->observacao = $porque;
        $this->encerradaEm = new \DateTimeImmutable();
    }
}
