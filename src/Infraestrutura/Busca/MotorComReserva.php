<?php

declare(strict_types=1);

namespace App\Infraestrutura\Busca;

use App\Dominio\Busca\Consulta;
use App\Dominio\Busca\MotorDeBusca;
use App\Dominio\Busca\Resultado;
use Psr\Log\LoggerInterface;

/**
 * Tenta o motor principal e, se ele falhar, responde pela reserva.
 *
 * É o que separa "a busca está pior hoje" de "o guia do cliente está fora do
 * ar". A falha é registrada em nível de alerta com a mensagem do erro, e o
 * resultado sai marcado, para o rodapé da página poder avisar e para o
 * suporte saber, ao abrir o chamado, que naquele momento o índice estava
 * fora.
 */
final class MotorComReserva implements MotorDeBusca
{
    public function __construct(
        private readonly MotorDeBusca $principal,
        private readonly MotorDeBusca $reserva,
        private readonly LoggerInterface $log,
    ) {
    }

    public function buscar(Consulta $consulta): Resultado
    {
        try {
            return $this->principal->buscar($consulta);
        } catch (\Throwable $falha) {
            $this->log->alert('A busca principal falhou; respondendo pela reserva.', [
                'motor' => $this->principal->nome(),
                'reserva' => $this->reserva->nome(),
                'portal' => $consulta->portal->apelido(),
                'erro' => $falha->getMessage(),
            ]);

            $resultado = $this->reserva->buscar($consulta);

            return new Resultado(
                itens: $resultado->itens,
                total: $resultado->total,
                facetasPorCategoria: $resultado->facetasPorCategoria,
                motor: $resultado->motor,
                comReserva: true,
            );
        }
    }

    public function nome(): string
    {
        return $this->principal->nome();
    }

    public function estaDisponivel(): bool
    {
        return $this->principal->estaDisponivel() || $this->reserva->estaDisponivel();
    }
}
