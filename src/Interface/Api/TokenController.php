<?php

declare(strict_types=1);

namespace App\Interface\Api;

use App\Dominio\Identidade\Papel;
use App\Dominio\Identidade\Repositorio\TokenAcessoRepositorio;
use App\Dominio\Identidade\Repositorio\UsuarioRepositorio;
use App\Dominio\Identidade\TokenAcesso;
use App\Dominio\Identidade\Usuario;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Troca e-mail e senha por um token de API.
 *
 * Três cuidados aqui, e os três existem porque esta é a porta que mais leva
 * tentativa automatizada:
 *
 * A resposta de erro é sempre a mesma, com o mesmo texto e o mesmo código,
 * para e-mail que não existe, senha errada e conta desativada. Diferenciar é
 * entregar de graça a lista de quem tem conta.
 *
 * O mesmo custo de hash é pago quando o e-mail não existe. Sem isso, a
 * diferença de tempo pode revelar o que a resposta de erro não informa.
 *
 * Cinco tentativas por minuto e por endereço, contadas pelo limitador.
 */
final class TokenController extends AbstractController
{
    public function __construct(
        private readonly UsuarioRepositorio $usuarios,
        private readonly TokenAcessoRepositorio $tokens,
        private readonly UserPasswordHasherInterface $cifrador,
        private readonly RateLimiterFactoryInterface $limitadorDeLogin,
        private readonly LoggerInterface $log,
    ) {
    }

    #[Route('/rest/tokens', name: 'api_token_criar', methods: ['POST'])]
    public function criar(Request $requisicao): JsonResponse
    {
        if (!$this->limitadorDeLogin->create($requisicao->getClientIp())->consume()->isAccepted()) {
            return $this->json([
                'erro' => 'Muitas tentativas. Espere um minuto e tente de novo.',
            ], 429);
        }

        $corpo = json_decode($requisicao->getContent(), true);

        if (!is_array($corpo) || !isset($corpo['email'], $corpo['senha'])
            || !is_string($corpo['email']) || !is_string($corpo['senha'])
            || (isset($corpo['descricao']) && !is_string($corpo['descricao']))) {
            return $this->json(['erro' => 'Informe e-mail e senha.'], 400);
        }

        if (strlen($corpo['senha']) > PasswordHasherInterface::MAX_PASSWORD_LENGTH) {
            return $this->json(['erro' => 'Senha acima do tamanho permitido.'], 400);
        }

        $usuario = $this->usuarios->porEmail((string) $corpo['email']);

        if (null === $usuario) {
            // Usa o algoritmo e o custo configurados também para conta ausente.
            $ficticio = new Usuario('ausente@almanaque.invalid', 'Conta inexistente', Papel::SUPORTE);
            $this->cifrador->hashPassword($ficticio, $corpo['senha']);

            return $this->recusar((string) $corpo['email'], 'e-mail não cadastrado');
        }

        if (!$this->cifrador->isPasswordValid($usuario, (string) $corpo['senha'])) {
            return $this->recusar((string) $corpo['email'], 'senha incorreta');
        }

        if (!$usuario->estaAtivo()) {
            return $this->recusar((string) $corpo['email'], 'conta desativada');
        }

        [$token, $segredo] = TokenAcesso::gerar(
            $usuario,
            mb_substr((string) ($corpo['descricao'] ?? 'API'), 0, 80),
        );

        $usuario->registrarAcesso();
        $this->tokens->salvar($token);

        return $this->json([
            'token' => $segredo,
            'expira_em' => $token->expiraEm()->format(\DateTimeInterface::ATOM),
            'usuario' => [
                'nome' => $usuario->nome(),
                'papel' => $usuario->papel()->value,
                'portal' => $usuario->portal()?->apelido(),
            ],
        ], 201);
    }

    private function recusar(string $email, string $motivoReal): JsonResponse
    {
        // O motivo de verdade vai para o log, nunca para a resposta.
        $this->log->notice('Tentativa de autenticação recusada.', [
            'email' => $email,
            'motivo' => $motivoReal,
        ]);

        return $this->json(['erro' => 'E-mail ou senha inválidos.'], 401);
    }
}
