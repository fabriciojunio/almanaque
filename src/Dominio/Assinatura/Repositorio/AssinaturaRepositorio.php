<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura\Repositorio;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Assinatura\Assinatura;
use App\Dominio\Assinatura\SituacaoAssinatura;
use App\Dominio\Portal\Portal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assinatura>
 */
class AssinaturaRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Assinatura::class);
    }

    public function doAnuncio(Anuncio $anuncio): ?Assinatura
    {
        return $this->findOneBy(['anuncio' => $anuncio]);
    }

    /**
     * As assinaturas que a rotina de cobrança precisa olhar hoje.
     *
     * O limite existe para a rotina processar em lotes: carregar dez mil
     * assinaturas na memória para cobrar é como a rotina quebra quando a base
     * cresce.
     *
     * @return list<Assinatura>
     */
    public function vencidasAte(\DateTimeImmutable $data, int $limite = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.situacao != :cancelada')
            ->andWhere('a.proximaCobrancaEm <= :data')
            ->setParameter('cancelada', SituacaoAssinatura::CANCELADA)
            ->setParameter('data', $data)
            ->orderBy('a.proximaCobrancaEm', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Assinatura> */
    public function inadimplentesDoPortal(Portal $portal): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.anuncio', 'an')
            ->andWhere('an.portal = :portal')
            ->andWhere('a.situacao = :inadimplente')
            ->setParameter('portal', $portal)
            ->setParameter('inadimplente', SituacaoAssinatura::INADIMPLENTE)
            ->getQuery()
            ->getResult();
    }

    public function salvar(Assinatura $assinatura): void
    {
        $this->getEntityManager()->persist($assinatura);
        $this->getEntityManager()->flush();
    }
}
