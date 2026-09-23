<?php

declare(strict_types=1);

namespace App\Dominio\Busca;

final readonly class ItemEncontrado
{
    public function __construct(
        public int $id,
        public string $titulo,
        public string $apelido,
        public string $resumo,
        public string $categoria,
        public ?string $cidade,
        public ?string $telefone,
        public bool $destaque,
        public ?string $imagem = null,
    ) {
    }
}
