<?php

declare(strict_types=1);

namespace App\Dominio\Anuncio;

class AnuncioRecusadoException extends \DomainException
{
    public function __construct(string $titulo)
    {
        parent::__construct(sprintf(
            'O anúncio "%s" foi recusado pela moderação e não pode ser publicado.',
            $titulo
        ));
    }
}
