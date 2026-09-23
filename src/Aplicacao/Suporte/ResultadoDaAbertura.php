<?php

declare(strict_types=1);

namespace App\Aplicacao\Suporte;

use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\ProblemaConhecido;

final readonly class ResultadoDaAbertura
{
    /** @param list<ProblemaConhecido> $problemasParecidos */
    public function __construct(
        public Chamado $chamado,
        public array $problemasParecidos,
    ) {
    }

    public function temCasoParecido(): bool
    {
        return [] !== $this->problemasParecidos;
    }
}
