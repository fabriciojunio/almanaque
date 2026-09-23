<?php

declare(strict_types=1);

namespace App\Aplicacao\Busca;

use App\Dominio\Busca\Consulta;
use App\Dominio\Busca\MotorDeBusca;
use App\Dominio\Busca\Resultado;
use App\Dominio\Portal\Portal;

/**
 * A busca do guia, do jeito que a página e a API a chamam.
 *
 * Existe para o controller não precisar saber que existe mais de um motor,
 * nem montar Consulta a partir de texto solto vindo da URL.
 */
final readonly class BuscarNoGuia
{
    public function __construct(private MotorDeBusca $motor)
    {
    }

    /** @param array<string, mixed> $parametros */
    public function apartirDaUrl(Portal $portal, array $parametros): Resultado
    {
        $consulta = new Consulta(
            portal: $portal,
            termo: $this->texto($parametros, 'q'),
            categoria: $this->texto($parametros, 'categoria'),
            cidade: $this->texto($parametros, 'cidade'),
            somenteDestaques: filter_var($parametros['destaques'] ?? false, FILTER_VALIDATE_BOOL),
            pagina: max(1, (int) ($parametros['pagina'] ?? 1)),
            tamanhoDaPagina: $this->tamanho($parametros),
        );

        return $this->motor->buscar($consulta);
    }

    public function executar(Consulta $consulta): Resultado
    {
        return $this->motor->buscar($consulta);
    }

    public function motor(): string
    {
        return $this->motor->nome();
    }

    /** @param array<string, mixed> $parametros */
    private function texto(array $parametros, string $chave): ?string
    {
        $valor = $parametros[$chave] ?? null;

        if (!is_string($valor)) {
            return null;
        }

        $limpo = trim($valor);

        return '' === $limpo ? null : mb_substr($limpo, 0, 120);
    }

    /** @param array<string, mixed> $parametros */
    private function tamanho(array $parametros): int
    {
        $pedido = (int) ($parametros['por_pagina'] ?? 12);

        return max(1, min($pedido, Consulta::TAMANHO_MAXIMO_DE_PAGINA));
    }
}
