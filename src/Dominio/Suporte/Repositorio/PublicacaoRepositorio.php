<?php

declare(strict_types=1);

namespace App\Dominio\Suporte\Repositorio;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Publicacao;
use App\Dominio\Suporte\SituacaoPublicacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publicacao>
 */
class PublicacaoRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Publicacao::class);
    }

    /** @return list<Publicacao> */
    public function doPortal(Portal $portal, int $limite = 20): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.portal = :portal')
            ->setParameter('portal', $portal)
            ->orderBy('p.iniciadaEm', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Publicacao> */
    public function emAndamento(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.situacao = :andamento')
            ->setParameter('andamento', SituacaoPublicacao::EM_ANDAMENTO)
            ->orderBy('p.iniciadaEm', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function salvar(Publicacao $publicacao): void
    {
        $this->getEntityManager()->persist($publicacao);
        $this->getEntityManager()->flush();
    }
}
