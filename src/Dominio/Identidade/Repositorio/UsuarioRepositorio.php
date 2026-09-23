<?php

declare(strict_types=1);

namespace App\Dominio\Identidade\Repositorio;

use App\Dominio\Identidade\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Usuario>
 */
class UsuarioRepositorio extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Usuario::class);
    }

    public function porEmail(string $email): ?Usuario
    {
        return $this->findOneBy(['email' => mb_strtolower($email)]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Usuario) {
            throw new UnsupportedUserException(sprintf('Usuário não suportado: %s', $user::class));
        }

        $user->definirSenha($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
