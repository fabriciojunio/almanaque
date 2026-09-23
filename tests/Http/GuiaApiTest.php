<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\CasoDeTesteHttp;
use PHPUnit\Framework\Attributes\Test;

class GuiaApiTest extends CasoDeTesteHttp
{
    #[Test]
    public function a_sonda_de_saude_responde_sem_autenticacao(): void
    {
        $this->chamar('GET', '/api/saude');

        self::assertResponseIsSuccessful();
        $corpo = $this->json();
        self::assertSame('no ar', $corpo['status']);
        self::assertTrue($corpo['dependencias']['banco']);
    }

    #[Test]
    public function a_sonda_nao_conta_detalhe_de_infraestrutura(): void
    {
        $this->chamar('GET', '/api/saude');

        self::assertSame(
            ['status', 'versao', 'dependencias'],
            array_keys($this->json()),
        );
    }

    #[Test]
    public function toda_resposta_leva_o_identificador_de_requisicao(): void
    {
        $this->chamar('GET', '/api/saude');

        $identificador = $this->navegador->getResponse()->headers->get('X-Request-Id');
        self::assertNotEmpty($identificador);
        self::assertMatchesRegularExpression('/^req-[a-f0-9]{12}$/', (string) $identificador);
    }

    #[Test]
    public function o_identificador_que_o_cliente_manda_e_preservado(): void
    {
        $this->navegador->request('GET', '/api/saude', server: ['HTTP_X_REQUEST_ID' => 'chamado-48210']);

        self::assertSame('chamado-48210', $this->navegador->getResponse()->headers->get('X-Request-Id'));
    }

    #[Test]
    public function identificador_com_formato_estranho_e_trocado(): void
    {
        $this->navegador->request('GET', '/api/saude', server: [
            'HTTP_X_REQUEST_ID' => '<script>alert(1)</script>',
        ]);

        $identificador = (string) $this->navegador->getResponse()->headers->get('X-Request-Id');
        self::assertStringNotContainsString('<script>', $identificador);
    }

    #[Test]
    public function toda_resposta_leva_os_cabecalhos_de_seguranca(): void
    {
        $this->chamar('GET', '/api/saude');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertStringContainsString(
            "frame-ancestors 'none'",
            (string) $this->navegador->getResponse()->headers->get('Content-Security-Policy'),
        );
    }

    #[Test]
    public function o_guia_publico_lista_anuncios_sem_token(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios');

        self::assertResponseIsSuccessful();
        $corpo = $this->json();
        self::assertSame(8, $corpo['total']);
        self::assertCount(8, $corpo['dados']);
        self::assertSame('banco', $corpo['busca']['motor'], 'sem Elasticsearch, a busca é a do banco');
    }

    #[Test]
    public function guia_que_nao_existe_responde_404(): void
    {
        $this->chamar('GET', '/api/g/inexistente/anuncios');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function a_busca_por_termo_filtra(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios?q=padaria');

        $corpo = $this->json();
        self::assertNotEmpty($corpo['dados']);
        self::assertStringContainsStringIgnoringCase('padaria', $corpo['dados'][0]['titulo']);
    }

    #[Test]
    public function a_busca_aceita_filtro_por_categoria(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios?categoria=automotivo');

        $corpo = $this->json();
        self::assertCount(2, $corpo['dados']);
        foreach ($corpo['dados'] as $anuncio) {
            self::assertSame('Automotivo', $anuncio['categoria']);
        }
    }

    #[Test]
    public function um_guia_nao_enxerga_o_anuncio_do_outro(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios?q=Padaria Estrela');
        $doBauru = $this->json()['dados'];
        self::assertCount(1, $doBauru);

        // O mesmo título existe nos dois portais dos dados de exemplo. Se o
        // filtro de inquilino vazar, este total muda.
        $this->chamar('GET', '/api/g/vale/anuncios?q=Padaria Estrela');
        self::assertCount(1, $this->json()['dados']);
    }

    #[Test]
    public function o_detalhe_do_anuncio_traz_contato_e_local(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios/padaria-estrela');

        self::assertResponseIsSuccessful();
        $corpo = $this->json();
        self::assertSame('Padaria Estrela', $corpo['titulo']);
        self::assertSame('(14) 3234-1010', $corpo['contato']['telefone']);
        self::assertNotNull($corpo['local']['latitude']);
    }

    #[Test]
    public function anuncio_de_um_portal_nao_abre_pela_url_do_outro(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios/padaria-estrela');
        self::assertResponseIsSuccessful();

        $this->chamar('GET', '/api/g/vale/anuncios/padaria-estrela');
        self::assertResponseIsSuccessful();

        // O mesmo apelido existe nos dois, e cada um devolve o seu: a chave
        // única é (portal, apelido), não o apelido sozinho.
        self::assertSame('Jaú', $this->json()['contato']['cidade']);
    }

    #[Test]
    public function as_categorias_vem_em_arvore_com_a_contagem(): void
    {
        $this->chamar('GET', '/api/g/bauru/categorias');

        $dados = $this->json()['dados'];
        self::assertCount(4, $dados);

        $alimentacao = array_values(array_filter(
            $dados,
            static fn (array $c) => 'alimentacao' === $c['apelido'],
        ))[0];

        self::assertCount(2, $alimentacao['filhas']);
        self::assertSame(1, $alimentacao['anuncios'], 'a sorveteria está na raiz');
    }

    #[Test]
    public function pagina_alem_do_fim_devolve_lista_vazia_e_nao_erro(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios?pagina=99');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['dados']);
    }

    #[Test]
    public function tamanho_de_pagina_absurdo_e_cortado_no_teto(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios?por_pagina=5000');

        self::assertResponseIsSuccessful();
        self::assertSame(50, $this->json()['por_pagina']);
    }

    #[Test]
    public function termo_com_curinga_de_like_nao_vira_busca_por_tudo(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios?q=%');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['dados'], 'o curinga é escapado, então não casa com nada');
    }
}
