<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

/**
 * A porta para quem cobra de verdade.
 *
 * O domínio não sabe se do outro lado está um adquirente, um boleto ou o
 * gateway de mentira usado na demonstração. Sabe que a resposta é aprovada ou
 * recusada com motivo.
 */
interface Gateway
{
    public function cobrar(Cobranca $cobranca): RespostaDoGateway;
}
