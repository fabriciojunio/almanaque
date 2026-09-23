<?php

declare(strict_types=1);

namespace App\Dominio\Busca;

use App\Dominio\Portal\Portal;

/**
 * O que o visitante pediu. Nasce da URL e não muda depois.
 */
final readonly class Consulta
{
    public const TAMANHO_MAXIMO_DE_PAGINA = 50;

    public function __construct(
        public Portal $portal,
        public ?string $termo = null,
        public ?string $categoria = null,
        public ?string $cidade = null,
        public bool $somenteDestaques = false,
        public int $pagina = 1,
        public int $tamanhoDaPagina = 12,
    ) {
        if ($pagina < 1) {
            throw new \InvalidArgumentException('Página começa em 1.');
        }

        if ($tamanhoDaPagina < 1 || $tamanhoDaPagina > self::TAMANHO_MAXIMO_DE_PAGINA) {
            throw new \InvalidArgumentException(sprintf('Tamanho de página fora do intervalo de 1 a %d.', self::TAMANHO_MAXIMO_DE_PAGINA));
        }
    }

    public function deslocamento(): int
    {
        return ($this->pagina - 1) * $this->tamanhoDaPagina;
    }

    public function temTermo(): bool
    {
        return null !== $this->termo && '' !== trim($this->termo);
    }

    public function termoLimpo(): string
    {
        return trim($this->termo ?? '');
    }
}
