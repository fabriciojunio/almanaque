<?php

declare(strict_types=1);

namespace App\Infraestrutura\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Os cabeçalhos de segurança de toda resposta.
 *
 * A política de conteúdo é restritiva porque o guia não carrega script de
 * terceiro: o mapa é desenhado com os dados do próprio anúncio, e não com um
 * iframe de fora. Cliente que customiza o tema dele precisa pedir a liberação
 * do domínio, e isso é de propósito.
 */
final class CabecalhosDeSeguranca
{
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -128)]
    public function aoResponder(ResponseEvent $evento): void
    {
        if (!$evento->isMainRequest()) {
            return;
        }

        $resposta = $evento->getResponse();
        $cabecalhos = $resposta->headers;

        $cabecalhos->set('X-Content-Type-Options', 'nosniff');
        $cabecalhos->set('X-Frame-Options', 'DENY');
        $cabecalhos->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cabecalhos->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $cabecalhos->set('Cross-Origin-Opener-Policy', 'same-origin');
        $cabecalhos->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            ."font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        );

        if ($evento->getRequest()->isSecure()) {
            $cabecalhos->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        $cabecalhos->remove('X-Powered-By');

        // O X-Powered-By também é posto pelo próprio PHP quando expose_php
        // está ligado, e aí ele não passa pelo objeto de resposta. Em imagem
        // nossa o php.ini desliga; em plataforma de terceiro, não temos o
        // php.ini, então sai daqui.
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }
    }
}
