<?php

declare(strict_types=1);

namespace App\Dominio\Suporte\Repositorio;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\Classificacao;
use App\Dominio\Suporte\SituacaoChamado;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chamado>
 */
class ChamadoRepositorio extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registro)
    {
        parent::__construct($registro, Chamado::class);
    }

    /**
     * A fila de trabalho: o que está aberto, do mais grave para o mais antigo.
     *
     * A ordenação é por peso da prioridade e depois por data de abertura, e
     * não pelo que o cliente escreveu como urgente. Todo chamado chega
     * urgente.
     *
     * @return list<Chamado>
     */
    public function fila(?Portal $portal = null, int $limite = 50): array
    {
        $consulta = $this->createQueryBuilder('c')
            ->andWhere('c.situacao IN (:abertos)')
            ->setParameter('abertos', [
                SituacaoChamado::ABERTO,
                SituacaoChamado::EM_INVESTIGACAO,
                SituacaoChamado::AGUARDANDO_CLIENTE,
            ])
            ->setMaxResults($limite);

        if (null !== $portal) {
            $consulta->andWhere('c.portal = :portal')->setParameter('portal', $portal);
        }

        $chamados = $consulta->getQuery()->getResult();

        usort($chamados, static function (Chamado $a, Chamado $b): int {
            return [$b->prioridade()->peso(), $a->abertoEm()->getTimestamp() * -1]
                <=> [$a->prioridade()->peso(), $b->abertoEm()->getTimestamp() * -1];
        });

        return $chamados;
    }

    /** @return list<Chamado> */
    public function porIdentificadorDeRequisicao(string $identificador): array
    {
        return $this->findBy(['identificadorDeRequisicao' => $identificador]);
    }

    /** @return list<Chamado> */
    public function doPortal(Portal $portal, int $limite = 100): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.portal = :portal')
            ->setParameter('portal', $portal)
            ->orderBy('c.abertoEm', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Quantos chamados caíram em cada classificação.
     *
     * É o número que mostra se o produto está com defeito ou se falta
     * documentação: uma pilha de dúvida de uso na mesma tela é problema de
     * interface, não de suporte.
     *
     * Devolve o enum, e não a chave dele: a chave é identificador em ASCII, e
     * quem escreve na tela é o rótulo, acentuado.
     *
     * @return list<array{classificacao: Classificacao, quantidade: int}>
     */
    public function contagemPorClassificacao(): array
    {
        $linhas = $this->createQueryBuilder('c')
            ->select('c.classificacao AS classificacao, COUNT(c.id) AS quantidade')
            ->andWhere('c.classificacao IS NOT NULL')
            ->groupBy('c.classificacao')
            ->getQuery()
            ->getArrayResult();

        $porChave = [];
        foreach ($linhas as $linha) {
            $chave = $linha['classificacao'] instanceof Classificacao
                ? $linha['classificacao']->value
                : (string) $linha['classificacao'];
            $porChave[$chave] = (int) $linha['quantidade'];
        }

        $contagem = [];
        foreach (Classificacao::cases() as $caso) {
            $contagem[] = [
                'classificacao' => $caso,
                'quantidade' => $porChave[$caso->value] ?? 0,
            ];
        }

        return $contagem;
    }

    public function salvar(Chamado $chamado): void
    {
        $this->getEntityManager()->persist($chamado);
        $this->getEntityManager()->flush();
    }
}
