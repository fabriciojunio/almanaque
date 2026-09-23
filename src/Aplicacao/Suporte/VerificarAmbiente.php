<?php

declare(strict_types=1);

namespace App\Aplicacao\Suporte;

use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Anuncio\Repositorio\CategoriaRepositorio;
use App\Dominio\Busca\Consulta;
use App\Dominio\Busca\MotorDeBusca;
use App\Dominio\Portal\Portal;

/**
 * A conferência que se faz no ambiente do cliente depois de publicar.
 *
 * São as perguntas que o suporte responderia na mão, abrindo o portal e
 * clicando: o guia tem anúncio, a busca devolve resultado, as categorias
 * carregam. Estão aqui para que a resposta seja a mesma toda vez e fique
 * registrada na publicação.
 */
final readonly class VerificarAmbiente
{
    public function __construct(
        private AnuncioRepositorio $anuncios,
        private CategoriaRepositorio $categorias,
        private MotorDeBusca $motor,
    ) {
    }

    /**
     * @return array<string, bool> nome da verificação => passou
     */
    public function executar(Portal $portal): array
    {
        return [
            'portal está ativo' => $portal->estaNoAr(),
            'guia tem anúncio publicado' => $this->anuncios->contarNoGuia($portal) > 0,
            'categorias carregam' => [] !== $this->categorias->raizesDoPortal($portal),
            'busca responde' => $this->buscaResponde($portal),
        ];
    }

    private function buscaResponde(Portal $portal): bool
    {
        try {
            $this->motor->buscar(new Consulta($portal, tamanhoDaPagina: 1));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
