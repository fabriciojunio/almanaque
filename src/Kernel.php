<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Onde fica o que é gerado na construção: o container compilado e as
     * rotas.
     *
     * Na publicação sem servidor vai para /tmp junto com o resto. A
     * alternativa seria gerar na construção e mandar pronto, mas o container
     * guarda dentro dele o caminho do cache, e o caminho da máquina que
     * constrói não é o da máquina que roda. Compilar na primeira requisição
     * de cada instância custa um segundo, uma vez.
     */
    public function getBuildDir(): string
    {
        if ($this->semDiscoDeEscrita()) {
            return '/tmp/almanaque/construcao/'.$this->environment;
        }

        return $this->getProjectDir().'/var/cache/'.$this->environment.'/construcao';
    }

    /**
     * Onde fica o que a aplicação escreve enquanto roda: template compilado
     * do Twig e cache de consulta do Doctrine.
     *
     * Na publicação sem servidor o disco é somente leitura fora de /tmp, e
     * cada instância tem o seu. O preço é a primeira requisição de cada
     * instância compilar os templates de novo; o container, que é a parte
     * cara, já vem pronto no pacote.
     */
    public function getCacheDir(): string
    {
        if ($this->semDiscoDeEscrita()) {
            return '/tmp/almanaque/cache/'.$this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ($this->semDiscoDeEscrita()) {
            return '/tmp/almanaque/log';
        }

        return parent::getLogDir();
    }

    private function semDiscoDeEscrita(): bool
    {
        return (bool) ($_SERVER['DISCO_SOMENTE_LEITURA'] ?? $_SERVER['VERCEL'] ?? false);
    }
}
