<?php

declare(strict_types=1);

namespace App\Infraestrutura\Http;

use App\Dominio\Portal\Portal;
use App\Dominio\Portal\Repositorio\PortalRepositorio;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Transforma o apelido do portal na URL no portal de verdade.
 *
 * Fica aqui, e não em cada controller, por causa de duas decisões que
 * precisam valer para o sistema inteiro: portal que não existe responde 404,
 * e portal suspenso também. Responder "suspenso" contaria, a quem está
 * varrendo endereços, quais apelidos existem.
 */
final readonly class ResolvedorDePortal implements ValueResolverInterface
{
    public function __construct(private PortalRepositorio $portais)
    {
    }

    /**
     * @return iterable<Portal>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Portal::class !== $argument->getType()) {
            return [];
        }

        $apelido = $request->attributes->get('portal');

        if (!is_string($apelido) || '' === $apelido) {
            return [];
        }

        $portal = $this->portais->porApelido($apelido);

        if (null === $portal || !$portal->estaNoAr()) {
            throw new NotFoundHttpException(sprintf('Guia "%s" não encontrado.', $apelido));
        }

        return [$portal];
    }
}
