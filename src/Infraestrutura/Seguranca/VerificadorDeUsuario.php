<?php

declare(strict_types=1);

namespace App\Infraestrutura\Seguranca;

use App\Dominio\Identidade\Usuario;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class VerificadorDeUsuario implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if ($user instanceof Usuario && !$user->estaAtivo()) {
            throw new DisabledException('Conta inativa.');
        }
    }
}
