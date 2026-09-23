<?php

declare(strict_types=1);

namespace App\Dominio\Identidade\Repositorio;

use App\Dominio\Identidade\TokenAcesso;
use App\Dominio\Identidade\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TokenAcesso>
 */
class TokenAcessoRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, TokenAcesso::class);
    }

    public function porSegredo(string $segredo): ?TokenAcesso
    {
        return $this->findOneBy(['hash' => TokenAcesso::cifrar($segredo)]);
    }

    /** @return list<TokenAcesso> */
    public function doUsuario(Usuario $usuario): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.usuario = :usuario')
            ->andWhere('t.revogadoEm IS NULL')
            ->setParameter('usuario', $usuario)
            ->orderBy('t.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function salvar(TokenAcesso $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }
}
