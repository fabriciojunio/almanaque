<?php

declare(strict_types=1);

namespace App\Interface\Console;

use App\Aplicacao\Assinatura\RodarCobrancas;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A rotina diária de cobrança, para rodar no agendador.
 *
 * Tem ensaio (--ensaio) porque a primeira vez que alguém roda isso em
 * produção, com dinheiro de cliente do outro lado, ninguém quer descobrir na
 * hora que a data estava errada.
 */
#[AsCommand(
    name: 'almanaque:cobrar',
    description: 'Cobra as assinaturas vencidas e trata as recusas',
)]
final class CobrarAssinaturasComando extends Command
{
    public function __construct(private readonly RodarCobrancas $cobrancas)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dia', null, InputOption::VALUE_REQUIRED, 'Data de referência (AAAA-MM-DD)')
            ->addOption('lote', null, InputOption::VALUE_REQUIRED, 'Quantas assinaturas por execução', '100')
            ->addOption('ensaio', null, InputOption::VALUE_NONE, 'Só mostra o que faria');
    }

    protected function execute(InputInterface $entrada, OutputInterface $saida): int
    {
        $tela = new SymfonyStyle($entrada, $saida);
        $dia = new \DateTimeImmutable((string) ($entrada->getOption('dia') ?: 'today'));
        $lote = max(1, (int) $entrada->getOption('lote'));

        if ($entrada->getOption('ensaio')) {
            $tela->warning(sprintf(
                'Ensaio: nada será cobrado. Referência %s, lote de %d.',
                $dia->format('d/m/Y'),
                $lote,
            ));

            return Command::SUCCESS;
        }

        $tela->title('Cobrança de '.$dia->format('d/m/Y'));

        $resumo = $this->cobrancas->executar($dia, $lote);

        $tela->definitionList(
            ['Processadas' => (string) $resumo->processadas()],
            ['Pagas' => (string) $resumo->pagas()],
            ['Recusadas' => (string) $resumo->recusadas()],
            ['Já cobradas' => (string) $resumo->jaCobradas()],
            ['Falhas' => (string) $resumo->falhas()],
            ['Total recebido' => 'R$ '.$resumo->totalEmReais()],
        );

        if ($resumo->falhas() > 0) {
            $tela->error(sprintf(
                '%d assinatura(s) falharam. O log tem o identificador de cada uma.',
                $resumo->falhas(),
            ));

            return Command::FAILURE;
        }

        $tela->success('Cobrança encerrada.');

        return Command::SUCCESS;
    }
}
