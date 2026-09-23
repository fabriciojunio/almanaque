<?php

declare(strict_types=1);

namespace App\Dominio\Busca;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Portal\Portal;

/**
 * Quem mantém o índice em dia.
 *
 * Fica separado do motor de busca porque as duas coisas falham por motivos
 * diferentes: indexar pode parar sem o guia parar junto.
 */
interface Indexador
{
    public function criarIndice(): void;

    public function indexar(Anuncio $anuncio): void;

    public function remover(Anuncio $anuncio): void;

    /** @param iterable<Anuncio> $anuncios */
    public function reindexar(iterable $anuncios): int;

    public function apagarIndiceDoPortal(Portal $portal): void;
}
