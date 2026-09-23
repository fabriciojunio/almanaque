<?php

declare(strict_types=1);

namespace App\Tests\Unidade\Suporte;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\ChamadoNaoReproduzidoException;
use App\Dominio\Suporte\ChamadoSemClassificacaoException;
use App\Dominio\Suporte\Classificacao;
use App\Dominio\Suporte\Prioridade;
use App\Dominio\Suporte\ProblemaConhecido;
use App\Dominio\Suporte\SituacaoChamado;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChamadoTest extends TestCase
{
    private Portal $portal;

    protected function setUp(): void
    {
        $this->portal = new Portal('Guia de Bauru', 'bauru', '2.4.0');
    }

    private function chamado(
        Prioridade $prioridade = Prioridade::NORMAL,
        ?\DateTimeImmutable $abertoEm = null,
    ): Chamado {
        return new Chamado(
            $this->portal,
            'A busca não acha meu anúncio',
            'Publiquei ontem e não aparece quando procuro pelo nome.',
            'cliente@guiadebauru.com.br',
            $prioridade,
            'req-8f21c4',
            $abertoEm,
        );
    }

    #[Test]
    public function guarda_a_versao_do_portal_no_momento_da_abertura(): void
    {
        $chamado = $this->chamado();
        $this->portal->publicarVersao('2.5.0');

        $this->assertSame('2.4.0', $chamado->versaoDoPortal());
    }

    #[Test]
    public function nasce_aberto_sem_classificacao_e_sem_responsavel(): void
    {
        $chamado = $this->chamado();

        $this->assertSame(SituacaoChamado::ABERTO, $chamado->situacao());
        $this->assertNull($chamado->classificacao());
        $this->assertNull($chamado->responsavel());
        $this->assertSame('req-8f21c4', $chamado->identificadorDeRequisicao());
    }

    #[Test]
    public function assumir_poe_em_investigacao(): void
    {
        $chamado = $this->chamado();

        $chamado->assumir('fabricio');

        $this->assertSame(SituacaoChamado::EM_INVESTIGACAO, $chamado->situacao());
        $this->assertSame('fabricio', $chamado->responsavel());
    }

    #[Test]
    public function dois_analistas_nao_assumem_o_mesmo_chamado(): void
    {
        $chamado = $this->chamado();
        $chamado->assumir('fabricio');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já está em investigação');

        $chamado->assumir('outra pessoa');
    }

    #[Test]
    public function nao_resolve_sem_dizer_o_que_era(): void
    {
        $chamado = $this->chamado();

        $this->expectException(ChamadoSemClassificacaoException::class);

        $chamado->resolver();
    }

    #[Test]
    public function classificar_como_defeito_exige_ter_reproduzido(): void
    {
        $chamado = $this->chamado();

        $this->expectException(ChamadoNaoReproduzidoException::class);

        $chamado->classificar(Classificacao::DEFEITO);
    }

    #[Test]
    public function defeito_reproduzido_aceita_o_problema_conhecido_junto(): void
    {
        $chamado = $this->chamado();
        $problema = new ProblemaConhecido(
            'PC-014',
            'Anúncio novo demora a entrar no índice',
            'publiquei e não aparece na busca',
            'A reindexação só rodava na virada do dia.',
            '2.3.0',
        );

        $chamado->registrarReproducao(emAmbienteLimpo: true);
        $chamado->classificar(Classificacao::DEFEITO, $problema);

        $this->assertSame(Classificacao::DEFEITO, $chamado->classificacao());
        $this->assertSame($problema, $chamado->problemaConhecido());
        $this->assertSame(SituacaoChamado::EM_INVESTIGACAO, $chamado->situacao());
    }

    #[Test]
    public function customizacao_do_cliente_so_vale_em_portal_customizado(): void
    {
        $chamado = $this->chamado();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não tem customização registrada');

        $chamado->classificar(Classificacao::CUSTOMIZACAO_DO_CLIENTE);
    }

    #[Test]
    public function customizacao_do_cliente_vale_quando_o_portal_foi_alterado(): void
    {
        $this->portal->marcarComoCustomizado();
        $chamado = $this->chamado();
        $chamado->registrarReproducao(emAmbienteLimpo: false);

        $chamado->classificar(Classificacao::CUSTOMIZACAO_DO_CLIENTE);

        $this->assertSame(Classificacao::CUSTOMIZACAO_DO_CLIENTE, $chamado->classificacao());
        $this->assertFalse($chamado->foiReproduzidoEmAmbienteLimpo());
    }

    #[Test]
    public function duvida_de_uso_nao_exige_reproducao(): void
    {
        $chamado = $this->chamado();

        $chamado->classificar(Classificacao::DUVIDA_DE_USO);
        $chamado->resolver();

        $this->assertSame(SituacaoChamado::RESOLVIDO, $chamado->situacao());
    }

    #[Test]
    public function so_defeito_vira_trabalho_para_o_desenvolvimento(): void
    {
        $this->assertTrue(Classificacao::DEFEITO->viraTarefaDeDesenvolvimento());
        $this->assertFalse(Classificacao::DUVIDA_DE_USO->viraTarefaDeDesenvolvimento());
        $this->assertFalse(Classificacao::CONFIGURACAO->viraTarefaDeDesenvolvimento());
        $this->assertFalse(Classificacao::CUSTOMIZACAO_DO_CLIENTE->viraTarefaDeDesenvolvimento());
    }

    #[Test]
    public function anotacao_visivel_marca_a_primeira_resposta(): void
    {
        $chamado = $this->chamado();

        $chamado->anotar('fabricio', 'Rodei a consulta e o anúncio está publicado.', visivelAoCliente: false);
        $this->assertNull($chamado->primeiraRespostaEm());

        $chamado->anotar('fabricio', 'Oi! Já estou olhando o seu caso.', visivelAoCliente: true);
        $this->assertNotNull($chamado->primeiraRespostaEm());
    }

    #[Test]
    public function anotacao_interna_nao_vai_para_o_cliente(): void
    {
        $chamado = $this->chamado();
        $anotacao = $chamado->anotar('fabricio', 'select * from anuncios where id = 4821', false);

        $this->assertFalse($anotacao->ehVisivelAoCliente());
        $this->assertCount(1, $chamado->anotacoes());
    }

    #[Test]
    public function so_fecha_depois_de_resolver(): void
    {
        $chamado = $this->chamado();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Só chamado resolvido pode ser fechado.');

        $chamado->fechar();
    }

    #[Test]
    public function fluxo_inteiro_do_chamado(): void
    {
        $chamado = $this->chamado();

        $chamado->assumir('fabricio');
        $chamado->anotar('fabricio', 'Pedi a hora e o usuário.', true);
        $chamado->devolverAoCliente();
        $this->assertSame(SituacaoChamado::AGUARDANDO_CLIENTE, $chamado->situacao());

        $chamado->registrarReproducao(emAmbienteLimpo: true);
        $chamado->classificar(Classificacao::DEFEITO);
        $chamado->resolver();
        $chamado->fechar();

        $this->assertSame(SituacaoChamado::FECHADO, $chamado->situacao());
        $this->assertNotNull($chamado->resolvidoEm());
    }

    #[Test]
    public function prazo_de_resposta_vem_da_prioridade(): void
    {
        $abertura = new \DateTimeImmutable('2026-09-23 09:00');
        $critico = $this->chamado(Prioridade::CRITICA, $abertura);
        $baixo = $this->chamado(Prioridade::BAIXA, $abertura);

        $this->assertSame('2026-09-23 10:00', $critico->prazoDeRespostaEm()->format('Y-m-d H:i'));
        $this->assertSame('2026-09-26 09:00', $baixo->prazoDeRespostaEm()->format('Y-m-d H:i'));
    }

    #[Test]
    public function chamado_sem_resposta_dentro_do_prazo_nao_estourou(): void
    {
        $chamado = $this->chamado(Prioridade::ALTA, new \DateTimeImmutable('-1 hour'));

        $this->assertFalse($chamado->estourouOPrazo());
    }

    #[Test]
    public function chamado_critico_parado_estoura_em_uma_hora(): void
    {
        $chamado = $this->chamado(Prioridade::CRITICA, new \DateTimeImmutable('-2 hours'));

        $this->assertTrue($chamado->estourouOPrazo());
    }

    #[Test]
    public function o_relogio_para_enquanto_a_bola_esta_com_o_cliente(): void
    {
        $chamado = $this->chamado(Prioridade::CRITICA, new \DateTimeImmutable('-5 hours'));
        $chamado->assumir('fabricio');
        $chamado->devolverAoCliente();

        $this->assertFalse($chamado->estourouOPrazo());
    }

    #[Test]
    public function respondido_depois_do_prazo_continua_estourado_para_sempre(): void
    {
        $chamado = $this->chamado(Prioridade::CRITICA, new \DateTimeImmutable('-3 hours'));
        $chamado->anotar('fabricio', 'Desculpe a demora.', true);
        $chamado->classificar(Classificacao::DUVIDA_DE_USO);
        $chamado->resolver();

        $this->assertTrue($chamado->estourouOPrazo());
    }

    #[Test]
    public function repriorizar_deixa_o_motivo_registrado(): void
    {
        $chamado = $this->chamado();

        $chamado->repriorizar(Prioridade::CRITICA, 'o portal inteiro está fora do ar');

        $this->assertSame(Prioridade::CRITICA, $chamado->prioridade());
        $this->assertStringContainsString(
            'o portal inteiro está fora do ar',
            $chamado->anotacoes()->first()->texto(),
        );
        $this->assertFalse($chamado->anotacoes()->first()->ehVisivelAoCliente());
    }

    #[Test]
    public function resolver_sem_ter_respondido_marca_a_resposta_na_hora_de_resolver(): void
    {
        $chamado = $this->chamado();
        $chamado->classificar(Classificacao::CONFIGURACAO);

        $chamado->resolver(new \DateTimeImmutable('2026-09-23 11:00'));

        $this->assertSame('2026-09-23 11:00', $chamado->primeiraRespostaEm()?->format('Y-m-d H:i'));
    }
}
