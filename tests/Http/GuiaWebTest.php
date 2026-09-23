<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Dominio\Identidade\Repositorio\UsuarioRepositorio;
use App\Dominio\Identidade\Usuario;
use App\Tests\CasoDeTesteHttp;
use PHPUnit\Framework\Attributes\Test;

class GuiaWebTest extends CasoDeTesteHttp
{
    #[Test]
    public function a_capa_lista_os_guias_publicados(): void
    {
        $this->navegador->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'O guia da sua cidade');
        self::assertSelectorTextContains('body', 'Guia de Bauru');
        self::assertSelectorTextContains('body', 'Vale Negócios');
    }

    #[Test]
    public function a_capa_do_guia_mostra_categorias_e_destaques(): void
    {
        $this->navegador->request('GET', '/g/bauru');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Guia de Bauru');
        self::assertSelectorTextContains('.indice', 'Alimentação');
        self::assertSelectorTextContains('.lista-anuncios', 'Padaria Estrela');
    }

    #[Test]
    public function guia_que_nao_existe_devolve_404(): void
    {
        $this->navegador->request('GET', '/g/nao-existe');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function a_busca_mostra_o_que_encontrou_e_quem_respondeu(): void
    {
        $this->navegador->request('GET', '/g/bauru/busca?q=padaria');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Resultados para');
        self::assertSelectorTextContains('.lista-anuncios', 'Padaria Estrela');
        self::assertSelectorTextContains('body', 'busca respondida por banco');
    }

    #[Test]
    public function busca_sem_resultado_explica_em_vez_de_mostrar_lista_vazia(): void
    {
        $this->navegador->request('GET', '/g/bauru/busca?q=nave espacial');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.vazio', 'Nada encontrado');
    }

    #[Test]
    public function o_filtro_por_categoria_marca_a_categoria_escolhida(): void
    {
        $navegacao = $this->navegador->request('GET', '/g/bauru/busca?categoria=automotivo');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Automotivo', $navegacao->filter('.indice .marcado a')->text());
        self::assertCount(2, $navegacao->filter('.lista-anuncios .anuncio'));
    }

    #[Test]
    public function a_ficha_do_anuncio_traz_contato_e_categoria(): void
    {
        $this->navegador->request('GET', '/g/bauru/padaria-estrela');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Padaria Estrela');
        self::assertSelectorTextContains('.ficha-dados', '(14) 3234-1010');
        self::assertSelectorTextContains('.chamada .etiqueta', 'Padarias');
    }

    #[Test]
    public function anuncio_de_outro_portal_nao_abre_pelo_endereco_deste(): void
    {
        $this->navegador->request('GET', '/g/bauru/padaria-estrela');
        self::assertResponseIsSuccessful();

        // O apelido existe nos dois portais, e cada endereço devolve o seu.
        $this->navegador->request('GET', '/g/vale/padaria-estrela');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ficha-dados', 'Jaú');
    }

    #[Test]
    public function a_pagina_declara_a_politica_de_conteudo(): void
    {
        $this->navegador->request('GET', '/');

        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertStringContainsString(
            "default-src 'self'",
            (string) $this->navegador->getResponse()->headers->get('Content-Security-Policy'),
        );
    }

    #[Test]
    public function o_console_de_suporte_pede_login(): void
    {
        $this->navegador->request('GET', '/suporte');

        self::assertResponseRedirects('/entrar');
    }

    #[Test]
    public function quem_nao_e_do_suporte_nao_entra_no_console(): void
    {
        $this->entrarComo($this->usuario('dono@guiadebauru.com.br'));

        $this->navegador->request('GET', '/suporte');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function o_suporte_ve_a_fila_ordenada_por_impacto(): void
    {
        $this->entrarComo($this->usuario('suporte@almanaque.com.br'));

        $navegacao = $this->navegador->request('GET', '/suporte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Fila do suporte');

        $primeira = $navegacao->filter('.tabela tbody tr')->first();
        self::assertStringContainsString('fora do ar', $primeira->text(), 'o crítico vem primeiro');
    }

    #[Test]
    public function a_tela_do_chamado_traz_a_triagem_em_quatro_caixas(): void
    {
        $this->entrarComo($this->usuario('suporte@almanaque.com.br'));

        $navegacao = $this->navegador->request('GET', '/suporte');
        $link = $navegacao->filter('.tabela tbody tr a')->first()->link();
        $chamado = $this->navegador->click($link);

        self::assertResponseIsSuccessful();
        self::assertCount(4, $chamado->filter('input[name="classificacao"]'));
        self::assertSelectorExists('input[name="problema"]');
    }

    #[Test]
    public function a_lista_de_problemas_conhecidos_procura_pelo_sintoma(): void
    {
        $this->entrarComo($this->usuario('suporte@almanaque.com.br'));

        $this->navegador->request('GET', '/suporte/problemas?q=não aparece na busca');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tabela', 'PC-001');
    }

    #[Test]
    public function a_publicacao_revertida_aparece_com_o_motivo(): void
    {
        $this->entrarComo($this->usuario('suporte@almanaque.com.br'));

        $this->navegador->request('GET', '/suporte/publicacoes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tabela', 'revertida');
        self::assertSelectorTextContains('.tabela', 'busca parou de responder');
    }

    /**
     * O firewall se chama "principal", e não "main": sem dizer o nome, o
     * navegador de teste autentica num contexto que não existe e a sessão
     * simplesmente não vale.
     */
    private function entrarComo(Usuario $usuario): void
    {
        $this->navegador->loginUser($usuario, 'principal');
    }

    private function usuario(string $email): Usuario
    {
        $usuario = static::getContainer()->get(UsuarioRepositorio::class)->porEmail($email);

        if (null === $usuario) {
            self::fail(sprintf('A conta "%s" não está nos dados de exemplo.', $email));
        }

        return $usuario;
    }
}
