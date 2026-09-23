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
     * Procura pelas palavras do relato, que é como o chamado chega.
     *
     * A procura é palavra por palavra, e não pela frase inteira: ninguém
     * digita o sintoma com as mesmas palavras que estão gravadas. Quem abre o
     * chamado escreve "não aparece na busca", e o registro diz "demora a
     * aparecer no índice". Todas as palavras precisam casar em algum campo,
     * senão qualquer termo comum traria a base inteira.
     *
     * Palavra de até duas letras fica de fora: "na" e "de" casam com tudo.
     *
     * @return list<ProblemaConhecido>
     */
    public function procurar(string $termo, int $limite = 10): array
    {
        $termo = trim($termo);

        if ('' === $termo) {
            return [];
        }

        $consulta = $this->createQueryBuilder('p')
            ->orderBy('p.registradoEm', 'DESC')
            ->setMaxResults($limite);

        // O código é identificador, então casa inteiro e sozinho.
        if (1 === preg_match('/^PC-\d+$/i', $termo)) {
            return $consulta
                ->andWhere('UPPER(p.codigo) = :codigo')
                ->setParameter('codigo', mb_strtoupper($termo))
                ->getQuery()
                ->getResult();
        }

        $palavras = array_filter(
            preg_split('/\s+/', $termo) ?: [],
            static fn (string $palavra) => mb_strlen($palavra) > 2,
        );

        if ([] === $palavras) {
            return [];
        }

        foreach (array_values($palavras) as $posicao => $palavra) {
            $chave = 'palavra'.$posicao;
            $consulta
                ->andWhere(sprintf(
                    '(p.titulo LIKE :%1$s OR p.sintoma LIKE :%1$s OR p.causa LIKE :%1$s)',
                    $chave,
                ))
                ->setParameter($chave, '%'.addcslashes($palavra, '\\%_').'%');
        }

        return $consulta->getQuery()->getResult();
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
