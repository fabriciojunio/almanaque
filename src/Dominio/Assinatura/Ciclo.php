<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

enum Ciclo: string
{
    case MENSAL = 'mensal';
    case ANUAL = 'anual';

    public function meses(): int
    {
        return match ($this) {
            self::MENSAL => 1,
            self::ANUAL => 12,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::MENSAL => 'Mensal',
            self::ANUAL => 'Anual',
        };
    }
}
