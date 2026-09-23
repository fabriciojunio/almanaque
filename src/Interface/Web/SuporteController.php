<?php

declare(strict_types=1);

namespace App\Interface\Web;

use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\Classificacao;
use App\Dominio\Suporte\Repositorio\ChamadoRepositorio;
use App\Dominio\Suporte\Repositorio\ProblemaConhecidoRepositorio;
use App\Dominio\Suporte\Repositorio\PublicacaoRepositorio;
use App\Infraestrutura\Seguranca\VotanteDeChamado;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * O console de quem atende.
 *
 * A fila é ordenada por impacto e idade, a tela do chamado tem a triagem em
 * quatro caixas, e a base de problemas conhecidos fica a um clique, porque em
 * produto usado por milhares de clientes o caso quase sempre já aconteceu.
 */
#[Route('/suporte')]
#[IsGranted('ROLE_SUPORTE')]
final class SuporteController extends AbstractController
{
    public function __construct(
        private readonly ChamadoRepositorio $chamados,
        private readonly ProblemaConhecidoRepositorio $problemas,
        private readonly PublicacaoRepositorio $publicacoes,
        private readonly EntityManagerInterface $gerenciador,
    ) {
    }

    #[Route('', name: 'suporte_fila', methods: ['GET'])]
    public function fila(): Response
    {
        return $this->render('suporte/fila.html.twig', [
            'chamados' => $this->chamados->fila(),
            'contagem' => $this->chamados->contagemPorClassificacao(),
            'problemasEmAberto' => $this->problemas->emAberto(),
        ]);
    }

    #[Route('/chamados/{id}', name: 'suporte_chamado', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function chamado(int $id): Response
    {
        $chamado = $this->buscar($id);

        return $this->render('suporte/chamado.html.twig', [
            'chamado' => $chamado,
            'classificacoes' => Classificacao::cases(),
            'parecidos' => $this->parecidos($chamado),
        ]);
    }

    #[Route('/chamados/{id}/assumir', name: 'suporte_assumir', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assumir(int $id, Request $requisicao): Response
    {
        $chamado = $this->buscar($id);
        $this->conferirToken($requisicao, 'assumir'.$id);

        try {
            $chamado->assumir((string) $this->getUser()?->getUserIdentifier());
            $this->gerenciador->flush();
        } catch (\DomainException $recusa) {
            $this->addFlash('erro', $recusa->getMessage());
        }

        return $this->redirectToRoute('suporte_chamado', ['id' => $id]);
    }

    #[Route('/chamados/{id}/triagem', name: 'suporte_triagem', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function triagem(int $id, Request $requisicao): Response
    {
        $chamado = $this->buscar($id);
        $this->conferirToken($requisicao, 'triagem'.$id);

        $classificacao = Classificacao::tryFrom((string) $requisicao->request->get('classificacao'));

        if (null === $classificacao) {
            $this->addFlash('erro', 'Escolha uma das quatro classificações.');

            return $this->redirectToRoute('suporte_chamado', ['id' => $id]);
        }

        if ($requisicao->request->getBoolean('reproduzido')) {
            $chamado->registrarReproducao($requisicao->request->getBoolean('ambiente_limpo'));
        }

        $problema = null;
        $codigo = trim((string) $requisicao->request->get('problema'));
        if ('' !== $codigo) {
            $problema = $this->problemas->porCodigo($codigo);
        }

        try {
            $chamado->classificar($classificacao, $problema);
            $this->gerenciador->flush();
            $this->addFlash('aviso', 'Chamado classificado como '.$classificacao->rotulo().'.');
        } catch (\DomainException $recusa) {
            $this->addFlash('erro', $recusa->getMessage());
        }

        return $this->redirectToRoute('suporte_chamado', ['id' => $id]);
    }

    #[Route('/chamados/{id}/anotar', name: 'suporte_anotar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function anotar(int $id, Request $requisicao): Response
    {
        $chamado = $this->buscar($id);
        $this->conferirToken($requisicao, 'anotar'.$id);

        $texto = trim((string) $requisicao->request->get('texto'));

        if ('' !== $texto) {
            $chamado->anotar(
                (string) $this->getUser()?->getUserIdentifier(),
                mb_substr($texto, 0, 5000),
                $requisicao->request->getBoolean('visivel'),
            );
            $this->gerenciador->flush();
        }

        return $this->redirectToRoute('suporte_chamado', ['id' => $id]);
    }

    #[Route('/chamados/{id}/resolver', name: 'suporte_resolver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resolver(int $id, Request $requisicao): Response
    {
        $chamado = $this->buscar($id);
        $this->conferirToken($requisicao, 'resolver'.$id);

        try {
            $chamado->resolver();
            $this->gerenciador->flush();
            $this->addFlash('aviso', 'Chamado resolvido.');
        } catch (\DomainException $recusa) {
            $this->addFlash('erro', $recusa->getMessage());
        }

        return $this->redirectToRoute('suporte_chamado', ['id' => $id]);
    }

    #[Route('/problemas', name: 'suporte_problemas', methods: ['GET'])]
    public function problemas(Request $requisicao): Response
    {
        $termo = trim((string) $requisicao->query->get('q', ''));

        return $this->render('suporte/problemas.html.twig', [
            'problemas' => '' !== $termo ? $this->problemas->procurar($termo, 30) : $this->problemas->findAll(),
            'termo' => $termo,
        ]);
    }

    #[Route('/publicacoes', name: 'suporte_publicacoes', methods: ['GET'])]
    public function publicacoes(): Response
    {
        return $this->render('suporte/publicacoes.html.twig', [
            'publicacoes' => $this->publicacoes->findBy([], ['iniciadaEm' => 'DESC'], 30),
        ]);
    }

    private function buscar(int $id): Chamado
    {
        $chamado = $this->chamados->find($id);

        if (null === $chamado) {
            throw $this->createNotFoundException('Chamado não encontrado.');
        }

        $this->denyAccessUnlessGranted(VotanteDeChamado::VER, $chamado);

        return $chamado;
    }

    /**
     * Sem o token, um link numa aba aberta em outro lugar conseguiria mudar o
     * chamado no lugar de quem está autenticado aqui.
     */
    private function conferirToken(Request $requisicao, string $identificador): void
    {
        if (!$this->isCsrfTokenValid($identificador, (string) $requisicao->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token de formulário inválido.');
        }
    }

    /**
     * @return list<\App\Dominio\Suporte\ProblemaConhecido>
     */
    private function parecidos(Chamado $chamado): array
    {
        return array_values(array_filter(
            $this->problemas->procurar($chamado->titulo(), 5),
            static fn ($problema) => $problema->afeta($chamado->versaoDoPortal()),
        ));
    }
}
