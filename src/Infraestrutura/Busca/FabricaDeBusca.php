<?php

declare(strict_types=1);

namespace App\Infraestrutura\Busca;

use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Anuncio\Repositorio\CategoriaRepositorio;
use App\Dominio\Busca\Indexador;
use App\Dominio\Busca\MotorDeBusca;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

/**
 * Decide, na partida, qual busca a aplicação vai usar.
 *
 * Sem ELASTICSEARCH_URL, o sistema inteiro roda na busca do banco e o
 * indexador não faz nada. É o que permite subir o projeto e rodar a bateria
 * de testes sem um serviço a mais no ar, e é também o caminho de
 * emergência quando o índice precisa ser desligado em produção.
 */
final class FabricaDeBusca
{
    public static function motor(
        string $url,
        string $indice,
        AnuncioRepositorio $anuncios,
        CategoriaRepositorio $categorias,
        LoggerInterface $log,
    ): MotorDeBusca {
        $reserva = new BuscaNoBanco($anuncios, $categorias);

        if ('' === trim($url)) {
            return $reserva;
        }

        return new MotorComReserva(
            new BuscaElasticsearch(self::cliente($url), $indice, $log),
            $reserva,
            $log,
        );
    }

    public static function indexador(string $url, string $indice, LoggerInterface $log): Indexador
    {
        if ('' === trim($url)) {
            return new IndexadorInerte();
        }

        return new IndexadorElasticsearch(self::cliente($url), $indice, $log);
    }

    private static function cliente(string $url): Client
    {
        return ClientBuilder::create()
            ->setHosts([$url])
            ->setRetries(1)
            ->build();
    }
}
