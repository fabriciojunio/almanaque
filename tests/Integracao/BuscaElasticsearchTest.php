<?php

declare(strict_types=1);

namespace App\Tests\Integracao;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Anuncio\Categoria;
use App\Dominio\Busca\Consulta;
use App\Dominio\Portal\Portal;
use App\Infraestrutura\Busca\BuscaElasticsearch;
use App\Infraestrutura\Busca\IndexadorElasticsearch;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * O motor de verdade, contra um Elasticsearch de verdade.
 *
 * Se pula sozinho quando não há serviço no ar, que é o caso da máquina de
 * quem programa. No CI ele roda, porque lá o serviço sobe junto: sem isso, a
 * parte do sistema que mais depende de configuração externa ficaria sem
 * nenhuma prova de que funciona.
 */
class BuscaElasticsearchTest extends TestCase
{
    private const INDICE = 'almanaque-teste';

    private Client $cliente;
    private IndexadorElasticsearch $indexador;
    private BuscaElasticsearch $busca;
    private Portal $bauru;
    private Portal $vale;

    protected function setUp(): void
    {
        $url = $_SERVER['ELASTICSEARCH_URL'] ?? '';

        if ('' === $url) {
            self::markTestSkipped('Sem ELASTICSEARCH_URL: o motor de reserva é quem responde aqui.');
        }

        $this->cliente = ClientBuilder::create()->setHosts([$url])->build();

        try {
            $this->cliente->ping();
        } catch (\Throwable $falha) {
            self::markTestSkipped('Elasticsearch não respondeu: '.$falha->getMessage());
        }

        $this->indexador = new IndexadorElasticsearch($this->cliente, self::INDICE, new NullLogger());
        $this->busca = new BuscaElasticsearch($this->cliente, self::INDICE, new NullLogger());

        $this->apagarIndice();
        $this->indexador->criarIndice();

        $this->bauru = new Portal('Guia de Bauru', 'bauru', '2.4.0');
        $this->vale = new Portal('Vale Negócios', 'vale', '2.5.0');

        $this->indexador->reindexar($this->anunciosDeExemplo());
    }

    protected function tearDown(): void
    {
        if (isset($this->cliente)) {
            $this->apagarIndice();
        }
    }

    #[Test]
    public function acha_pelo_nome(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru, termo: 'padaria'));

        self::assertSame(1, $resultado->total);
        self::assertSame('Padaria Estrela', $resultado->itens[0]->titulo);
        self::assertSame('elasticsearch', $resultado->motor);
    }

    #[Test]
    public function acha_sem_acento_o_que_foi_escrito_com_acento(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru, termo: 'acai'));

        self::assertSame(1, $resultado->total, '"acai" precisa achar "açaí"');
        self::assertSame('Sorveteria Gelo Bom', $resultado->itens[0]->titulo);
    }

    #[Test]
    public function acha_no_plural_o_que_foi_escrito_no_singular(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru, termo: 'chaves'));

        self::assertGreaterThan(0, $resultado->total, 'o radical de "chaves" e "chave" é o mesmo');
    }

    #[Test]
    public function perdoa_erro_de_digitacao(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru, termo: 'padria'));

        self::assertSame(1, $resultado->total, 'uma letra trocada ainda encontra');
    }

    #[Test]
    public function o_titulo_pesa_mais_que_a_descricao(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru, termo: 'chaveiro'));

        self::assertGreaterThanOrEqual(2, $resultado->total);
        self::assertSame(
            'Chaveiro do Zé',
            $resultado->itens[0]->titulo,
            'quem tem a palavra no título vem antes de quem só menciona no texto',
        );
    }

    #[Test]
    public function um_portal_nao_enxerga_o_anuncio_do_outro(): void
    {
        $doBauru = $this->busca->buscar(new Consulta($this->bauru, termo: 'padaria'));
        $doVale = $this->busca->buscar(new Consulta($this->vale, termo: 'padaria'));

        self::assertSame(1, $doBauru->total);
        self::assertSame(1, $doVale->total);
        self::assertSame('Padaria do Vale', $doVale->itens[0]->titulo);
    }

    #[Test]
    public function traz_a_contagem_por_categoria_na_mesma_requisicao(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru));

        self::assertArrayHasKey('padarias', $resultado->facetasPorCategoria);
        self::assertSame(1, $resultado->facetasPorCategoria['padarias']);
    }

    #[Test]
    public function sem_termo_o_destaque_vem_na_frente(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru));

        self::assertTrue($resultado->itens[0]->destaque);
    }

    #[Test]
    public function anuncio_fora_do_guia_nao_entra_no_indice(): void
    {
        $resultado = $this->busca->buscar(new Consulta($this->bauru, termo: 'rascunho'));

        self::assertSame(0, $resultado->total);
    }

    /** @return list<Anuncio> */
    private function anunciosDeExemplo(): array
    {
        $padarias = new Categoria($this->bauru, 'Padarias', 'padarias');
        $servicos = new Categoria($this->bauru, 'Serviços', 'servicos');
        $sorvetes = new Categoria($this->bauru, 'Sorveterias', 'sorveterias');
        $doVale = new Categoria($this->vale, 'Padarias', 'padarias');

        $prazo = new \DateTimeImmutable('+30 days');

        $estrela = new Anuncio($this->bauru, 'Padaria Estrela', 'padaria-estrela', 'Pão quente o dia inteiro.', $padarias);
        $estrela->publicar($prazo, destaque: true);

        $chaveiro = new Anuncio($this->bauru, 'Chaveiro do Zé', 'chaveiro-do-ze', 'Cópia de chave na hora.', $servicos);
        $chaveiro->publicar($prazo);

        $serralheria = new Anuncio($this->bauru, 'Serralheria Central', 'serralheria-central', 'Portão, grade e serviço de chaveiro.', $servicos);
        $serralheria->publicar($prazo);

        $sorveteria = new Anuncio($this->bauru, 'Sorveteria Gelo Bom', 'sorveteria-gelo-bom', 'Sorvete de massa, açaí e picolé.', $sorvetes);
        $sorveteria->publicar($prazo);

        $rascunho = new Anuncio($this->bauru, 'Loja Rascunho', 'loja-rascunho', 'Ainda não publiquei este rascunho.', $servicos);

        $padariaDoVale = new Anuncio($this->vale, 'Padaria do Vale', 'padaria-do-vale', 'Pão de queijo e café.', $doVale);
        $padariaDoVale->publicar($prazo);

        $anuncios = [$estrela, $chaveiro, $serralheria, $sorveteria, $rascunho, $padariaDoVale];

        // O índice usa o id do anúncio como chave, e aqui nada passou pelo
        // banco: os ids são atribuídos na mão.
        foreach ($anuncios as $posicao => $anuncio) {
            $reflexo = new \ReflectionProperty($anuncio, 'id');
            $reflexo->setValue($anuncio, $posicao + 1);
        }

        return $anuncios;
    }

    private function apagarIndice(): void
    {
        try {
            $this->cliente->indices()->delete(['index' => self::INDICE]);
        } catch (\Throwable) {
            // O índice não existir é o caso normal na primeira volta.
        }
    }
}
