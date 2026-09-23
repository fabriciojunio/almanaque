<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Dominio\Identidade\Repositorio\UsuarioRepositorio;
use App\Dominio\Identidade\TokenAcesso;
use App\Tests\CasoDeTesteHttp;
use PHPUnit\Framework\Attributes\Test;

/**
 * O que precisa continuar valendo depois de qualquer mudança: quem entra,
 * quem não entra, e o que cada um enxerga.
 */
class SegurancaApiTest extends CasoDeTesteHttp
{
    #[Test]
    public function sem_token_a_area_autenticada_responde_401(): void
    {
        $this->chamar('GET', '/api/chamados');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function token_inventado_nao_entra(): void
    {
        $this->chamar('GET', '/api/chamados', token: str_repeat('a', 64));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function token_revogado_nao_entra(): void
    {
        $usuario = static::getContainer()->get(UsuarioRepositorio::class)
            ->porEmail('suporte@almanaque.com.br');
        self::assertNotNull($usuario);

        [$token, $segredo] = TokenAcesso::gerar($usuario, 'teste de revogação');
        $token->revogar();
        $this->gerenciador()->persist($token);
        $this->gerenciador()->flush();

        $this->chamar('GET', '/api/chamados', token: $segredo);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function token_de_conta_desativada_nao_entra(): void
    {
        $usuario = static::getContainer()->get(UsuarioRepositorio::class)
            ->porEmail('anunciante@guiadebauru.com.br');
        self::assertNotNull($usuario);

        [$token, $segredo] = TokenAcesso::gerar($usuario, 'teste de desativação');
        $this->gerenciador()->persist($token);
        $usuario->desativar();
        $this->gerenciador()->flush();

        $this->chamar('GET', '/api/chamados', token: $segredo);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function o_banco_nunca_guarda_o_token_em_claro(): void
    {
        $segredo = $this->tokenDe('suporte@almanaque.com.br');

        $guardado = $this->gerenciador()->getConnection()
            ->executeQuery('SELECT hash FROM tokens_acesso ORDER BY id DESC')
            ->fetchOne();

        self::assertNotSame($segredo, $guardado);
        self::assertSame(hash('sha256', $segredo), $guardado);
    }

    #[Test]
    public function login_devolve_o_mesmo_erro_para_email_inexistente_e_senha_errada(): void
    {
        $this->chamar('POST', '/api/tokens', corpo: [
            'email' => 'ninguem@exemplo.com.br',
            'senha' => 'seja-la-qual-for',
        ]);
        $paraQuemNaoExiste = $this->json();
        self::assertResponseStatusCodeSame(401);

        $this->chamar('POST', '/api/tokens', corpo: [
            'email' => 'suporte@almanaque.com.br',
            'senha' => 'senha-errada',
        ]);
        $paraSenhaErrada = $this->json();
        self::assertResponseStatusCodeSame(401);

        self::assertSame($paraQuemNaoExiste, $paraSenhaErrada);
        self::assertSame('E-mail ou senha inválidos.', $paraSenhaErrada['erro']);
    }

    #[Test]
    public function login_correto_devolve_token_que_funciona(): void
    {
        $this->chamar('POST', '/api/tokens', corpo: [
            'email' => 'suporte@almanaque.com.br',
            'senha' => 'demonstracao2026',
        ]);

        self::assertResponseStatusCodeSame(201);
        $corpo = $this->json();
        self::assertNotEmpty($corpo['token']);
        self::assertSame('ROLE_SUPORTE', $corpo['usuario']['papel']);

        $this->chamar('GET', '/api/chamados', token: $corpo['token']);
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function a_sexta_tentativa_de_senha_no_mesmo_minuto_e_barrada(): void
    {
        // Sem isto o navegador de teste reinicia o núcleo a cada requisição, a
        // contagem do limitador (que na bateria vive em memória) zera junto, e
        // o teste passaria a provar o contrário do que ele quer provar.
        $this->navegador->disableReboot();

        for ($tentativa = 1; $tentativa <= 5; ++$tentativa) {
            $this->chamar('POST', '/api/tokens', corpo: [
                'email' => 'suporte@almanaque.com.br',
                'senha' => 'senha-errada-'.$tentativa,
            ]);
            self::assertResponseStatusCodeSame(401);
        }

        $this->chamar('POST', '/api/tokens', corpo: [
            'email' => 'suporte@almanaque.com.br',
            'senha' => 'demonstracao2026',
        ]);

        self::assertResponseStatusCodeSame(429, 'o limite vale até para a senha certa');
    }

    #[Test]
    public function o_dono_de_um_portal_nao_abre_chamado_de_outro(): void
    {
        $token = $this->tokenDe('dono@guiadebauru.com.br');

        $this->chamar('GET', '/api/chamados', token: $token);
        $doBauru = $this->json()['dados'];

        foreach ($doBauru as $chamado) {
            self::assertSame('bauru', $chamado['portal']);
        }

        $chamadoDoVale = array_values(array_filter(
            $this->chamadosDoSuporte(),
            static fn (array $c) => 'vale' === $c['portal'],
        ))[0];

        $this->chamar('GET', '/api/chamados/'.$chamadoDoVale['id'], token: $token);
        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function o_cliente_ve_o_proprio_chamado_mas_nao_a_anotacao_interna(): void
    {
        $chamadoDoVale = array_values(array_filter(
            $this->chamadosDoSuporte(),
            static fn (array $c) => 'vale' === $c['portal'],
        ))[0];

        $this->chamar('GET', '/api/chamados/'.$chamadoDoVale['id'], token: $this->tokenDe('dono@valenegocios.com.br'));
        self::assertResponseIsSuccessful();

        $anotacoes = $this->json()['anotacoes'];
        self::assertNotEmpty($anotacoes);
        foreach ($anotacoes as $anotacao) {
            self::assertFalse($anotacao['interna'], 'anotação interna não pode chegar ao cliente');
        }
    }

    #[Test]
    public function o_suporte_ve_a_anotacao_interna(): void
    {
        $chamadoDoVale = array_values(array_filter(
            $this->chamadosDoSuporte(),
            static fn (array $c) => 'vale' === $c['portal'],
        ))[0];

        $this->chamar('GET', '/api/chamados/'.$chamadoDoVale['id'], token: $this->tokenDe('suporte@almanaque.com.br'));

        $internas = array_filter($this->json()['anotacoes'], static fn (array $a) => $a['interna']);
        self::assertNotEmpty($internas);
    }

    #[Test]
    public function cliente_nao_faz_triagem(): void
    {
        $chamado = $this->chamadosDoSuporte()[0];

        $this->chamar(
            'POST',
            '/api/chamados/'.$chamado['id'].'/triagem',
            token: $this->tokenDe('dono@guiadebauru.com.br'),
            corpo: ['classificacao' => 'duvida_de_uso'],
        );

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function a_area_publica_do_guia_continua_aberta(): void
    {
        $this->chamar('GET', '/api/g/bauru/anuncios');

        self::assertResponseIsSuccessful();
    }

    /** @return list<array<string, mixed>> */
    private function chamadosDoSuporte(): array
    {
        $this->chamar('GET', '/api/chamados', token: $this->tokenDe('suporte@almanaque.com.br'));

        return $this->json()['dados'];
    }
}
