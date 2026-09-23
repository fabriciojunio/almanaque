<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

enum SituacaoAssinatura: string
{
    case ATIVA = 'ativa';
    case INADIMPLENTE = 'inadimplente';
    case CANCELADA = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::ATIVA => 'Ativa',
            self::INADIMPLENTE => 'Em atraso',
            self::CANCELADA => 'Cancelada',
        };
    }

    public function mantemAnuncioNoAr(): bool
    {
        return self::CANCELADA !== $this;
    }
}
