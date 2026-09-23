<?php

declare(strict_types=1);

namespace App\Tests\Unidade\Anuncio;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Anuncio\AnuncioRecusadoException;
use App\Dominio\Anuncio\Categoria;
use App\Dominio\Anuncio\SituacaoAnuncio;
use App\Dominio\Portal\Portal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AnuncioTest extends TestCase
{
    private Portal $portal;
    private Categoria $categoria;

    protected function setUp(): void
    {
        $this->portal = new Portal('Guia de Bauru', 'bauru', '2.4.0');
        $this->categoria = new Categoria($this->portal, 'Restaurantes', 'restaurantes');
    }

    private function anuncio(): Anuncio
    {
        return new Anuncio(
            $this->portal,
            'Padaria Estrela',
            'padaria-estrela',
            'Pão quente das cinco da manhã às oito da noite.',
            $this->categoria,
        );
    }

    #[Test]
    public function nasce_como_rascunho_e_fora_do_guia(): void
    {
        $anuncio = $this->anuncio();

        $this->assertSame(SituacaoAnuncio::RASCUNHO, $anuncio->situacao());
        $this->assertFalse($anuncio->estaNoGuia());
        $this->assertNull($anuncio->publicadoEm());
    }

    #[Test]
    public function recusa_categoria_de_outro_portal(): void
    {
        $outroPortal = new Portal('Guia do Vale', 'vale', '2.4.0');
        $categoriaDeOutro = new Categoria($outroPortal, 'Restaurantes', 'restaurantes');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A categoria é de outro portal.');

        new Anuncio($this->portal, 'Padaria', 'padaria', 'Descrição', $categoriaDeOutro);
    }

    #[Test]
    public function publicar_coloca_no_guia_com_prazo(): void
    {
        $anuncio = $this->anuncio();
        $prazo = new \DateTimeImmutable('+30 days');

        $anuncio->publicar($prazo);

        $this->assertSame(SituacaoAnuncio::PUBLICADO, $anuncio->situacao());
        $this->assertTrue($anuncio->estaNoGuia());
        $this->assertNotNull($anuncio->publicadoEm());
        $this->assertSame($prazo->format('Y-m-d'), $anuncio->expiraEm()?->format('Y-m-d'));
    }

    #[Test]
    public function publicar_com_prazo_no_passado_nao_faz_sentido(): void
    {
        $anuncio = $this->anuncio();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('O prazo de publicação precisa ser no futuro.');

        $anuncio->publicar(new \DateTimeImmutable('-1 day'));
    }

    #[Test]
    public function republicar_mantem_a_data_da_primeira_publicacao(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+10 days'));
        $primeiraPublicacao = $anuncio->publicadoEm();

        $anuncio->publicar(new \DateTimeImmutable('+40 days'));

        $this->assertEquals($primeiraPublicacao, $anuncio->publicadoEm());
    }

    #[Test]
    public function anuncio_recusado_nao_volta_ao_guia(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->recusar();

        $this->expectException(AnuncioRecusadoException::class);

        $anuncio->publicar(new \DateTimeImmutable('+30 days'));
    }

    #[Test]
    public function expira_quando_o_prazo_vence(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+2 days'));

        $this->assertTrue($anuncio->expirar(new \DateTimeImmutable('+3 days')));
        $this->assertSame(SituacaoAnuncio::EXPIRADO, $anuncio->situacao());
        $this->assertFalse($anuncio->estaNoGuia());
    }

    #[Test]
    public function nao_expira_antes_do_prazo(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+10 days'));

        $this->assertFalse($anuncio->expirar(new \DateTimeImmutable('+1 day')));
        $this->assertSame(SituacaoAnuncio::PUBLICADO, $anuncio->situacao());
    }

    #[Test]
    public function rascunho_nunca_expira(): void
    {
        $this->assertFalse($this->anuncio()->expirar(new \DateTimeImmutable('+100 days')));
    }

    #[Test]
    public function expirar_tira_o_destaque(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+1 day'), destaque: true);
        $this->assertTrue($anuncio->ehDestaque());

        $anuncio->expirar(new \DateTimeImmutable('+2 days'));

        $this->assertFalse($anuncio->ehDestaque());
    }

    #[Test]
    public function retirar_do_ar_nao_espera_o_prazo(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+30 days'), destaque: true);

        $anuncio->retirarDoAr();

        $this->assertSame(SituacaoAnuncio::EXPIRADO, $anuncio->situacao());
        $this->assertFalse($anuncio->ehDestaque());
    }

    #[Test]
    public function avisa_quando_falta_pouco_para_expirar(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+3 days'));

        $this->assertSame(3, $anuncio->vaiExpirarEm());
    }

    #[Test]
    public function nao_avisa_quando_o_prazo_esta_longe(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->publicar(new \DateTimeImmutable('+60 days'));

        $this->assertNull($anuncio->vaiExpirarEm());
    }

    #[Test]
    public function coordenada_fora_do_planeta_e_recusada(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coordenada fora do planeta.');

        $this->anuncio()->situarNoMapa(-95.0, -49.06);
    }

    #[Test]
    public function aceita_coordenada_de_bauru(): void
    {
        $anuncio = $this->anuncio();
        $anuncio->situarNoMapa(-22.3145, -49.0605);

        $this->assertSame(-22.3145, $anuncio->latitude());
        $this->assertSame(-49.0605, $anuncio->longitude());
    }

    #[Test]
    public function alterar_dados_recusa_categoria_de_outro_portal(): void
    {
        $outroPortal = new Portal('Guia do Vale', 'vale', '2.4.0');
        $categoriaDeOutro = new Categoria($outroPortal, 'Bares', 'bares');

        $this->expectException(\InvalidArgumentException::class);

        $this->anuncio()->alterarDados('Outro título', 'Outra descrição', $categoriaDeOutro);
    }

    #[Test]
    public function contar_visualizacao_nao_mexe_na_data_de_atualizacao(): void
    {
        $anuncio = $this->anuncio();
        $antes = $anuncio->atualizadoEm();

        $anuncio->registrarVisualizacao();

        $this->assertSame(1, $anuncio->visualizacoes());
        $this->assertEquals($antes, $anuncio->atualizadoEm());
    }
}
