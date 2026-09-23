<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Repositorio\ChamadoRepositorio;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Um chamado de cliente.
 *
 * O ciclo que o sistema obriga é o mesmo que a investigação segue na prática:
 * o chamado chega, alguém assume, classifica em uma das quatro caixas e só
 * então resolve. Não dá para marcar como resolvido sem dizer o que era, e
 * isso é de propósito: chamado fechado sem classificação é justamente o que
 * impede descobrir, três meses depois, que o mesmo defeito voltou.
 */
#[ORM\Entity(repositoryClass: ChamadoRepositorio::class)]
#[ORM\Table(name: 'chamados')]
#[ORM\Index(name: 'chamado_fila', columns: ['situacao', 'prioridade'])]
#[ORM\Index(name: 'chamado_portal', columns: ['portal_id'])]
#[ORM\Index(name: 'chamado_requisicao', columns: ['identificador_de_requisicao'])]
class Chamado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portal::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 160)]
    private string $titulo;

    #[ORM\Column(type: Types::TEXT)]
    private string $relato;

    #[ORM\Column(length: 120)]
    private string $autor;

    #[ORM\Column(length: 20, enumType: SituacaoChamado::class)]
    private SituacaoChamado $situacao = SituacaoChamado::ABERTO;

    #[ORM\Column(length: 20, enumType: Prioridade::class)]
    private Prioridade $prioridade;

    #[ORM\Column(length: 30, enumType: Classificacao::class, nullable: true)]
    private ?Classificacao $classificacao = null;

    /**
     * O código que o cliente viu na tela de erro. É por ele que se acha a
     * linha no log, sem ter que procurar por horário.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $identificadorDeRequisicao = null;

    /**
     * A versão que o portal rodava quando o chamado entrou. Guardada aqui
     * porque o portal vai ser atualizado, e aí a informação se perde.
     */
    #[ORM\Column(length: 20)]
    private string $versaoDoPortal;

    #[ORM\Column]
    private bool $reproduzido = false;

    #[ORM\Column]
    private bool $reproduzidoEmAmbienteLimpo = false;

    #[ORM\ManyToOne(targetEntity: ProblemaConhecido::class, inversedBy: 'chamados')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProblemaConhecido $problemaConhecido = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $responsavel = null;

    /** @var Collection<int, Anotacao> */
    #[ORM\OneToMany(mappedBy: 'chamado', targetEntity: Anotacao::class, cascade: ['persist'])]
    #[ORM\OrderBy(['escritaEm' => 'ASC'])]
    private Collection $anotacoes;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $abertoEm;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $primeiraRespostaEm = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvidoEm = null;

    public function __construct(
        Portal $portal,
        string $titulo,
        string $relato,
        string $autor,
        Prioridade $prioridade = Prioridade::NORMAL,
        ?string $identificadorDeRequisicao = null,
        ?\DateTimeImmutable $abertoEm = null,
    ) {
        $this->portal = $portal;
        $this->titulo = $titulo;
        $this->relato = $relato;
        $this->autor = $autor;
        $this->prioridade = $prioridade;
        $this->identificadorDeRequisicao = $identificadorDeRequisicao;
        $this->versaoDoPortal = $portal->versao();
        $this->anotacoes = new ArrayCollection();
        $this->abertoEm = $abertoEm ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function portal(): Portal
    {
        return $this->portal;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function relato(): string
    {
        return $this->relato;
    }

    public function autor(): string
    {
        return $this->autor;
    }

    public function situacao(): SituacaoChamado
    {
        return $this->situacao;
    }

    public function prioridade(): Prioridade
    {
        return $this->prioridade;
    }

    public function classificacao(): ?Classificacao
    {
        return $this->classificacao;
    }

    public function identificadorDeRequisicao(): ?string
    {
        return $this->identificadorDeRequisicao;
    }

    public function versaoDoPortal(): string
    {
        return $this->versaoDoPortal;
    }

    public function foiReproduzido(): bool
    {
        return $this->reproduzido;
    }

    public function foiReproduzidoEmAmbienteLimpo(): bool
    {
        return $this->reproduzidoEmAmbienteLimpo;
    }

    public function problemaConhecido(): ?ProblemaConhecido
    {
        return $this->problemaConhecido;
    }

    public function responsavel(): ?string
    {
        return $this->responsavel;
    }

    /** @return Collection<int, Anotacao> */
    public function anotacoes(): Collection
    {
        return $this->anotacoes;
    }

    public function abertoEm(): \DateTimeImmutable
    {
        return $this->abertoEm;
    }

    public function primeiraRespostaEm(): ?\DateTimeImmutable
    {
        return $this->primeiraRespostaEm;
    }

    public function resolvidoEm(): ?\DateTimeImmutable
    {
        return $this->resolvidoEm;
    }

    public function assumir(string $responsavel): void
    {
        $this->exigirAberto();

        $this->responsavel = $responsavel;
        $this->situacao = SituacaoChamado::EM_INVESTIGACAO;
    }

    public function anotar(string $autor, string $texto, bool $visivelAoCliente): Anotacao
    {
        $anotacao = new Anotacao($this, $autor, $texto, $visivelAoCliente);
        $this->anotacoes->add($anotacao);

        if ($visivelAoCliente && null === $this->primeiraRespostaEm) {
            $this->primeiraRespostaEm = $anotacao->escritaEm();
        }

        return $anotacao;
    }

    /**
     * Registra a reprodução. O segundo argumento é o que separa defeito do
     * produto de alteração feita pelo cliente, e por isso não tem valor
     * padrão: quem investiga precisa responder.
     */
    public function registrarReproducao(bool $emAmbienteLimpo): void
    {
        $this->reproduzido = true;
        $this->reproduzidoEmAmbienteLimpo = $emAmbienteLimpo;
    }

    public function classificar(Classificacao $classificacao, ?ProblemaConhecido $problema = null): void
    {
        if (Classificacao::DEFEITO === $classificacao && !$this->reproduzido) {
            throw new ChamadoNaoReproduzidoException($this->titulo);
        }

        if (Classificacao::CUSTOMIZACAO_DO_CLIENTE === $classificacao && !$this->portal->estaCustomizado()) {
            throw new \DomainException(
                'O portal não tem customização registrada; a classificação não se sustenta.'
            );
        }

        $this->classificacao = $classificacao;
        $this->problemaConhecido = $problema;

        if (SituacaoChamado::ABERTO === $this->situacao) {
            $this->situacao = SituacaoChamado::EM_INVESTIGACAO;
        }
    }

    public function devolverAoCliente(): void
    {
        if (!$this->situacao->estaNaFila()) {
            throw new \DomainException('Chamado fora da fila não volta para o cliente.');
        }

        $this->situacao = SituacaoChamado::AGUARDANDO_CLIENTE;
    }

    public function resolver(\DateTimeImmutable $quando = new \DateTimeImmutable()): void
    {
        if (null === $this->classificacao) {
            throw new ChamadoSemClassificacaoException($this->titulo);
        }

        $this->situacao = SituacaoChamado::RESOLVIDO;
        $this->resolvidoEm = $quando;
        $this->primeiraRespostaEm ??= $quando;
    }

    public function fechar(): void
    {
        if (SituacaoChamado::RESOLVIDO !== $this->situacao) {
            throw new \DomainException('Só chamado resolvido pode ser fechado.');
        }

        $this->situacao = SituacaoChamado::FECHADO;
    }

    public function repriorizar(Prioridade $prioridade, string $porque): void
    {
        $this->anotar('sistema', sprintf(
            'Prioridade alterada de %s para %s: %s',
            $this->prioridade->rotulo(),
            $prioridade->rotulo(),
            $porque,
        ), false);

        $this->prioridade = $prioridade;
    }

    public function prazoDeRespostaEm(): \DateTimeImmutable
    {
        return $this->abertoEm->modify(sprintf('+%d hours', $this->prioridade->horasDeResposta()));
    }

    public function estourouOPrazo(\DateTimeImmutable $agora = new \DateTimeImmutable()): bool
    {
        if (null !== $this->primeiraRespostaEm) {
            return $this->primeiraRespostaEm > $this->prazoDeRespostaEm();
        }

        if (!$this->situacao->contaTempoDeResposta()) {
            return false;
        }

        return $agora > $this->prazoDeRespostaEm();
    }

    private function exigirAberto(): void
    {
        if (SituacaoChamado::ABERTO !== $this->situacao) {
            throw new \DomainException(sprintf(
                'O chamado já está %s.',
                mb_strtolower($this->situacao->rotulo())
            ));
        }
    }
}
