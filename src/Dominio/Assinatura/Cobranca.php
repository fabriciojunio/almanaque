<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Uma tentativa de cobrar o ciclo da assinatura.
 *
 * A competência é o ano e o mês do ciclo, e existe para a rotina de cobrança
 * poder rodar duas vezes sem cobrar o anunciante duas vezes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cobrancas')]
#[ORM\Index(name: 'cobranca_situacao', columns: ['situacao'])]
class Cobranca
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Assinatura::class, inversedBy: 'cobrancas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Assinatura $assinatura;

    #[ORM\Column]
    private int $valorEmCentavos;

    #[ORM\Column(length: 7)]
    private string $competencia;

    #[ORM\Column(length: 20, enumType: SituacaoCobranca::class)]
    private SituacaoCobranca $situacao = SituacaoCobranca::PENDENTE;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $motivoDaRecusa = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $identificadorNoGateway = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $abertaEm;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $fechadaEm = null;

    public function __construct(
        Assinatura $assinatura,
        int $valorEmCentavos,
        string $competencia,
        \DateTimeImmutable $abertaEm,
    ) {
        $this->assinatura = $assinatura;
        $this->valorEmCentavos = $valorEmCentavos;
        $this->competencia = $competencia;
        $this->abertaEm = $abertaEm;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assinatura(): Assinatura
    {
        return $this->assinatura;
    }

    public function valorEmCentavos(): int
    {
        return $this->valorEmCentavos;
    }

    public function competencia(): string
    {
        return $this->competencia;
    }

    public function situacao(): SituacaoCobranca
    {
        return $this->situacao;
    }

    public function motivoDaRecusa(): ?string
    {
        return $this->motivoDaRecusa;
    }

    public function identificadorNoGateway(): ?string
    {
        return $this->identificadorNoGateway;
    }

    public function abertaEm(): \DateTimeImmutable
    {
        return $this->abertaEm;
    }

    public function fechadaEm(): ?\DateTimeImmutable
    {
        return $this->fechadaEm;
    }

    public function foiPaga(): bool
    {
        return SituacaoCobranca::PAGA === $this->situacao;
    }

    public function foiRecusada(): bool
    {
        return SituacaoCobranca::RECUSADA === $this->situacao;
    }

    public function estaPendente(): bool
    {
        return SituacaoCobranca::PENDENTE === $this->situacao;
    }

    public function anotarIdentificadorNoGateway(string $identificador): void
    {
        $this->identificadorNoGateway = $identificador;
    }

    public function marcarComoPaga(): void
    {
        $this->exigirPendente();

        $this->situacao = SituacaoCobranca::PAGA;
        $this->fechadaEm = new \DateTimeImmutable();
    }

    public function marcarComoRecusada(string $motivo): void
    {
        $this->exigirPendente();

        $this->situacao = SituacaoCobranca::RECUSADA;
        $this->motivoDaRecusa = mb_substr($motivo, 0, 200);
        $this->fechadaEm = new \DateTimeImmutable();
    }

    public function estornar(): void
    {
        if (!$this->foiPaga()) {
            throw new \DomainException('Só cobrança paga pode ser estornada.');
        }

        $this->situacao = SituacaoCobranca::ESTORNADA;
        $this->fechadaEm = new \DateTimeImmutable();
    }

    public function valorEmReais(): string
    {
        return number_format($this->valorEmCentavos / 100, 2, ',', '.');
    }

    private function exigirPendente(): void
    {
        if (!$this->estaPendente()) {
            throw new \DomainException(sprintf(
                'A cobrança da competência %s já foi fechada como %s.',
                $this->competencia,
                $this->situacao->value,
            ));
        }
    }
}
