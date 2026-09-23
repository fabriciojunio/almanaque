<?php

declare(strict_types=1);

namespace App\Infraestrutura\Busca;

use App\Dominio\Busca\Consulta;
use App\Dominio\Busca\ItemEncontrado;
use App\Dominio\Busca\MotorDeBusca;
use App\Dominio\Busca\Resultado;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;

/**
 * A busca de verdade.
 *
 * Três coisas que o banco não faz e que aqui saem de graça: relevância com
 * peso maior no título, tolerância a erro de digitação e a contagem por
 * categoria calculada junto com o resultado, numa requisição só.
 */
final readonly class BuscaElasticsearch implements MotorDeBusca
{
    public function __construct(
        private Client $cliente,
        private string $indice,
        private LoggerInterface $log,
    ) {
    }

    public function buscar(Consulta $consulta): Resultado
    {
        $resposta = $this->cliente->search([
            'index' => $this->indice,
            'body' => [
                'from' => $consulta->deslocamento(),
                'size' => $consulta->tamanhoDaPagina,
                'query' => $this->montarConsulta($consulta),
                'sort' => $this->montarOrdenacao($consulta),
                'aggs' => [
                    'categorias' => [
                        'terms' => ['field' => 'categoria.chave', 'size' => 30],
                    ],
                ],
            ],
        ])->asArray();

        return new Resultado(
            itens: $this->lerItens($resposta),
            total: (int) ($resposta['hits']['total']['value'] ?? 0),
            facetasPorCategoria: $this->lerFacetas($resposta),
            motor: $this->nome(),
        );
    }

    public function nome(): string
    {
        return 'elasticsearch';
    }

    public function estaDisponivel(): bool
    {
        try {
            return $this->cliente->ping()->asBool();
        } catch (\Throwable $falha) {
            $this->log->warning('Elasticsearch não respondeu ao ping.', [
                'erro' => $falha->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function montarConsulta(Consulta $consulta): array
    {
        // O filtro por portal é obrigatório e vem primeiro: um índice só
        // guarda os anúncios de todos os clientes, e esquecer este filtro
        // mostra o anúncio de um no guia do outro.
        $filtros = [
            ['term' => ['portal' => $consulta->portal->apelido()]],
        ];

        if (null !== $consulta->categoria) {
            $filtros[] = ['term' => ['categoria.chave' => $consulta->categoria]];
        }

        if (null !== $consulta->cidade && '' !== trim($consulta->cidade)) {
            $filtros[] = ['term' => ['cidade.chave' => $consulta->cidade]];
        }

        if ($consulta->somenteDestaques) {
            $filtros[] = ['term' => ['destaque' => true]];
        }

        if (!$consulta->temTermo()) {
            return ['bool' => ['filter' => $filtros]];
        }

        return [
            'bool' => [
                'filter' => $filtros,
                'must' => [[
                    'multi_match' => [
                        'query' => $consulta->termoLimpo(),
                        // O título pesa três vezes mais que a descrição: quem
                        // procura "padaria" quer a padaria, não a loja que
                        // menciona padaria no texto.
                        'fields' => ['titulo^3', 'categoria.nome^2', 'descricao'],
                        'fuzziness' => 'AUTO',
                        'operator' => 'and',
                    ],
                ]],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function montarOrdenacao(Consulta $consulta): array
    {
        // Sem termo, o guia ordena por destaque e data. Com termo, a
        // relevância manda, e o destaque só desempata: um anúncio em destaque
        // que não casa com a busca não pode aparecer na frente do que casa.
        if (!$consulta->temTermo()) {
            return [
                ['destaque' => 'desc'],
                ['publicado_em' => 'desc'],
            ];
        }

        return [
            '_score',
            ['destaque' => 'desc'],
        ];
    }

    /**
     * @param array<string, mixed> $resposta
     *
     * @return list<ItemEncontrado>
     */
    private function lerItens(array $resposta): array
    {
        $itens = [];

        foreach ($resposta['hits']['hits'] ?? [] as $achado) {
            $fonte = $achado['_source'];

            $itens[] = new ItemEncontrado(
                id: (int) $fonte['id'],
                titulo: $fonte['titulo'],
                apelido: $fonte['apelido'],
                resumo: mb_substr((string) $fonte['descricao'], 0, 180),
                categoria: $fonte['categoria']['nome'],
                cidade: $fonte['cidade']['nome'] ?? null,
                telefone: $fonte['telefone'] ?? null,
                destaque: (bool) $fonte['destaque'],
                imagem: $fonte['imagem'] ?? null,
            );
        }

        return $itens;
    }

    /**
     * @param array<string, mixed> $resposta
     *
     * @return array<string, int>
     */
    private function lerFacetas(array $resposta): array
    {
        $facetas = [];

        foreach ($resposta['aggregations']['categorias']['buckets'] ?? [] as $balde) {
            $facetas[(string) $balde['key']] = (int) $balde['doc_count'];
        }

        return $facetas;
    }
}
