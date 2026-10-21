<?php

declare(strict_types=1);

namespace App\Infraestrutura\Seguranca;

use App\Dominio\Identidade\Papel;
use App\Dominio\Identidade\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, null>
 */
final class VotanteDeSuporte extends Voter
{
    public const ACESSAR = 'ACESSAR_SUPORTE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACESSAR === $attribute && null === $subject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $usuario = $token->getUser();

        return $usuario instanceof Usuario
            && $usuario->estaAtivo()
            && in_array($usuario->papel(), [Papel::SUPORTE, Papel::ADMINISTRADOR], true);
    }
}
