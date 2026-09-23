<?php

declare(strict_types=1);

namespace App\Interface\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SegurancaController extends AbstractController
{
    #[Route('/entrar', name: 'entrar', methods: ['GET', 'POST'])]
    public function entrar(AuthenticationUtils $utilitarios): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('inicio');
        }

        return $this->render('seguranca/entrar.html.twig', [
            // A mensagem que chega aqui é sempre a genérica do Symfony. Dizer
            // se o e-mail existe entregaria a lista de quem tem conta.
            'erro' => $utilitarios->getLastAuthenticationError(),
            'ultimoEmail' => $utilitarios->getLastUsername(),
        ]);
    }

    #[Route('/sair', name: 'sair', methods: ['GET'])]
    public function sair(): never
    {
        throw new \LogicException('Esta rota é tratada pelo firewall.');
    }
}
