<?php

declare(strict_types=1);

namespace App\Interface\Api;

use App\Aplicacao\Busca\BuscarNoGuia;
use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Anuncio\Repositorio\CategoriaRepositorio;
use App\Dominio\Busca\ItemEncontrado;
use App\Dominio\Portal\Portal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A parte pública da API: o guia de cada portal.
 *
 * É pública porque o guia é público, e é limitada por chamadas por minuto
 * porque busca custa e raspagem é o uso previsível de um diretório aberto.
 */
#[Route('/api/g/{portal}', requirements: ['portal' => '[a-z0-9\-]{2,60}'])]
final class GuiaController extends AbstractController
{
    public function __construct(
        private readonly BuscarNoGuia $busca,
        private readonly AnuncioRepositorio $anuncios,
        private readonly CategoriaRepositorio $categorias,
        private readonly RateLimiterFactoryInterface $limitadorDeBusca,
    ) {
    }

    #[Route('/anuncios', name: 'api_guia_anuncios', methods: ['GET'])]
    public function buscar(Portal $portal, Request $requisicao): JsonResponse
    {
        if (!$this->limitadorDeBusca->create($requisicao->getClientIp())->consume()->isAccepted()) {
            return $this->json(['erro' => 'Muitas buscas seguidas. Espere um instante.'], 429);
        }

        $parametros = $requisicao->query->all();
        $resultado = $this->busca->apartirDaUrl($portal, $parametros);
        $porPagina = max(1, min((int) ($parametros['por_pagina'] ?? 12), 50));

        return $this->json([
            'dados' => array_map($this->paraLista(...), $resultado->itens),
            'total' => $resultado->total,
            'pagina' => max(1, (int) ($parametros['pagina'] ?? 1)),
            'por_pagina' => $porPagina,
            'paginas' => $resultado->totalDePaginas($porPagina),
            'facetas' => $resultado->facetasPorCategoria,
            'busca' => [
                'motor' => $resultado->motor,
                'em_reserva' => $resultado->comReserva,
            ],
        ]);
    }

    #[Route('/anuncios/{apelido}', name: 'api_guia_anuncio', methods: ['GET'], requirements: ['apelido' => '[a-z0-9\-]{2,160}'])]
    public function detalhe(Portal $portal, string $apelido): JsonResponse
    {
        $anuncio = $this->anuncios->porApelido($portal, $apelido);

        if (null === $anuncio || !$anuncio->estaNoGuia()) {
            return $this->json(['erro' => 'Anúncio não encontrado.'], 404);
        }

        $anuncio->registrarVisualizacao();
        $this->anuncios->salvar($anuncio);

        return $this->json([
            'id' => $anuncio->id(),
            'titulo' => $anuncio->titulo(),
            'apelido' => $anuncio->apelido(),
            'descricao' => $anuncio->descricao(),
            'categoria' => [
                'nome' => $anuncio->categoria()->nome(),
                'apelido' => $anuncio->categoria()->apelido(),
                'caminho' => $anuncio->categoria()->caminho(),
            ],
            'contato' => [
                'telefone' => $anuncio->telefone(),
                'site' => $anuncio->site(),
                'endereco' => $anuncio->endereco(),
                'bairro' => $anuncio->bairro(),
                'cidade' => $anuncio->cidade(),
            ],
            'local' => null !== $anuncio->latitude()
                ? ['latitude' => $anuncio->latitude(), 'longitude' => $anuncio->longitude()]
                : null,
            'imagens' => $anuncio->imagens(),
            'destaque' => $anuncio->ehDestaque(),
            'publicado_em' => $anuncio->publicadoEm()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/categorias', name: 'api_guia_categorias', methods: ['GET'])]
    public function categorias(Portal $portal): JsonResponse
    {
        $contagem = $this->anuncios->contagemPorCategoria($portal);

        $arvore = array_map(
            fn ($raiz) => [
                'nome' => $raiz->nome(),
                'apelido' => $raiz->apelido(),
                'anuncios' => $contagem[(int) $raiz->id()] ?? 0,
                'filhas' => array_values(array_map(
                    static fn ($filha) => [
                        'nome' => $filha->nome(),
                        'apelido' => $filha->apelido(),
                        'anuncios' => $contagem[(int) $filha->id()] ?? 0,
                    ],
                    array_filter(
                        $this->categorias->doPortal($portal),
                        static fn ($categoria) => $categoria->pai() === $raiz,
                    ),
                )),
            ],
            $this->categorias->raizesDoPortal($portal),
        );

        return $this->json(['dados' => $arvore]);
    }

    /** @return array<string, mixed> */
    private function paraLista(ItemEncontrado $item): array
    {
        return [
            'id' => $item->id,
            'titulo' => $item->titulo,
            'apelido' => $item->apelido,
            'resumo' => $item->resumo,
            'categoria' => $item->categoria,
            'cidade' => $item->cidade,
            'telefone' => $item->telefone,
            'destaque' => $item->destaque,
            'imagem' => $item->imagem,
        ];
    }
}
