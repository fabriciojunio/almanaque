<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Uma linha da investigação.
 *
 * Nem tudo que o analista escreve vai para o cliente: a anotação interna é
 * onde ficam a consulta que rodou, a linha do log e a hipótese descartada. É
 * o que faz diferença quando o mesmo chamado volta seis meses depois.
 */
#[ORM\Entity]
#[ORM\Table(name: 'anotacoes')]
class Anotacao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Chamado::class, inversedBy: 'anotacoes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Chamado $chamado;

    #[ORM\Column(length: 120)]
    private string $autor;

    #[ORM\Column(type: Types::TEXT)]
    private string $texto;

    #[ORM\Column]
    private bool $visivelAoCliente;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $escritaEm;

    public function __construct(Chamado $chamado, string $autor, string $texto, bool $visivelAoCliente)
    {
        $this->chamado = $chamado;
        $this->autor = $autor;
        $this->texto = $texto;
        $this->visivelAoCliente = $visivelAoCliente;
        $this->escritaEm = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function chamado(): Chamado
    {
        return $this->chamado;
    }

    public function autor(): string
    {
        return $this->autor;
    }

    public function texto(): string
    {
        return $this->texto;
    }

    public function ehVisivelAoCliente(): bool
    {
        return $this->visivelAoCliente;
    }

    public function escritaEm(): \DateTimeImmutable
    {
        return $this->escritaEm;
    }
}
