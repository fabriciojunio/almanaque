<?php

declare(strict_types=1);

namespace App\Dominio\Suporte\Repositorio;

use App\Dominio\Suporte\ProblemaConhecido;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProblemaConhecido>
 */
class ProblemaConhecidoRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, ProblemaConhecido::class);
    }

    public function porCodigo(string $codigo): ?ProblemaConhecido
    {
        return $this->findOneBy(['codigo' => $codigo]);
    }

    /**
     * Procura pelo sintoma, que é como o chamado chega.
     *
     * @return list<ProblemaConhecido>
     */
    public function procurar(string $termo, int $limite = 10): array
    {
        $escapado = addcslashes(trim($termo), '\\%_');

        return $this->createQueryBuilder('p')
            ->andWhere('p.sintoma LIKE :termo OR p.titulo LIKE :termo OR p.codigo = :codigo')
            ->setParameter('termo', '%'.$escapado.'%')
            ->setParameter('codigo', trim($termo))
            ->orderBy('p.registradoEm', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** @return list<ProblemaConhecido> */
    public function emAberto(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.corrigidoNaVersao IS NULL')
            ->orderBy('p.registradoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function proximoCodigo(): string
    {
        $quantidade = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('PC-%03d', $quantidade + 1);
    }
}
