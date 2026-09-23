<?php

declare(strict_types=1);

namespace App\Dominio\Portal;

enum SituacaoPortal: string
{
    case ATIVO = 'ativo';
    case SUSPENSO = 'suspenso';

    public function rotulo(): string
    {
        return match ($this) {
            self::ATIVO => 'No ar',
            self::SUSPENSO => 'Suspenso',
        };
    }
}
