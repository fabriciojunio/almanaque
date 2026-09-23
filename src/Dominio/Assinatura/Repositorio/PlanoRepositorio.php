<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura\Repositorio;

use App\Dominio\Assinatura\Plano;
use App\Dominio\Portal\Portal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plano>
 */
class PlanoRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Plano::class);
    }

    /** @return list<Plano> */
    public function ativosDoPortal(Portal $portal): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.portal = :portal')
            ->andWhere('p.ativo = true')
            ->setParameter('portal', $portal)
            ->orderBy('p.precoEmCentavos', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
