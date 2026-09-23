<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

final readonly class RespostaDoGateway
{
    private function __construct(
        public bool $aprovada,
        public string $identificador,
        public ?string $motivo,
    ) {
    }

    public static function aprovada(string $identificador): self
    {
        return new self(true, $identificador, null);
    }

    public static function recusada(string $identificador, string $motivo): self
    {
        return new self(false, $identificador, $motivo);
    }
}
