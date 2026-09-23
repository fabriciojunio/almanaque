<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Dominio\Identidade\Repositorio\UsuarioRepositorio;
use App\Tests\CasoDeTesteHttp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A política de conteúdo do Almanaque não libera estilo nem script embutido.
 *
 * Isso corta a forma mais comum de XSS virar execução, e cobra um preço: um
 * style="" esquecido num template não quebra o teste de rota, mas some no
 * navegador, e o layout aparece torto só em produção. Foi o que aconteceu uma
 * vez aqui. Este teste existe para não acontecer de novo.
 */
class PoliticaDeConteudoTest extends CasoDeTesteHttp
{
    /** @return iterable<string, array{string, bool}> */
    public static function paginas(): iterable
    {
        yield 'capa' => ['/', false];
        yield 'guia' => ['/g/bauru', false];
        yield 'busca' => ['/g/bauru/busca?q=padaria', false];
        yield 'anúncio' => ['/g/bauru/padaria-estrela', false];
        yield 'entrar' => ['/entrar', false];
        yield 'fila do suporte' => ['/suporte', true];
        yield 'problemas conhecidos' => ['/suporte/problemas', true];
        yield 'publicações' => ['/suporte/publicacoes', true];
    }

    #[Test]
    #[DataProvider('paginas')]
    public function nenhuma_pagina_usa_estilo_embutido(string $caminho, bool $exigeSuporte): void
    {
        if ($exigeSuporte) {
            $this->entrarComoSuporte();
        }

        $this->navegador->request('GET', $caminho);
        self::assertResponseIsSuccessful();

        $html = (string) $this->navegador->getResponse()->getContent();

        self::assertDoesNotMatchRegularExpression(
            '/<[^>]+\sstyle\s*=/i',
            $html,
            sprintf('%s tem style embutido, que a política de conteúdo bloqueia', $caminho),
        );
    }

    #[Test]
    #[DataProvider('paginas')]
    public function nenhuma_pagina_usa_script_embutido(string $caminho, bool $exigeSuporte): void
    {
        if ($exigeSuporte) {
            $this->entrarComoSuporte();
        }

        $this->navegador->request('GET', $caminho);

        $html = (string) $this->navegador->getResponse()->getContent();

        self::assertDoesNotMatchRegularExpression(
            '/<script(?![^>]*\ssrc=)[^>]*>[^<]/i',
            $html,
            sprintf('%s tem script embutido, que a política de conteúdo bloqueia', $caminho),
        );
    }

    #[Test]
    public function a_politica_declarada_e_a_restritiva(): void
    {
        $this->navegador->request('GET', '/');

        $politica = (string) $this->navegador->getResponse()->headers->get('Content-Security-Policy');

        self::assertStringContainsString("default-src 'self'", $politica);
        self::assertStringNotContainsString('unsafe-inline', $politica);
        self::assertStringNotContainsString('unsafe-eval', $politica);
    }

    private function entrarComoSuporte(): void
    {
        $usuario = static::getContainer()->get(UsuarioRepositorio::class)
            ->porEmail('suporte@almanaque.com.br');

        self::assertNotNull($usuario);

        $this->navegador->loginUser($usuario, 'principal');
    }
}
