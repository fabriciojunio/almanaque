<?php

declare(strict_types=1);

namespace App\Tests\Unidade\Assinatura;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Anuncio\Categoria;
use App\Dominio\Anuncio\SituacaoAnuncio;
use App\Dominio\Assinatura\Assinatura;
use App\Dominio\Assinatura\AssinaturaCanceladaException;
use App\Dominio\Assinatura\Ciclo;
use App\Dominio\Assinatura\Plano;
use App\Dominio\Assinatura\SituacaoAssinatura;
use App\Dominio\Assinatura\SituacaoCobranca;
use App\Dominio\Portal\Portal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssinaturaTest extends TestCase
{
    private Portal $portal;
    private Anuncio $anuncio;
    private Plano $plano;

    protected function setUp(): void
    {
        $this->portal = new Portal('Guia de Bauru', 'bauru', '2.4.0');
        $categoria = new Categoria($this->portal, 'Restaurantes', 'restaurantes');
        $this->anuncio = new Anuncio(
            $this->portal,
            'Padaria Estrela',
            'padaria-estrela',
            'Pão quente o dia inteiro.',
            $categoria,
        );
        $this->plano = new Plano($this->portal, 'Destaque', 9900, Ciclo::MENSAL, incluiDestaque: true);
    }

    private function assinatura(string $primeiraCobranca = 'today'): Assinatura
    {
        return new Assinatura($this->anuncio, $this->plano, new \DateTimeImmutable($primeiraCobranca));
    }

    #[Test]
    public function recusa_plano_de_outro_portal(): void
    {
        $outroPortal = new Portal('Guia do Vale', 'vale', '2.4.0');
        $planoDeOutro = new Plano($outroPortal, 'Básico', 4900, Ciclo::MENSAL);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('O plano é de outro portal.');

        new Assinatura($this->anuncio, $planoDeOutro, new \DateTimeImmutable());
    }

    #[Test]
    public function nasce_ativa_e_vencida_quando_a_primeira_cobranca_e_hoje(): void
    {
        $assinatura = $this->assinatura();

        $this->assertSame(SituacaoAssinatura::ATIVA, $assinatura->situacao());
        $this->assertTrue($assinatura->estaVencida());
    }

    #[Test]
    public function pagamento_coloca_o_anuncio_no_guia_ate_o_proximo_ciclo(): void
    {
        $assinatura = $this->assinatura();
        $cobranca = $assinatura->abrirCobranca();

        $assinatura->confirmarPagamento($cobranca);

        $this->assertTrue($cobranca->foiPaga());
        $this->assertSame(SituacaoAnuncio::PUBLICADO, $this->anuncio->situacao());
        $this->assertTrue($this->anuncio->ehDestaque(), 'o plano inclui destaque');
        $this->assertSame(
            (new \DateTimeImmutable('today'))->modify('+1 month')->format('Y-m-d'),
            $assinatura->proximaCobrancaEm()->format('Y-m-d'),
        );
    }

    #[Test]
    public function plano_anual_empurra_doze_meses(): void
    {
        $anual = new Plano($this->portal, 'Anual', 99000, Ciclo::ANUAL);
        $assinatura = new Assinatura($this->anuncio, $anual, new \DateTimeImmutable('today'));

        $assinatura->confirmarPagamento($assinatura->abrirCobranca());

        $this->assertSame(
            (new \DateTimeImmutable('today'))->modify('+12 months')->format('Y-m-d'),
            $assinatura->proximaCobrancaEm()->format('Y-m-d'),
        );
    }

    #[Test]
    public function rodar_a_rotina_duas_vezes_nao_cobra_duas_vezes(): void
    {
        $assinatura = $this->assinatura();

        $primeira = $assinatura->abrirCobranca();
        $segunda = $assinatura->abrirCobranca();

        $this->assertSame($primeira, $segunda);
        $this->assertCount(1, $assinatura->cobrancas());
    }

    #[Test]
    public function recusa_deixa_inadimplente_e_tenta_de_novo_em_tres_dias(): void
    {
        $assinatura = $this->assinatura();
        $vencimento = $assinatura->proximaCobrancaEm();

        $assinatura->registrarRecusa($assinatura->abrirCobranca(), 'cartão vencido');

        $this->assertSame(SituacaoAssinatura::INADIMPLENTE, $assinatura->situacao());
        $this->assertSame(1, $assinatura->tentativasSeguidasRecusadas());
        $this->assertSame(
            $vencimento->modify('+3 days')->format('Y-m-d'),
            $assinatura->proximaCobrancaEm()->format('Y-m-d'),
        );
    }

    #[Test]
    public function anuncio_continua_no_guia_durante_a_inadimplencia(): void
    {
        $assinatura = $this->assinatura();
        $assinatura->confirmarPagamento($assinatura->abrirCobranca());

        $assinatura->registrarRecusa($assinatura->abrirCobranca(), 'saldo insuficiente');

        $this->assertTrue($this->anuncio->estaNoGuia());
    }

    #[Test]
    public function tres_recusas_seguidas_cancelam_e_tiram_o_anuncio_do_ar(): void
    {
        $assinatura = $this->assinatura();
        $assinatura->confirmarPagamento($assinatura->abrirCobranca());

        for ($tentativa = 1; $tentativa <= 3; ++$tentativa) {
            $assinatura->registrarRecusa($assinatura->abrirCobranca(), 'cartão recusado');
        }

        $this->assertSame(SituacaoAssinatura::CANCELADA, $assinatura->situacao());
        $this->assertNotNull($assinatura->canceladaEm());
        $this->assertSame(SituacaoAnuncio::EXPIRADO, $this->anuncio->situacao());
    }

    #[Test]
    public function pagamento_depois_de_recusa_zera_o_contador(): void
    {
        $assinatura = $this->assinatura();
        $assinatura->registrarRecusa($assinatura->abrirCobranca(), 'cartão vencido');

        $assinatura->confirmarPagamento($assinatura->abrirCobranca());

        $this->assertSame(0, $assinatura->tentativasSeguidasRecusadas());
        $this->assertSame(SituacaoAssinatura::ATIVA, $assinatura->situacao());
    }

    #[Test]
    public function assinatura_cancelada_nao_gera_cobranca_nova(): void
    {
        $assinatura = $this->assinatura();
        $assinatura->cancelar();

        $this->expectException(AssinaturaCanceladaException::class);

        $assinatura->abrirCobranca();
    }

    #[Test]
    public function cancelar_duas_vezes_nao_muda_a_data_do_cancelamento(): void
    {
        $assinatura = $this->assinatura();
        $assinatura->cancelar();
        $primeira = $assinatura->canceladaEm();

        $assinatura->cancelar();

        $this->assertEquals($primeira, $assinatura->canceladaEm());
    }

    #[Test]
    public function cobranca_de_outra_assinatura_e_recusada(): void
    {
        $assinatura = $this->assinatura();
        $outroAnuncio = new Anuncio(
            $this->portal,
            'Mercado Central',
            'mercado-central',
            'Hortifruti e açougue.',
            new Categoria($this->portal, 'Mercados', 'mercados'),
        );
        $outraAssinatura = new Assinatura($outroAnuncio, $this->plano, new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A cobrança é de outra assinatura.');

        $assinatura->confirmarPagamento($outraAssinatura->abrirCobranca());
    }

    #[Test]
    public function cobranca_ja_fechada_nao_fecha_de_novo(): void
    {
        $assinatura = $this->assinatura();
        $cobranca = $assinatura->abrirCobranca();
        $assinatura->confirmarPagamento($cobranca);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já foi fechada como paga');

        $cobranca->marcarComoPaga();
    }

    #[Test]
    public function a_competencia_e_o_mes_do_ciclo(): void
    {
        $assinatura = $this->assinatura('2026-03-10');

        $this->assertSame('2026-03', $assinatura->abrirCobranca()->competencia());
    }

    #[Test]
    public function soma_o_que_foi_pago_e_ignora_o_que_foi_recusado(): void
    {
        $assinatura = $this->assinatura();
        $assinatura->confirmarPagamento($assinatura->abrirCobranca());
        $assinatura->registrarRecusa($assinatura->abrirCobranca(), 'cartão vencido');

        $this->assertSame(9900, $assinatura->totalPagoEmCentavos());
    }

    #[Test]
    public function estorno_so_vale_para_cobranca_paga(): void
    {
        $assinatura = $this->assinatura();
        $cobranca = $assinatura->abrirCobranca();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Só cobrança paga pode ser estornada.');

        $cobranca->estornar();
    }

    #[Test]
    public function estorno_marca_a_cobranca_e_sai_do_total(): void
    {
        $assinatura = $this->assinatura();
        $cobranca = $assinatura->abrirCobranca();
        $assinatura->confirmarPagamento($cobranca);

        $cobranca->estornar();

        $this->assertSame(SituacaoCobranca::ESTORNADA, $cobranca->situacao());
        $this->assertSame(0, $assinatura->totalPagoEmCentavos());
    }

    #[Test]
    public function valor_em_reais_sai_no_formato_daqui(): void
    {
        $assinatura = $this->assinatura();

        $this->assertSame('99,00', $assinatura->abrirCobranca()->valorEmReais());
    }
}
