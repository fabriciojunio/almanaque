<?php

declare(strict_types=1);

namespace App\Dominio\Busca;

final readonly class Resultado
{
    /**
     * @param list<ItemEncontrado> $itens
     * @param array<string, int>   $facetasPorCategoria nome da categoria => quantidade
     */
    public function __construct(
        public array $itens,
        public int $total,
        public array $facetasPorCategoria,
        public string $motor,
        public bool $comReserva = false,
    ) {
    }

    public static function vazio(string $motor): self
    {
        return new self([], 0, [], $motor);
    }

    public function estaVazio(): bool
    {
        return [] === $this->itens;
    }

    public function totalDePaginas(int $tamanhoDaPagina): int
    {
        return max(1, (int) ceil($this->total / $tamanhoDaPagina));
    }
}
