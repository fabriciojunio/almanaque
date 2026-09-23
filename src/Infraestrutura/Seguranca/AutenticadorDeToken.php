<?php

declare(strict_types=1);

namespace App\Infraestrutura\Seguranca;

use App\Dominio\Identidade\Repositorio\TokenAcessoRepositorio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Valida o token que chega no cabeçalho Authorization da API.
 *
 * O banco guarda o hash, nunca o token. A busca é pelo hash, então uma
 * comparação de texto não acontece em lugar nenhum, e um dump do banco não
 * vira acesso.
 *
 * A mensagem de erro é sempre a mesma, para token inexistente, expirado e
 * revogado. Dizer qual dos três é ajudar quem está tentando adivinhar.
 */
final readonly class AutenticadorDeToken implements AccessTokenHandlerInterface
{
    public function __construct(
        private TokenAcessoRepositorio $tokens,
        private EntityManagerInterface $gerenciador,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $token = $this->tokens->porSegredo($accessToken);

        if (null === $token || !$token->estaValido()) {
            throw new BadCredentialsException('Token inválido.');
        }

        $token->registrarUso();
        $this->gerenciador->flush();

        return new UserBadge($token->usuario()->getUserIdentifier());
    }
}
