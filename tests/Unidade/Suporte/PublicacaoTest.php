<?php

declare(strict_types=1);

namespace App\Tests\Unidade\Suporte;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\ProblemaConhecido;
use App\Dominio\Suporte\Publicacao;
use App\Dominio\Suporte\SituacaoPublicacao;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PublicacaoTest extends TestCase
{
    private Portal $portal;

    protected function setUp(): void
    {
        $this->portal = new Portal('Guia de Bauru', 'bauru', '2.4.0');
    }

    #[Test]
    public function publicar_troca_a_versao_do_portal_na_hora(): void
    {
        $publicacao = new Publicacao($this->portal, '2.5.0', 'fabricio');

        $this->assertSame('2.5.0', $this->portal->versao());
        $this->assertSame('2.4.0', $publicacao->versaoAnterior());
        $this->assertSame(SituacaoPublicacao::EM_ANDAMENTO, $publicacao->situacao());
    }

    #[Test]
    public function nao_publica_versao_anterior_a_que_esta_no_ar(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('seria voltar no tempo');

        new Publicacao($this->portal, '2.3.0', 'fabricio');
    }

    #[Test]
    public function a_versao_2_10_e_maior_que_a_2_9(): void
    {
        $portal = new Portal('Guia do Vale', 'vale', '2.9.0');

        $publicacao = new Publicacao($portal, '2.10.0', 'fabricio');

        $this->assertSame('2.10.0', $portal->versao());
        $this->assertSame('2.9.0', $publicacao->versaoAnterior());
    }

    #[Test]
    public function publicacao_sem_verificacao_nao_se_conclui(): void
    {
        $publicacao = new Publicacao($this->portal, '2.5.0', 'fabricio');

        $this->expectException(\DomainException::class);

        $publicacao->concluir();
    }

    #[Test]
    public function verificacao_reprovada_impede_concluir_e_diz_qual_foi(): void
    {
        $publicacao = new Publicacao($this->portal, '2.5.0', 'fabricio');
        $publicacao->registrarVerificacao('página inicial abre', true);
        $publicacao->registrarVerificacao('busca responde', false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('busca responde');

        $publicacao->concluir();
    }

    #[Test]
    public function com_tudo_aprovado_a_publicacao_fecha(): void
    {
        $publicacao = new Publicacao($this->portal, '2.5.0', 'fabricio');
        $publicacao->registrarVerificacao('página inicial abre', true);
        $publicacao->registrarVerificacao('busca responde', true);
        $publicacao->registrarVerificacao('cobrança processa', true);

        $publicacao->concluir();

        $this->assertSame(SituacaoPublicacao::CONCLUIDA, $publicacao->situacao());
        $this->assertNotNull($publicacao->encerradaEm());
        $this->assertSame([], $publicacao->verificacoesReprovadas());
    }

    #[Test]
    public function reverter_devolve_o_portal_para_a_versao_anterior(): void
    {
        $publicacao = new Publicacao($this->portal, '2.5.0', 'fabricio');
        $publicacao->registrarVerificacao('busca responde', false);

        $publicacao->reverter('a busca parou de responder depois da subida');

        $this->assertSame('2.4.0', $this->portal->versao());
        $this->assertSame(SituacaoPublicacao::REVERTIDA, $publicacao->situacao());
        $this->assertStringContainsString('busca parou', (string) $publicacao->observacao());
    }

    #[Test]
    public function publicacao_encerrada_nao_recebe_verificacao_nova(): void
    {
        $publicacao = new Publicacao($this->portal, '2.5.0', 'fabricio');
        $publicacao->registrarVerificacao('tudo certo', true);
        $publicacao->concluir();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já foi encerrada');

        $publicacao->registrarVerificacao('outra coisa', true);
    }

    #[Test]
    public function problema_corrigido_deixa_de_afetar_quem_atualizou(): void
    {
        $problema = new ProblemaConhecido(
            'PC-014',
            'Anúncio novo demora a entrar no índice',
            'publiquei e não aparece na busca',
            'A reindexação só rodava na virada do dia.',
            '2.3.0',
        );
        $problema->marcarCorrigidoNa('2.5.0');

        $this->assertTrue($problema->afeta('2.4.0'), 'quem está atrás da correção ainda pega');
        $this->assertFalse($problema->afeta('2.5.0'), 'quem atualizou não pega mais');
        $this->assertFalse($problema->afeta('2.2.0'), 'quem está antes da versão afetada nunca pegou');
    }

    #[Test]
    public function problema_sem_correcao_afeta_todas_as_versoes_dali_para_frente(): void
    {
        $problema = new ProblemaConhecido(
            'PC-015',
            'Cobrança recorrente duplica em fevereiro',
            'fui cobrado duas vezes',
            'O dia 30 não existe em fevereiro e a data escorregava.',
            '2.4.0',
        );

        $this->assertFalse($problema->estaCorrigido());
        $this->assertTrue($problema->afeta('2.4.0'));
        $this->assertTrue($problema->afeta('2.10.0'));
        $this->assertFalse($problema->afeta('2.3.9'));
    }
}
