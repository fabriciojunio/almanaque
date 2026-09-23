<?php

declare(strict_types=1);

namespace App\Interface\Api;

use App\Dominio\Busca\MotorDeBusca;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sonda de saúde para o balanceador e para o monitoramento.
 *
 * Responde sem autenticação, então não diz versão de biblioteca, endereço de
 * banco nem contagem de registro: só se cada dependência respondeu. A busca
 * aparece separada do banco de propósito, porque índice fora é degradação e
 * banco fora é parada.
 */
final class SaudeController extends AbstractController
{
    public function __construct(
        private readonly Connection $conexao,
        private readonly MotorDeBusca $busca,
        private readonly string $versaoDaAplicacao,
    ) {
    }

    #[Route('/rest/saude', name: 'api_saude', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $banco = $this->respondeu(fn () => $this->conexao->executeQuery('SELECT 1')->fetchOne());
        $busca = $this->respondeu(fn () => $this->busca->estaDisponivel());

        return $this->json([
            'status' => $banco ? 'no ar' : 'parado',
            'versao' => $this->versaoDaAplicacao,
            'dependencias' => [
                'banco' => $banco,
                'busca' => $busca,
            ],
        ], $banco ? 200 : 503);
    }

    private function respondeu(callable $checagem): bool
    {
        try {
            return false !== $checagem();
        } catch (\Throwable) {
            return false;
        }
    }
}
