<?php

declare(strict_types=1);

namespace App\Dominio\Anuncio;

use App\Dominio\Anuncio\Repositorio\CategoriaRepositorio;
use App\Dominio\Portal\Portal;
use Doctrine\ORM\Mapping as ORM;

/**
 * Categoria do guia, com um nível de subcategoria.
 *
 * Árvore de profundidade arbitrária foi descartada: em guia comercial, dois
 * níveis cobrem o caso real e evitam a consulta recursiva, que em MySQL só
 * ficou razoável na 8 e continua cara.
 */
#[ORM\Entity(repositoryClass: CategoriaRepositorio::class)]
#[ORM\Table(name: 'categorias')]
#[ORM\UniqueConstraint(name: 'categoria_apelido_portal', columns: ['portal_id', 'apelido'])]
class Categoria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portal::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 80)]
    private string $nome;

    #[ORM\Column(length: 80)]
    private string $apelido;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $pai = null;

    #[ORM\Column]
    private int $ordem = 0;

    public function __construct(Portal $portal, string $nome, string $apelido, ?self $pai = null)
    {
        if (null !== $pai && null !== $pai->pai) {
            throw new \InvalidArgumentException('Categoria só tem dois níveis.');
        }

        $this->portal = $portal;
        $this->nome = $nome;
        $this->apelido = $apelido;
        $this->pai = $pai;
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

    public function apelido(): string
    {
        return $this->apelido;
    }

    public function pai(): ?self
    {
        return $this->pai;
    }

    public function ordem(): int
    {
        return $this->ordem;
    }

    public function definirOrdem(int $ordem): void
    {
        $this->ordem = $ordem;
    }

    public function ehRaiz(): bool
    {
        return null === $this->pai;
    }

    public function caminho(): string
    {
        $pai = $this->pai;

        return null === $pai ? $this->nome : $pai->nome().' / '.$this->nome;
    }
}
