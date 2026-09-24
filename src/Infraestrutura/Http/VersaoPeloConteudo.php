<?php

declare(strict_types=1);

namespace App\Infraestrutura\Http;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * A marca de versão do arquivo estático, tirada do conteúdo dele.
 *
 * A folha de estilo é servida com validade longa e nome fixo. Enquanto a URL
 * não mudava, quem já tinha visitado o site continuava com a versão antiga por
 * uma semana, e a correção publicada simplesmente não aparecia. Aconteceu.
 *
 * O trecho do conteúdo entra na URL, então arquivo que não mudou mantém o
 * endereço e segue valendo no navegador, e arquivo que mudou passa a ter outro.
 * Fonte que continua igual não é baixada de novo a cada publicação, que é o que
 * aconteceria com data de modificação.
 */
final class VersaoPeloConteudo implements VersionStrategyInterface
{
    /** @var array<string, string> */
    private array $calculadas = [];

    public function __construct(private readonly string $raizPublica)
    {
    }

    public function getVersion(string $path): string
    {
        return $this->calculadas[$path] ??= $this->resumir($path);
    }

    public function applyVersion(string $path): string
    {
        $versao = $this->getVersion($path);

        if ('' === $versao) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'v='.$versao;
    }

    private function resumir(string $path): string
    {
        $arquivo = $this->raizPublica.'/'.ltrim($path, '/');

        if (!is_file($arquivo)) {
            return '';
        }

        $conteudo = @file_get_contents($arquivo);

        if (false === $conteudo) {
            return '';
        }

        return substr(hash('xxh128', $conteudo), 0, 12);
    }
}
