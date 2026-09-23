<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

enum SituacaoPublicacao: string
{
    case EM_ANDAMENTO = 'em_andamento';
    case CONCLUIDA = 'concluida';
    case REVERTIDA = 'revertida';

    public function rotulo(): string
    {
        return match ($this) {
            self::EM_ANDAMENTO => 'Em andamento',
            self::CONCLUIDA => 'Concluída',
            self::REVERTIDA => 'Revertida',
        };
    }
}
