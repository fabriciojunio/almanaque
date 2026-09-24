<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\CasoDeTesteHttp;
use PHPUnit\Framework\Attributes\Test;

/**
 * A folha de estilo e as fontes são servidas com validade longa. Sem marca de
 * versão no endereço, quem já tinha visitado o site ficava com a versão antiga
 * até o prazo vencer, e correção publicada não aparecia para quem mais importa:
 * o visitante que volta.
 */
class EnderecoDeEstaticoTest extends CasoDeTesteHttp
{
    #[Test]
    public function a_folha_de_estilo_sai_com_marca_de_versao(): void
    {
        $this->navegador->request('GET', '/');

        $folha = $this->navegador->getCrawler()
            ->filter('link[rel="stylesheet"][href*="almanaque.css"]')
            ->attr('href');

        self::assertMatchesRegularExpression('/almanaque\.css\?v=[0-9a-f]+$/', (string) $folha);
    }

    #[Test]
    public function a_fonte_pre_carregada_tambem_sai_com_marca(): void
    {
        $this->navegador->request('GET', '/');

        $fonte = $this->navegador->getCrawler()
            ->filter('link[rel="preload"][as="font"]')
            ->first()
            ->attr('href');

        self::assertMatchesRegularExpression('/\.woff2\?v=[0-9a-f]+$/', (string) $fonte);
    }
}
