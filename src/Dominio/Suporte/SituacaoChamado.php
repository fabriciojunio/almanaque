<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

enum SituacaoChamado: string
{
    case ABERTO = 'aberto';
    case EM_INVESTIGACAO = 'em_investigacao';
    case AGUARDANDO_CLIENTE = 'aguardando_cliente';
    case RESOLVIDO = 'resolvido';
    case FECHADO = 'fechado';

    public function rotulo(): string
    {
        return match ($this) {
            self::ABERTO => 'Aberto',
            self::EM_INVESTIGACAO => 'Em investigação',
            self::AGUARDANDO_CLIENTE => 'Aguardando cliente',
            self::RESOLVIDO => 'Resolvido',
            self::FECHADO => 'Fechado',
        };
    }

    public function estaNaFila(): bool
    {
        return in_array($this, [self::ABERTO, self::EM_INVESTIGACAO, self::AGUARDANDO_CLIENTE], true);
    }

    /** O relógio do prazo para enquanto a bola está com o cliente. */
    public function contaTempoDeResposta(): bool
    {
        return in_array($this, [self::ABERTO, self::EM_INVESTIGACAO], true);
    }
}
