<?php

declare(strict_types=1);

namespace App\Interface\Web;

use App\Aplicacao\Busca\BuscarNoGuia;
use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Anuncio\Repositorio\CategoriaRepositorio;
use App\Dominio\Portal\Portal;
use App\Dominio\Portal\Repositorio\PortalRepositorio;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * As páginas que o visitante vê.
 *
 * Tudo aqui é renderizado no servidor, sem framework de tela. É um guia: a
 * página precisa abrir rápido em celular ruim e ser lida pelo buscador, que é
 * de onde vem quem procura por "chaveiro perto de mim".
 */
final class GuiaController extends AbstractController
{
    public function __construct(
        private readonly PortalRepositorio $portais,
        private readonly CategoriaRepositorio $categorias,
        private readonly AnuncioRepositorio $anuncios,
        private readonly BuscarNoGuia $busca,
    ) {
    }

    #[Route('/', name: 'inicio', methods: ['GET'])]
    public function inicio(): Response
    {
        return $this->render('guia/portais.html.twig', [
            'portais' => $this->portais->noAr(),
        ]);
    }

    #[Route('/g/{portal}', name: 'guia', methods: ['GET'], requirements: ['portal' => '[a-z0-9\-]{2,60}'])]
    public function capa(Portal $portal): Response
    {
        return $this->render('guia/capa.html.twig', [
            'portal' => $portal,
            'categorias' => $this->arvore($portal),
            'destaques' => $this->anuncios->destaquesDoGuia($portal),
            'total' => $this->anuncios->contarNoGuia($portal),
        ]);
    }

    #[Route('/g/{portal}/busca', name: 'guia_busca', methods: ['GET'], requirements: ['portal' => '[a-z0-9\-]{2,60}'])]
    public function buscar(Portal $portal, Request $requisicao): Response
    {
        $parametros = $requisicao->query->all();
        $resultado = $this->busca->apartirDaUrl($portal, $parametros);
        $pagina = max(1, (int) ($parametros['pagina'] ?? 1));

        return $this->render('guia/busca.html.twig', [
            'portal' => $portal,
            'categorias' => $this->arvore($portal),
            'resultado' => $resultado,
            'termo' => $parametros['q'] ?? '',
            'categoriaEscolhida' => $parametros['categoria'] ?? null,
            'pagina' => $pagina,
            'paginas' => $resultado->totalDePaginas(12),
        ]);
    }

    #[Route('/g/{portal}/{apelido}', name: 'guia_anuncio', methods: ['GET'], requirements: [
        'portal' => '[a-z0-9\-]{2,60}',
        'apelido' => '[a-z0-9\-]{2,160}',
    ])]
    public function anuncio(Portal $portal, string $apelido): Response
    {
        $anuncio = $this->anuncios->porApelido($portal, $apelido);

        if (null === $anuncio || !$anuncio->estaNoGuia()) {
            throw $this->createNotFoundException('Anúncio não encontrado neste guia.');
        }

        $anuncio->registrarVisualizacao();
        $this->anuncios->salvar($anuncio);

        return $this->render('guia/anuncio.html.twig', [
            'portal' => $portal,
            'anuncio' => $anuncio,
        ]);
    }

    /**
     * Categorias raiz com as filhas e a contagem, que é o índice da lateral.
     *
     * @return list<array{categoria: mixed, quantidade: int, filhas: list<array{categoria: mixed, quantidade: int}>}>
     */
    private function arvore(Portal $portal): array
    {
        $contagem = $this->anuncios->contagemPorCategoria($portal);
        $todas = $this->categorias->doPortal($portal);
        $arvore = [];

        foreach ($todas as $categoria) {
            if (!$categoria->ehRaiz()) {
                continue;
            }

            $filhas = [];
            foreach ($todas as $possivelFilha) {
                if ($possivelFilha->pai() === $categoria) {
                    $filhas[] = [
                        'categoria' => $possivelFilha,
                        'quantidade' => $contagem[(int) $possivelFilha->id()] ?? 0,
                    ];
                }
            }

            // A contagem da raiz soma as filhas. Quem lê "Serviços 0" com
            // "Construção 1" logo abaixo acha que a tela está errada, e está
            // mesmo: ninguém pensa em categoria como pasta vazia.
            $daRaiz = $contagem[(int) $categoria->id()] ?? 0;
            $dasFilhas = array_sum(array_column($filhas, 'quantidade'));

            $arvore[] = [
                'categoria' => $categoria,
                'quantidade' => $daRaiz + $dasFilhas,
                'filhas' => $filhas,
            ];
        }

        return $arvore;
    }
}
