<?php

declare(strict_types=1);

namespace App\Dominio\Portal\Repositorio;

use App\Dominio\Portal\Portal;
use App\Dominio\Portal\SituacaoPortal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Portal>
 */
class PortalRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Portal::class);
    }

    public function porApelido(string $apelido): ?Portal
    {
        return $this->findOneBy(['apelido' => $apelido]);
    }

    public function porDominio(string $dominio): ?Portal
    {
        return $this->findOneBy(['dominio' => $dominio]);
    }

    /** @return list<Portal> */
    public function noAr(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.situacao = :situacao')
            ->setParameter('situacao', SituacaoPortal::ATIVO)
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Portal> */
    public function todos(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function salvar(Portal $portal): void
    {
        $this->getEntityManager()->persist($portal);
        $this->getEntityManager()->flush();
    }
}
