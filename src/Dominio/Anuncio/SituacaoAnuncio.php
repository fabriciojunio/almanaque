<?php

declare(strict_types=1);

namespace App\Dominio\Anuncio;

enum SituacaoAnuncio: string
{
    case RASCUNHO = 'rascunho';
    case PUBLICADO = 'publicado';
    case EXPIRADO = 'expirado';
    case RECUSADO = 'recusado';

    public function rotulo(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::PUBLICADO => 'Publicado',
            self::EXPIRADO => 'Expirado',
            self::RECUSADO => 'Recusado',
        };
    }

    /** Só anúncio publicado aparece na busca e no guia. */
    public function apareceNoGuia(): bool
    {
        return self::PUBLICADO === $this;
    }
}
