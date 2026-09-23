<?php

declare(strict_types=1);

namespace App\Infraestrutura\Busca;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Busca\Indexador;
use App\Dominio\Portal\Portal;

/**
 * O indexador de quem não tem Elasticsearch ligado.
 *
 * Não faz nada, de propósito. É o que permite o resto do código chamar o
 * indexador sem perguntar antes se existe índice, e é o que faz a bateria de
 * testes rodar sem serviço nenhum no ar.
 */
final class IndexadorInerte implements Indexador
{
    public function criarIndice(): void
    {
    }

    public function indexar(Anuncio $anuncio): void
    {
    }

    public function remover(Anuncio $anuncio): void
    {
    }

    public function reindexar(iterable $anuncios): int
    {
        return 0;
    }

    public function apagarIndiceDoPortal(Portal $portal): void
    {
    }
}
