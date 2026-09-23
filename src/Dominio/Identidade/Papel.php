<?php

declare(strict_types=1);

namespace App\Dominio\Identidade;

/**
 * Quem é quem no Almanaque.
 *
 * O anunciante mexe no próprio anúncio. O dono do portal mexe no guia dele. O
 * analista de suporte atravessa portais, porque atende todos eles, e é o
 * único que enxerga anotação interna de chamado.
 */
enum Papel: string
{
    case ANUNCIANTE = 'ROLE_ANUNCIANTE';
    case DONO_DO_PORTAL = 'ROLE_DONO_DO_PORTAL';
    case SUPORTE = 'ROLE_SUPORTE';
    case ADMINISTRADOR = 'ROLE_ADMIN';

    public function rotulo(): string
    {
        return match ($this) {
            self::ANUNCIANTE => 'Anunciante',
            self::DONO_DO_PORTAL => 'Dono do portal',
            self::SUPORTE => 'Suporte',
            self::ADMINISTRADOR => 'Administrador',
        };
    }

    public function atravessaPortais(): bool
    {
        return in_array($this, [self::SUPORTE, self::ADMINISTRADOR], true);
    }
}
