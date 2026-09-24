<?php

declare(strict_types=1);

namespace App\Tests\Unidade\Http;

use App\Infraestrutura\Http\VersaoPeloConteudo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VersaoPeloConteudoTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        $this->raiz = sys_get_temp_dir().'/almanaque-versao-'.bin2hex(random_bytes(4));
        mkdir($this->raiz.'/estilos', 0o777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->raiz.'/estilos/*') ?: []);
        rmdir($this->raiz.'/estilos');
        rmdir($this->raiz);
    }

    #[Test]
    public function o_endereco_do_arquivo_carrega_a_marca_do_conteudo(): void
    {
        $this->gravar('estilos/folha.css', 'body { color: red }');

        $endereco = $this->estrategia()->applyVersion('estilos/folha.css');

        self::assertMatchesRegularExpression('/^estilos\/folha\.css\?v=[0-9a-f]{12}$/', $endereco);
    }

    #[Test]
    public function conteudo_diferente_da_marca_diferente(): void
    {
        $this->gravar('estilos/folha.css', 'body { color: red }');
        $antes = $this->estrategia()->getVersion('estilos/folha.css');

        $this->gravar('estilos/folha.css', 'body { color: blue }');
        $depois = $this->estrategia()->getVersion('estilos/folha.css');

        self::assertNotSame($antes, $depois);
    }

    /**
     * O ponto de cobrar o conteúdo, e não a data de modificação: fonte que não
     * mudou mantém o endereço entre publicações e não é baixada de novo.
     */
    #[Test]
    public function o_mesmo_conteudo_mantem_a_marca_mesmo_com_data_nova(): void
    {
        $this->gravar('estilos/folha.css', 'body { color: red }');
        $antes = $this->estrategia()->getVersion('estilos/folha.css');

        touch($this->raiz.'/estilos/folha.css', time() + 3600);
        $depois = $this->estrategia()->getVersion('estilos/folha.css');

        self::assertSame($antes, $depois);
    }

    #[Test]
    public function arquivo_que_nao_existe_sai_sem_marca_em_vez_de_quebrar(): void
    {
        $endereco = $this->estrategia()->applyVersion('estilos/sumiu.css');

        self::assertSame('estilos/sumiu.css', $endereco);
    }

    private function estrategia(): VersaoPeloConteudo
    {
        return new VersaoPeloConteudo($this->raiz);
    }

    private function gravar(string $caminho, string $conteudo): void
    {
        file_put_contents($this->raiz.'/'.$caminho, $conteudo);
    }
}
