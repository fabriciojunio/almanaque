<?php

declare(strict_types=1);

namespace App\Tests;

use App\Dominio\Identidade\Repositorio\UsuarioRepositorio;
use App\Dominio\Identidade\TokenAcesso;
use App\Dominio\Portal\Portal;
use App\Dominio\Portal\Repositorio\PortalRepositorio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base dos testes que passam pelo HTTP.
 *
 * O banco é o SQLite de teste, criado pelo comando de preparação e carregado
 * com os dados de exemplo uma vez só. Cada teste roda dentro de uma transação
 * que volta atrás no fim, cortesia do DAMADoctrineTestBundle.
 */
abstract class CasoDeTesteHttp extends WebTestCase
{
    protected KernelBrowser $navegador;

    protected function setUp(): void
    {
        $this->navegador = static::createClient();

        // O limitador conta em disco, para a contagem atravessar requisições
        // dentro do mesmo teste. Zerar aqui evita que um teste que erra a
        // senha de propósito deixe o limite consumido para o seguinte.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function gerenciador(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function portal(string $apelido): Portal
    {
        $portal = static::getContainer()->get(PortalRepositorio::class)->porApelido($apelido);

        if (null === $portal) {
            self::fail(sprintf('O portal "%s" não está nos dados de exemplo.', $apelido));
        }

        return $portal;
    }

    /**
     * Gera um token de API para a conta informada e devolve o segredo.
     *
     * Passa pelo mesmo caminho da aplicação de propósito: token de teste
     * criado na mão esconderia um defeito na geração ou na validação.
     */
    protected function tokenDe(string $email): string
    {
        $usuario = static::getContainer()->get(UsuarioRepositorio::class)->porEmail($email);

        if (null === $usuario) {
            self::fail(sprintf('A conta "%s" não está nos dados de exemplo.', $email));
        }

        [$token, $segredo] = TokenAcesso::gerar($usuario, 'bateria de testes');
        $this->gerenciador()->persist($token);
        $this->gerenciador()->flush();

        return $segredo;
    }

    /** @param array<string, mixed> $corpo */
    protected function chamar(
        string $metodo,
        string $caminho,
        ?string $token = null,
        ?array $corpo = null,
    ): void {
        $cabecalhos = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $token) {
            $cabecalhos['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->navegador->request(
            $metodo,
            $caminho,
            server: $cabecalhos,
            content: null !== $corpo ? json_encode($corpo, JSON_THROW_ON_ERROR) : null,
        );
    }

    /** @return array<string, mixed> */
    protected function json(): array
    {
        $conteudo = $this->navegador->getResponse()->getContent();

        self::assertIsString($conteudo);

        $dados = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($dados, 'a resposta não era um JSON de objeto');

        return $dados;
    }
}
