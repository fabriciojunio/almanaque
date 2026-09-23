<?php

declare(strict_types=1);

namespace App\Interface\Console;

use App\Aplicacao\Anuncio\ExpirarAnuncios;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tira do guia o que passou do prazo.
 *
 * Roda depois da cobrança, nunca antes: quem pagou de manhã teve o prazo
 * empurrado e não pode sair do ar à tarde.
 */
#[AsCommand(
    name: 'almanaque:expirar',
    description: 'Expira os anúncios com prazo vencido e tira do índice',
)]
final class ExpirarAnunciosComando extends Command
{
    public function __construct(private readonly ExpirarAnuncios $expirar)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('lote', null, InputOption::VALUE_REQUIRED, 'Quantos anúncios por execução', '100');
    }

    protected function execute(InputInterface $entrada, OutputInterface $saida): int
    {
        $tela = new SymfonyStyle($entrada, $saida);
        $quantidade = $this->expirar->executar(new \DateTimeImmutable(), max(1, (int) $entrada->getOption('lote')));

        $tela->success(sprintf(
            '%d anúncio(s) saíram do guia por prazo vencido.',
            $quantidade,
        ));

        return Command::SUCCESS;
    }
}
