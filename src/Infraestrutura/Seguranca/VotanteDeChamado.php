<?php

declare(strict_types=1);

namespace App\Infraestrutura\Seguranca;

use App\Dominio\Identidade\Usuario;
use App\Dominio\Suporte\Chamado;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Quem enxerga e quem atende um chamado.
 *
 * A separação que importa: o cliente vê o chamado dele, mas nunca a anotação
 * interna, que é onde ficam a consulta que rodou, a linha do log e a
 * hipótese descartada. Atender é só de quem é do suporte.
 *
 * @extends Voter<string, Chamado>
 */
final class VotanteDeChamado extends Voter
{
    public const VER = 'VER_CHAMADO';
    public const ATENDER = 'ATENDER_CHAMADO';
    public const VER_ANOTACAO_INTERNA = 'VER_ANOTACAO_INTERNA';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VER, self::ATENDER, self::VER_ANOTACAO_INTERNA], true)
            && $subject instanceof Chamado;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $usuario = $token->getUser();

        if (!$usuario instanceof Usuario || !$usuario->estaAtivo()) {
            return false;
        }

        $ehDaCasa = $usuario->papel()->atravessaPortais();

        return match ($attribute) {
            self::VER => $ehDaCasa || $usuario->podeVer($subject->portal()),
            self::ATENDER, self::VER_ANOTACAO_INTERNA => $ehDaCasa,
            default => false,
        };
    }
}
