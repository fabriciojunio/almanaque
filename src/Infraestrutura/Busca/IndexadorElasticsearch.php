<?php

declare(strict_types=1);

namespace App\Infraestrutura\Busca;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Busca\Indexador;
use App\Dominio\Portal\Portal;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;

/**
 * Mantém o índice em dia.
 *
 * O analisador é o que faz a busca em português funcionar: "açaí" e "acai"
 * caem no mesmo termo, e "padarias" encontra "padaria". Sem isso, o
 * Elasticsearch acerta menos que o LIKE do banco em palavra acentuada, que é
 * a maioria delas aqui.
 */
final readonly class IndexadorElasticsearch implements Indexador
{
    public function __construct(
        private Client $cliente,
        private string $indice,
        private LoggerInterface $log,
    ) {
    }

    public function criarIndice(): void
    {
        if ($this->cliente->indices()->exists(['index' => $this->indice])->asBool()) {
            return;
        }

        $this->cliente->indices()->create([
            'index' => $this->indice,
            'body' => [
                'settings' => [
                    'analysis' => [
                        'filter' => [
                            'radical_pt' => ['type' => 'stemmer', 'language' => 'brazilian'],
                            'sem_acento' => ['type' => 'asciifolding', 'preserve_original' => true],
                        ],
                        'analyzer' => [
                            'portugues' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'sem_acento', 'radical_pt'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'portal' => ['type' => 'keyword'],
                        'titulo' => ['type' => 'text', 'analyzer' => 'portugues'],
                        'apelido' => ['type' => 'keyword'],
                        'descricao' => ['type' => 'text', 'analyzer' => 'portugues'],
                        'categoria' => [
                            'properties' => [
                                'chave' => ['type' => 'keyword'],
                                'nome' => ['type' => 'text', 'analyzer' => 'portugues'],
                            ],
                        ],
                        'cidade' => [
                            'properties' => [
                                'chave' => ['type' => 'keyword'],
                                'nome' => ['type' => 'keyword'],
                            ],
                        ],
                        'telefone' => ['type' => 'keyword', 'index' => false],
                        'imagem' => ['type' => 'keyword', 'index' => false],
                        'destaque' => ['type' => 'boolean'],
                        'publicado_em' => ['type' => 'date'],
                        'local' => ['type' => 'geo_point'],
                    ],
                ],
            ],
        ]);

        $this->log->info('Índice de busca criado.', ['indice' => $this->indice]);
    }

    public function indexar(Anuncio $anuncio): void
    {
        // Anúncio fora do guia sai do índice. Deixar rascunho indexado é como
        // o cliente descobre, pela busca, o anúncio que ainda não publicou.
        if (!$anuncio->estaNoGuia()) {
            $this->remover($anuncio);

            return;
        }

        $this->cliente->index([
            'index' => $this->indice,
            'id' => (string) $anuncio->id(),
            'body' => $this->documento($anuncio),
        ]);
    }

    public function remover(Anuncio $anuncio): void
    {
        try {
            $this->cliente->delete([
                'index' => $this->indice,
                'id' => (string) $anuncio->id(),
            ]);
        } catch (\Throwable $falha) {
            // Remover o que não está lá é o caso normal, não erro.
            $this->log->debug('Nada a remover do índice.', [
                'anuncio' => $anuncio->id(),
                'erro' => $falha->getMessage(),
            ]);
        }
    }

    public function reindexar(iterable $anuncios): int
    {
        $this->criarIndice();

        $operacoes = [];
        $quantidade = 0;

        foreach ($anuncios as $anuncio) {
            if (!$anuncio->estaNoGuia()) {
                continue;
            }

            $operacoes[] = ['index' => ['_index' => $this->indice, '_id' => (string) $anuncio->id()]];
            $operacoes[] = $this->documento($anuncio);
            ++$quantidade;

            // Lotes de 500 documentos: mandar a base inteira numa requisição
            // é como a reindexação estoura a memória e o tempo limite.
            if (1000 === count($operacoes)) {
                $this->cliente->bulk(['body' => $operacoes]);
                $operacoes = [];
            }
        }

        if ([] !== $operacoes) {
            $this->cliente->bulk(['body' => $operacoes]);
        }

        $this->cliente->indices()->refresh(['index' => $this->indice]);

        return $quantidade;
    }

    public function apagarIndiceDoPortal(Portal $portal): void
    {
        $this->cliente->deleteByQuery([
            'index' => $this->indice,
            'body' => ['query' => ['term' => ['portal' => $portal->apelido()]]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function documento(Anuncio $anuncio): array
    {
        $documento = [
            'id' => $anuncio->id(),
            'portal' => $anuncio->portal()->apelido(),
            'titulo' => $anuncio->titulo(),
            'apelido' => $anuncio->apelido(),
            'descricao' => $anuncio->descricao(),
            'categoria' => [
                'chave' => $anuncio->categoria()->apelido(),
                'nome' => $anuncio->categoria()->nome(),
            ],
            'telefone' => $anuncio->telefone(),
            'imagem' => $anuncio->imagens()[0] ?? null,
            'destaque' => $anuncio->ehDestaque(),
            'publicado_em' => $anuncio->publicadoEm()?->format(\DateTimeInterface::ATOM),
        ];

        if (null !== $anuncio->cidade()) {
            $documento['cidade'] = [
                'chave' => $anuncio->cidade(),
                'nome' => $anuncio->cidade(),
            ];
        }

        if (null !== $anuncio->latitude() && null !== $anuncio->longitude()) {
            $documento['local'] = [
                'lat' => $anuncio->latitude(),
                'lon' => $anuncio->longitude(),
            ];
        }

        return $documento;
    }
}
