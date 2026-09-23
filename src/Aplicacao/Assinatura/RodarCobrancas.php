<?php

declare(strict_types=1);

namespace App\Aplicacao\Assinatura;

use App\Dominio\Assinatura\Assinatura;
use App\Dominio\Assinatura\Gateway;
use App\Dominio\Assinatura\Repositorio\AssinaturaRepositorio;
use App\Dominio\Busca\Indexador;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * A rotina diária de cobrança.
 *
 * Três decisões que sustentam essa rotina:
 *
 * Cada assinatura é gravada na sua própria transação. Uma cobrança que estoura
 * não pode levar junto as outras trinta que já deram certo, e cobrar de novo
 * quem já pagou é pior que atrasar quem não pagou.
 *
 * A falha de uma assinatura não interrompe o lote. Ela é registrada com o
 * identificador da assinatura e a rotina segue, porque o caso comum de falha é
 * o adquirente devolver erro para um cartão específico.
 *
 * A idempotência é da entidade, na competência da cobrança. Rodar a rotina
 * duas vezes no mesmo dia, o que acontece quando alguém repete o comando na
 * mão, não cobra o anunciante duas vezes.
 */
final readonly class RodarCobrancas
{
    public function __construct(
        private AssinaturaRepositorio $assinaturas,
        private Gateway $gateway,
        private Indexador $indexador,
        private EntityManagerInterface $gerenciador,
        private LoggerInterface $log,
    ) {
    }

    public function executar(\DateTimeImmutable $dia = new \DateTimeImmutable(), int $lote = 100): ResumoDaCobranca
    {
        $vencidas = $this->assinaturas->vencidasAte($dia, $lote);
        $resumo = new ResumoDaCobranca();

        foreach ($vencidas as $assinatura) {
            try {
                $this->cobrar($assinatura, $dia, $resumo);
            } catch (\Throwable $falha) {
                $resumo->falhou();
                $this->log->error('Falha ao cobrar a assinatura.', [
                    'assinatura' => $assinatura->id(),
                    'erro' => $falha->getMessage(),
                ]);
            }
        }

        $this->log->info('Rotina de cobrança encerrada.', $resumo->paraLog());

        return $resumo;
    }

    private function cobrar(Assinatura $assinatura, \DateTimeImmutable $dia, ResumoDaCobranca $resumo): void
    {
        $this->gerenciador->wrapInTransaction(function () use ($assinatura, $dia, $resumo): void {
            $cobranca = $assinatura->abrirCobranca($dia);

            if (!$cobranca->estaPendente()) {
                $resumo->jaCobrada();

                return;
            }

            $resposta = $this->gateway->cobrar($cobranca);
            $cobranca->anotarIdentificadorNoGateway($resposta->identificador);

            if ($resposta->aprovada) {
                $assinatura->confirmarPagamento($cobranca);
                $resumo->paga($cobranca->valorEmCentavos());
            } else {
                $assinatura->registrarRecusa($cobranca, $resposta->motivo ?? 'sem motivo informado');
                $resumo->recusada();
            }

            $this->gerenciador->persist($cobranca);
            $this->gerenciador->flush();
        });

        // O índice é atualizado fora da transação: anúncio que saiu do guia
        // por falta de pagamento precisa sumir da busca, e uma falha aqui não
        // pode desfazer uma cobrança que já foi aprovada pelo adquirente.
        $this->indexador->indexar($assinatura->anuncio());
    }
}
