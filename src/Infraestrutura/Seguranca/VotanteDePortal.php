<?php

declare(strict_types=1);

namespace App\Infraestrutura\Seguranca;

use App\Dominio\Identidade\Papel;
use App\Dominio\Identidade\Usuario;
use App\Dominio\Portal\Portal;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * O isolamento entre inquilinos, no lugar onde ele não escapa.
 *
 * O filtro por portal já existe nos repositórios, mas repositório serve para
 * montar consulta, não para decidir permissão. Quando alguém pede um recurso
 * pelo identificador, é aqui que a pergunta "este portal é seu?" é
 * respondida, e a resposta padrão é não.
 *
 * @extends Voter<string, Portal>
 */
final class VotanteDePortal extends Voter
{
    public const VER = 'VER_PORTAL';
    public const ADMINISTRAR = 'ADMINISTRAR_PORTAL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VER, self::ADMINISTRAR], true)
            && $subject instanceof Portal;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $usuario = $token->getUser();

        if (!$usuario instanceof Usuario || !$usuario->estaAtivo()) {
            return false;
        }

        if ($usuario->papel()->atravessaPortais()) {
            return true;
        }

        if (!$usuario->podeVer($subject)) {
            return false;
        }

        return match ($attribute) {
            self::VER => true,
            self::ADMINISTRAR => Papel::DONO_DO_PORTAL === $usuario->papel(),
            default => false,
        };
    }
}
