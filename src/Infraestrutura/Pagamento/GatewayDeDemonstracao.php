<?php

declare(strict_types=1);

namespace App\Infraestrutura\Pagamento;

use App\Dominio\Assinatura\Cobranca;
use App\Dominio\Assinatura\Gateway;
use App\Dominio\Assinatura\RespostaDoGateway;
use Psr\Log\LoggerInterface;

/**
 * O adquirente de mentira.
 *
 * Aprova quase tudo e recusa de um jeito previsível: valor terminado em 13
 * centavos é recusado. Parece bobagem, e é o que permite demonstrar a
 * inadimplência e testar o caminho da recusa sem depender de sandbox de
 * terceiro, que cai justamente no dia da demonstração.
 */
final readonly class GatewayDeDemonstracao implements Gateway
{
    private const CENTAVOS_QUE_RECUSAM = 13;

    public function __construct(private LoggerInterface $log)
    {
    }

    public function cobrar(Cobranca $cobranca): RespostaDoGateway
    {
        $identificador = 'dem_'.bin2hex(random_bytes(6));

        if (self::CENTAVOS_QUE_RECUSAM === $cobranca->valorEmCentavos() % 100) {
            $this->log->info('Cobrança recusada pelo adquirente de demonstração.', [
                'cobranca' => $cobranca->id(),
                'valor' => $cobranca->valorEmCentavos(),
            ]);

            return RespostaDoGateway::recusada($identificador, 'cartão recusado pelo emissor');
        }

        return RespostaDoGateway::aprovada($identificador);
    }
}
