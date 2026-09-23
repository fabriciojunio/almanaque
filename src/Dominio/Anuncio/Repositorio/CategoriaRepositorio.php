<?php

declare(strict_types=1);

namespace App\Dominio\Anuncio\Repositorio;

use App\Dominio\Anuncio\Categoria;
use App\Dominio\Portal\Portal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categoria>
 */
class CategoriaRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Categoria::class);
    }

    /** @return list<Categoria> */
    public function doPortal(Portal $portal): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.portal = :portal')
            ->setParameter('portal', $portal)
            ->orderBy('c.ordem', 'ASC')
            ->addOrderBy('c.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Categoria> */
    public function raizesDoPortal(Portal $portal): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.portal = :portal')
            ->andWhere('c.pai IS NULL')
            ->setParameter('portal', $portal)
            ->orderBy('c.ordem', 'ASC')
            ->addOrderBy('c.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function porApelido(Portal $portal, string $apelido): ?Categoria
    {
        return $this->findOneBy(['portal' => $portal, 'apelido' => $apelido]);
    }
}
