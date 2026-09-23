<?php

declare(strict_types=1);

namespace App\Tests\Integracao;

use App\Aplicacao\Assinatura\RodarCobrancas;
use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Anuncio\Categoria;
use App\Dominio\Anuncio\SituacaoAnuncio;
use App\Dominio\Assinatura\Assinatura;
use App\Dominio\Assinatura\Ciclo;
use App\Dominio\Assinatura\Plano;
use App\Dominio\Assinatura\SituacaoAssinatura;
use App\Dominio\Portal\Portal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A rotina de cobrança contra o banco de verdade.
 *
 * O adquirente de demonstração recusa valor terminado em 13 centavos, o que
 * dá um caminho de recusa previsível sem depender de sandbox de terceiro.
 */
class RodarCobrancasTest extends KernelTestCase
{
    private EntityManagerInterface $gerenciador;
    private RodarCobrancas $rotina;
    private Portal $portal;
    private Categoria $categoria;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->gerenciador = static::getContainer()->get(EntityManagerInterface::class);
        $this->rotina = static::getContainer()->get(RodarCobrancas::class);

        $this->portal = new Portal('Guia de Teste', 'teste-cobranca', '2.4.0');
        $this->categoria = new Categoria($this->portal, 'Serviços', 'servicos-teste');

        $this->gerenciador->persist($this->portal);
        $this->gerenciador->persist($this->categoria);
        $this->gerenciador->flush();
    }

    #[Test]
    public function assinatura_vencida_e_cobrada_e_o_anuncio_entra_no_guia(): void
    {
        $assinatura = $this->assinaturaVencida(precoEmCentavos: 4900);

        $resumo = $this->rotina->executar(new \DateTimeImmutable('today'));

        self::assertSame(1, $resumo->pagas());
        self::assertSame(4900, $resumo->totalEmCentavos());
        self::assertSame(SituacaoAssinatura::ATIVA, $assinatura->situacao());
        self::assertSame(SituacaoAnuncio::PUBLICADO, $assinatura->anuncio()->situacao());
    }

    #[Test]
    public function rodar_duas_vezes_no_mesmo_dia_nao_cobra_duas_vezes(): void
    {
        $assinatura = $this->assinaturaVencida(precoEmCentavos: 4900);

        $this->rotina->executar(new \DateTimeImmutable('today'));
        $segundoResumo = $this->rotina->executar(new \DateTimeImmutable('today'));

        self::assertSame(0, $segundoResumo->pagas(), 'a segunda volta não acha nada vencido');
        self::assertCount(1, $assinatura->cobrancas());
        self::assertSame(4900, $assinatura->totalPagoEmCentavos());
    }

    #[Test]
    public function cobranca_recusada_deixa_a_assinatura_inadimplente_com_o_motivo(): void
    {
        $assinatura = $this->assinaturaVencida(precoEmCentavos: 4913);

        $resumo = $this->rotina->executar(new \DateTimeImmutable('today'));

        self::assertSame(1, $resumo->recusadas());
        self::assertSame(SituacaoAssinatura::INADIMPLENTE, $assinatura->situacao());
        self::assertSame(1, $assinatura->tentativasSeguidasRecusadas());

        $cobranca = $assinatura->cobrancas()->first();
        self::assertNotFalse($cobranca);
        self::assertTrue($cobranca->foiRecusada());
        self::assertSame('cartão recusado pelo emissor', $cobranca->motivoDaRecusa());
        self::assertNotNull($cobranca->identificadorNoGateway());
    }

    #[Test]
    public function o_anuncio_fica_no_guia_durante_a_inadimplencia(): void
    {
        $assinatura = $this->assinaturaVencida(precoEmCentavos: 4900);
        $this->rotina->executar(new \DateTimeImmutable('today'));

        // Segundo ciclo, agora com valor que o adquirente recusa.
        $this->trocarPreco($assinatura, 4913);
        $this->rotina->executar($assinatura->proximaCobrancaEm());

        self::assertSame(SituacaoAssinatura::INADIMPLENTE, $assinatura->situacao());
        self::assertTrue($assinatura->anuncio()->estaNoGuia(), 'três dias de tolerância antes de sair');
    }

    #[Test]
    public function tres_recusas_seguidas_cancelam_e_tiram_o_anuncio_do_guia(): void
    {
        $assinatura = $this->assinaturaVencida(precoEmCentavos: 4900);
        $this->rotina->executar(new \DateTimeImmutable('today'));
        $this->trocarPreco($assinatura, 4913);

        for ($volta = 1; $volta <= 3; ++$volta) {
            $this->rotina->executar($assinatura->proximaCobrancaEm());
        }

        self::assertSame(SituacaoAssinatura::CANCELADA, $assinatura->situacao());
        self::assertSame(SituacaoAnuncio::EXPIRADO, $assinatura->anuncio()->situacao());
    }

    #[Test]
    public function assinatura_que_ainda_nao_venceu_fica_quieta(): void
    {
        $this->assinaturaVencida(precoEmCentavos: 4900, vencimento: '+5 days');

        $resumo = $this->rotina->executar(new \DateTimeImmutable('today'));

        self::assertSame(0, $resumo->processadas());
    }

    #[Test]
    public function o_lote_limita_quantas_assinaturas_a_rotina_pega(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->assinaturaVencida(precoEmCentavos: 4900, sufixo: (string) $i);
        }

        $resumo = $this->rotina->executar(new \DateTimeImmutable('today'), lote: 2);

        self::assertSame(2, $resumo->processadas());
    }

    private function assinaturaVencida(
        int $precoEmCentavos,
        string $vencimento = 'today',
        string $sufixo = '',
    ): Assinatura {
        $anuncio = new Anuncio(
            $this->portal,
            'Chaveiro do Zé'.$sufixo,
            'chaveiro-do-ze'.$sufixo,
            'Chaves, fechaduras e cópias na hora.',
            $this->categoria,
        );

        $plano = new Plano($this->portal, 'Teste'.$sufixo, $precoEmCentavos, Ciclo::MENSAL);
        $assinatura = new Assinatura($anuncio, $plano, new \DateTimeImmutable($vencimento));

        $this->gerenciador->persist($anuncio);
        $this->gerenciador->persist($plano);
        $this->gerenciador->persist($assinatura);
        $this->gerenciador->flush();

        return $assinatura;
    }

    private function trocarPreco(Assinatura $assinatura, int $centavos): void
    {
        $novo = new Plano($this->portal, 'Recusado', $centavos, Ciclo::MENSAL);
        $this->gerenciador->persist($novo);
        $this->gerenciador->flush();

        $this->gerenciador->getConnection()->executeStatement(
            'UPDATE assinaturas SET plano_id = :plano WHERE id = :id',
            ['plano' => $novo->id(), 'id' => $assinatura->id()],
        );

        $this->gerenciador->refresh($assinatura);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->gerenciador, $this->rotina);
    }
}
