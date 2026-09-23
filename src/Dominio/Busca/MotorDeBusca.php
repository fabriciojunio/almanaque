<?php

declare(strict_types=1);

namespace App\Dominio\Busca;

/**
 * A porta da busca.
 *
 * Existem duas implementações de propósito: o Elasticsearch, que é o motor de
 * verdade, e o banco, que é a reserva. O guia do cliente não pode ficar sem
 * busca porque um serviço caiu.
 */
interface MotorDeBusca
{
    public function buscar(Consulta $consulta): Resultado;

    /** Nome curto do motor, que aparece no rodapé do resultado e no log. */
    public function nome(): string;

    public function estaDisponivel(): bool;
}
