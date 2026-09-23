<?php

declare(strict_types=1);

namespace App\Infraestrutura\Sessao;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * A sessão do console, guardada no banco.
 *
 * Em produção a aplicação roda sem disco de escrita e a instância morre a
 * qualquer momento: sessão em arquivo deslogaria o analista assim que ele
 * caísse em outra instância.
 *
 * O handler reaproveita a conexão que o Doctrine já abriu, em vez de abrir
 * uma segunda. Num banco gerenciado com limite de conexão, e num ambiente
 * onde cada requisição é um processo novo, isso importa.
 */
final class FabricaDeSessaoNoBanco
{
    public static function criar(Connection $conexao): PdoSessionHandler
    {
        $nativa = $conexao->getNativeConnection();

        if (!$nativa instanceof \PDO) {
            throw new \RuntimeException('A sessão no banco precisa de uma conexão PDO; o driver em uso não fornece uma.');
        }

        return new PdoSessionHandler($nativa, [
            'db_table' => 'sessoes',
            // A trava de escrita do PdoSessionHandler segura a conexão pelo
            // tempo da requisição. Num banco gerenciado, com conexão cara,
            // sai mais barato conviver com a corrida de duas abas do que
            // manter transação aberta.
            'lock_mode' => PdoSessionHandler::LOCK_NONE,
        ]);
    }
}
