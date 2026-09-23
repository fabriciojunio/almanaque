<?php

declare(strict_types=1);

namespace App\Infraestrutura\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Carimba a requisição com um identificador e devolve ele no cabeçalho.
 *
 * É a peça que liga as três pontas do atendimento: o cliente vê o código na
 * tela de erro, o chamado guarda o código, e o log tem a linha com o mesmo
 * código. Sem isso, investigar um chamado é procurar por horário, e horário
 * de cliente em outro fuso não ajuda.
 */
final class IdentificadorDeRequisicao
{
    public const CABECALHO = 'X-Request-Id';

    private string $atual = '';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
    public function aoReceber(RequestEvent $evento): void
    {
        if (!$evento->isMainRequest()) {
            return;
        }

        $requisicao = $evento->getRequest();
        $recebido = (string) $requisicao->headers->get(self::CABECALHO, '');

        // Identificador vindo de fora é aceito, porque é assim que o rastro
        // atravessa o balanceador e os serviços. Mas só no formato esperado:
        // ele vai para o log e para a tela, e texto livre de fora não entra
        // em nenhum dos dois.
        $this->atual = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $recebido)
            ? $recebido
            : 'req-'.substr(str_replace('-', '', Uuid::v4()->toRfc4122()), 0, 12);

        $requisicao->headers->set(self::CABECALHO, $this->atual);
        $requisicao->attributes->set('identificador_de_requisicao', $this->atual);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function aoResponder(ResponseEvent $evento): void
    {
        if ($evento->isMainRequest() && '' !== $this->atual) {
            $evento->getResponse()->headers->set(self::CABECALHO, $this->atual);
        }
    }

    public function atual(): string
    {
        return $this->atual;
    }
}
