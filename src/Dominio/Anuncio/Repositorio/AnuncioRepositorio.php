<?php

declare(strict_types=1);

namespace App\Dominio\Anuncio\Repositorio;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Anuncio\Categoria;
use App\Dominio\Anuncio\SituacaoAnuncio;
use App\Dominio\Portal\Portal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Anuncio>
 */
class AnuncioRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Anuncio::class);
    }

    public function porApelido(Portal $portal, string $apelido): ?Anuncio
    {
        return $this->findOneBy(['portal' => $portal, 'apelido' => $apelido]);
    }

    public function apelidoJaUsado(Portal $portal, string $apelido): bool
    {
        return null !== $this->porApelido($portal, $apelido);
    }

    /**
     * Busca no banco, usada quando não há Elasticsearch no ar.
     *
     * A relevância é pobre de propósito: título vale mais que descrição, e é
     * só isso. Quem quiser relevância de verdade liga o Elasticsearch. O que
     * esta consulta garante é que o guia do cliente não fica sem busca quando
     * o índice cai.
     *
     * @return list<Anuncio>
     */
    public function buscar(
        Portal $portal,
        ?string $termo = null,
        ?Categoria $categoria = null,
        ?string $cidade = null,
        int $limite = 20,
        int $deslocamento = 0,
    ): array {
        $consulta = $this->doGuia($portal)
            ->setMaxResults($limite)
            ->setFirstResult($deslocamento);

        if (null !== $termo && '' !== trim($termo)) {
            // LOWER dos dois lados porque LIKE não quer dizer a mesma coisa em
            // todo banco: no MySQL e no SQLite ele ignora maiúscula por causa
            // da colação padrão, no PostgreSQL não. Sem isto, procurar por
            // "padaria" não acha "Padaria Estrela" em produção, e acha no
            // ambiente de quem programa.
            $consulta
                ->andWhere('LOWER(a.titulo) LIKE :termo OR LOWER(a.descricao) LIKE :termo')
                ->setParameter('termo', '%'.mb_strtolower($this->escapar($termo)).'%');
        }

        if (null !== $categoria) {
            $consulta
                ->andWhere('a.categoria = :categoria OR c.pai = :categoria')
                ->setParameter('categoria', $categoria);
        }

        if (null !== $cidade && '' !== trim($cidade)) {
            $consulta
                ->andWhere('a.cidade = :cidade')
                ->setParameter('cidade', $cidade);
        }

        return $consulta
            ->orderBy('a.destaque', 'DESC')
            ->addOrderBy('a.publicadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function contarNoGuia(Portal $portal): int
    {
        return (int) $this->doGuia($portal)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Anuncio> */
    public function destaquesDoGuia(Portal $portal, int $limite = 6): array
    {
        return $this->doGuia($portal)
            ->andWhere('a.destaque = true')
            ->orderBy('a.publicadoEm', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Contagem por categoria, para a coluna de filtros do guia.
     *
     * @return array<int, int> id da categoria => quantidade
     */
    public function contagemPorCategoria(Portal $portal): array
    {
        $linhas = $this->doGuia($portal)
            ->select('c.id AS categoria, COUNT(a.id) AS quantidade')
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();

        $contagem = [];
        foreach ($linhas as $linha) {
            $contagem[(int) $linha['categoria']] = (int) $linha['quantidade'];
        }

        return $contagem;
    }

    /**
     * Os anúncios publicados cujo prazo já venceu.
     *
     * @return list<Anuncio>
     */
    public function comPrazoVencido(\DateTimeImmutable $agora, int $limite = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.situacao = :publicado')
            ->andWhere('a.expiraEm IS NOT NULL')
            ->andWhere('a.expiraEm <= :agora')
            ->setParameter('publicado', SituacaoAnuncio::PUBLICADO)
            ->setParameter('agora', $agora)
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Anuncio> */
    public function doPortal(Portal $portal, int $limite = 200): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.portal = :portal')
            ->setParameter('portal', $portal)
            ->orderBy('a.atualizadoEm', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function salvar(Anuncio $anuncio): void
    {
        $this->getEntityManager()->persist($anuncio);
        $this->getEntityManager()->flush();
    }

    /**
     * O filtro que não pode faltar em consulta nenhuma do guia: o portal e a
     * situação. Fica aqui, e não espalhado pelos controllers, porque esquecer
     * ele uma vez mostra o anúncio de um cliente no guia de outro.
     */
    private function doGuia(Portal $portal): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->join('a.categoria', 'c')
            ->andWhere('a.portal = :portal')
            ->andWhere('a.situacao = :publicado')
            ->setParameter('portal', $portal)
            ->setParameter('publicado', SituacaoAnuncio::PUBLICADO);
    }

    private function escapar(string $termo): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($termo));
    }
}
