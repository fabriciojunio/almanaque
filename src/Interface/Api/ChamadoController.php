<?php

declare(strict_types=1);

namespace App\Interface\Api;

use App\Aplicacao\Suporte\AbrirChamado;
use App\Dominio\Identidade\Usuario;
use App\Dominio\Portal\Repositorio\PortalRepositorio;
use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\Classificacao;
use App\Dominio\Suporte\ProblemaConhecido;
use App\Dominio\Suporte\Repositorio\ChamadoRepositorio;
use App\Dominio\Suporte\Repositorio\ProblemaConhecidoRepositorio;
use App\Infraestrutura\Http\IdentificadorDeRequisicao;
use App\Infraestrutura\Seguranca\VotanteDeChamado;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Os chamados, pela API.
 *
 * Quem é do portal abre e acompanha o que é dele. Quem é do suporte enxerga a
 * fila inteira, triagem e anotação interna. A separação não é de tela: é do
 * votante, e cada regra tem teste.
 */
#[Route('/rest/chamados')]
final class ChamadoController extends AbstractController
{
    public function __construct(
        private readonly ChamadoRepositorio $chamados,
        private readonly ProblemaConhecidoRepositorio $problemas,
        private readonly PortalRepositorio $portais,
        private readonly AbrirChamado $abrir,
        private readonly EntityManagerInterface $gerenciador,
        private readonly IdentificadorDeRequisicao $identificador,
        private readonly RateLimiterFactoryInterface $limitadorDeChamado,
    ) {
    }

    #[Route('', name: 'api_chamado_listar', methods: ['GET'])]
    public function listar(): JsonResponse
    {
        $usuario = $this->usuarioAutenticado();
        $portal = $usuario->portal();

        $chamados = (null === $portal || $usuario->papel()->atravessaPortais())
            ? $this->chamados->fila()
            : $this->chamados->doPortal($portal);

        return $this->json([
            'dados' => array_map($this->paraLista(...), $chamados),
        ]);
    }

    #[Route('', name: 'api_chamado_abrir', methods: ['POST'])]
    public function abrir(Request $requisicao): JsonResponse
    {
        $usuario = $this->usuarioAutenticado();

        if (!$this->limitadorDeChamado->create((string) $usuario->id())->consume()->isAccepted()) {
            return $this->json([
                'erro' => 'Muitos chamados abertos na última hora. Responda os que já estão na fila.',
            ], 429);
        }

        $corpo = json_decode($requisicao->getContent(), true);

        if (!is_array($corpo) || !isset($corpo['titulo'], $corpo['relato'])) {
            return $this->json(['erro' => 'Informe o título e o relato.'], 400);
        }

        $portal = $usuario->portal();

        if (null === $portal) {
            $apelido = (string) ($corpo['portal'] ?? '');
            $portal = $this->portais->porApelido($apelido);

            if (null === $portal) {
                return $this->json(['erro' => 'Informe o portal do chamado.'], 400);
            }
        }

        $resultado = $this->abrir->executar(
            portal: $portal,
            titulo: mb_substr(trim((string) $corpo['titulo']), 0, 160),
            relato: mb_substr(trim((string) $corpo['relato']), 0, 5000),
            autor: $usuario->email(),
            identificadorDeRequisicao: $this->identificadorInformado($corpo),
        );

        return $this->json([
            'chamado' => $this->paraLista($resultado->chamado),
            'problemas_parecidos' => array_map(
                static fn (ProblemaConhecido $problema) => [
                    'codigo' => $problema->codigo(),
                    'titulo' => $problema->titulo(),
                    'contorno' => $problema->contorno(),
                    'corrigido_na_versao' => $problema->corrigidoNaVersao(),
                ],
                $resultado->problemasParecidos,
            ),
        ], 201);
    }

    #[Route('/{id}', name: 'api_chamado_ver', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ver(int $id): JsonResponse
    {
        $chamado = $this->chamados->find($id);

        if (null === $chamado) {
            return $this->json(['erro' => 'Chamado não encontrado.'], 404);
        }

        $this->denyAccessUnlessGranted(VotanteDeChamado::VER, $chamado);

        $verAnotacaoInterna = $this->isGranted(VotanteDeChamado::VER_ANOTACAO_INTERNA, $chamado);

        $anotacoes = [];
        foreach ($chamado->anotacoes() as $anotacao) {
            if (!$anotacao->ehVisivelAoCliente() && !$verAnotacaoInterna) {
                continue;
            }

            $anotacoes[] = [
                'autor' => $anotacao->autor(),
                'texto' => $anotacao->texto(),
                'interna' => !$anotacao->ehVisivelAoCliente(),
                'escrita_em' => $anotacao->escritaEm()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json($this->paraLista($chamado) + [
            'relato' => $chamado->relato(),
            'anotacoes' => $anotacoes,
            'problema_conhecido' => $chamado->problemaConhecido()?->codigo(),
        ]);
    }

    #[Route('/{id}/triagem', name: 'api_chamado_triar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function triar(int $id, Request $requisicao): JsonResponse
    {
        $chamado = $this->chamados->find($id);

        if (null === $chamado) {
            return $this->json(['erro' => 'Chamado não encontrado.'], 404);
        }

        $this->denyAccessUnlessGranted(VotanteDeChamado::ATENDER, $chamado);

        $corpo = json_decode($requisicao->getContent(), true);
        $classificacao = Classificacao::tryFrom((string) ($corpo['classificacao'] ?? ''));

        if (null === $classificacao) {
            return $this->json([
                'erro' => 'Classificação inválida.',
                'aceitas' => array_column(Classificacao::cases(), 'value'),
            ], 422);
        }

        if (isset($corpo['reproduzido'])) {
            $chamado->registrarReproducao((bool) ($corpo['ambiente_limpo'] ?? false));
        }

        $problema = isset($corpo['problema_conhecido'])
            ? $this->problemas->porCodigo((string) $corpo['problema_conhecido'])
            : null;

        try {
            $chamado->classificar($classificacao, $problema);
        } catch (\DomainException $recusa) {
            return $this->json(['erro' => $recusa->getMessage()], 422);
        }

        $this->gerenciador->flush();

        return $this->json($this->paraLista($chamado));
    }

    /** @return array<string, mixed> */
    private function paraLista(Chamado $chamado): array
    {
        return [
            'id' => $chamado->id(),
            'titulo' => $chamado->titulo(),
            'portal' => $chamado->portal()->apelido(),
            'situacao' => $chamado->situacao()->value,
            'prioridade' => $chamado->prioridade()->value,
            'classificacao' => $chamado->classificacao()?->value,
            'versao_do_portal' => $chamado->versaoDoPortal(),
            'identificador_de_requisicao' => $chamado->identificadorDeRequisicao(),
            'responsavel' => $chamado->responsavel(),
            'prazo_de_resposta' => $chamado->prazoDeRespostaEm()->format(\DateTimeInterface::ATOM),
            'fora_do_prazo' => $chamado->estourouOPrazo(),
            'aberto_em' => $chamado->abertoEm()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function usuarioAutenticado(): Usuario
    {
        $usuario = $this->getUser();

        if (!$usuario instanceof Usuario) {
            throw $this->createAccessDeniedException();
        }

        return $usuario;
    }

    /** @param array<string, mixed> $corpo */
    private function identificadorInformado(array $corpo): string
    {
        $informado = (string) ($corpo['identificador_de_requisicao'] ?? '');

        return preg_match('/^[A-Za-z0-9\-]{8,64}$/', $informado)
            ? $informado
            : $this->identificador->atual();
    }
}
