<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

class ChamadoNaoReproduzidoException extends \DomainException
{
    public function __construct(string $titulo)
    {
        parent::__construct(sprintf(
            'O chamado "%s" não pode ser classificado como defeito sem ter sido reproduzido.',
            $titulo
        ));
    }
}
