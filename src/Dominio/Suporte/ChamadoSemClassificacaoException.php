<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

class ChamadoSemClassificacaoException extends \DomainException
{
    public function __construct(string $titulo)
    {
        parent::__construct(sprintf(
            'O chamado "%s" não pode ser resolvido antes de ser classificado em dúvida de uso, configuração, defeito ou customização do cliente.',
            $titulo
        ));
    }
}
