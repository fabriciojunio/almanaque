<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

class AssinaturaCanceladaException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Assinatura cancelada não gera cobrança nova.');
    }
}
