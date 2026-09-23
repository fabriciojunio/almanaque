<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

enum SituacaoCobranca: string
{
    case PENDENTE = 'pendente';
    case PAGA = 'paga';
    case RECUSADA = 'recusada';
    case ESTORNADA = 'estornada';

    public function rotulo(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::PAGA => 'Paga',
            self::RECUSADA => 'Recusada',
            self::ESTORNADA => 'Estornada',
        };
    }
}
