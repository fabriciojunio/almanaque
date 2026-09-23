<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

/**
 * Prioridade é impacto, não urgência declarada pelo cliente.
 *
 * Todo chamado chega urgente. O que fura a fila é o que derruba o portal do
 * ar ou trava dinheiro entrando, porque nesses dois o prejuízo corre por
 * minuto.
 */
enum Prioridade: string
{
    case BAIXA = 'baixa';
    case NORMAL = 'normal';
    case ALTA = 'alta';
    case CRITICA = 'critica';

    public function rotulo(): string
    {
        return match ($this) {
            self::BAIXA => 'Baixa',
            self::NORMAL => 'Normal',
            self::ALTA => 'Alta',
            self::CRITICA => 'Crítica',
        };
    }

    /** Prazo de primeira resposta, em horas. */
    public function horasDeResposta(): int
    {
        return match ($this) {
            self::CRITICA => 1,
            self::ALTA => 4,
            self::NORMAL => 24,
            self::BAIXA => 72,
        };
    }

    public function peso(): int
    {
        return match ($this) {
            self::CRITICA => 4,
            self::ALTA => 3,
            self::NORMAL => 2,
            self::BAIXA => 1,
        };
    }
}
