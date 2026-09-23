<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

use App\Dominio\Assinatura\Repositorio\PlanoRepositorio;
use App\Dominio\Portal\Portal;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * O plano que o anunciante contrata dentro de um portal.
 *
 * O preço fica em centavos, como inteiro. Guardar dinheiro em float é o
 * caminho curto para o total da fatura fechar com um centavo de diferença.
 */
#[ORM\Entity(repositoryClass: PlanoRepositorio::class)]
#[ORM\Table(name: 'planos')]
class Plano
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portal::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 60)]
    private string $nome;

    #[ORM\Column]
    private int $precoEmCentavos;

    #[ORM\Column(length: 10, enumType: Ciclo::class)]
    private Ciclo $ciclo;

    #[ORM\Column]
    private bool $incluiDestaque = false;

    #[ORM\Column]
    private int $limiteDeImagens = 3;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $criadoEm;

    public function __construct(
        Portal $portal,
        string $nome,
        int $precoEmCentavos,
        Ciclo $ciclo,
        bool $incluiDestaque = false,
        int $limiteDeImagens = 3,
    ) {
        if ($precoEmCentavos < 0) {
            throw new \InvalidArgumentException('Preço não pode ser negativo.');
        }

        $this->portal = $portal;
        $this->nome = $nome;
        $this->precoEmCentavos = $precoEmCentavos;
        $this->ciclo = $ciclo;
        $this->incluiDestaque = $incluiDestaque;
        $this->limiteDeImagens = $limiteDeImagens;
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function portal(): Portal
    {
        return $this->portal;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function precoEmCentavos(): int
    {
        return $this->precoEmCentavos;
    }

    public function ciclo(): Ciclo
    {
        return $this->ciclo;
    }

    public function incluiDestaque(): bool
    {
        return $this->incluiDestaque;
    }

    public function limiteDeImagens(): int
    {
        return $this->limiteDeImagens;
    }

    public function estaAtivo(): bool
    {
        return $this->ativo;
    }

    public function criadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function ehGratuito(): bool
    {
        return 0 === $this->precoEmCentavos;
    }

    public function encerrar(): void
    {
        $this->ativo = false;
    }
}
