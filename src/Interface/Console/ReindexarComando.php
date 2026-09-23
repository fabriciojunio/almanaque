<?php

declare(strict_types=1);

namespace App\Interface\Console;

use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Busca\Indexador;
use App\Dominio\Portal\Repositorio\PortalRepositorio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Refaz o índice de busca de um portal, ou de todos.
 *
 * É o primeiro comando que o suporte roda quando o cliente diz que publicou e
 * o anúncio não aparece na busca. Sem Elasticsearch ligado, o comando avisa e
 * não finge que fez.
 */
#[AsCommand(
    name: 'almanaque:reindexar',
    description: 'Reconstrói o índice de busca',
)]
final class ReindexarComando extends Command
{
    public function __construct(
        private readonly Indexador $indexador,
        private readonly PortalRepositorio $portais,
        private readonly AnuncioRepositorio $anuncios,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('portal', InputArgument::OPTIONAL, 'Apelido do portal; sem isso, reindexa todos');
    }

    protected function execute(InputInterface $entrada, OutputInterface $saida): int
    {
        $tela = new SymfonyStyle($entrada, $saida);
        $apelido = $entrada->getArgument('portal');

        $portais = null !== $apelido
            ? array_filter([$this->portais->porApelido((string) $apelido)])
            : $this->portais->noAr();

        if ([] === $portais) {
            $tela->error('Nenhum portal encontrado com esse apelido.');

            return Command::FAILURE;
        }

        $this->indexador->criarIndice();
        $total = 0;

        foreach ($portais as $portal) {
            $indexados = $this->indexador->reindexar($this->anuncios->doPortal($portal, 5000));
            $total += $indexados;
            $tela->writeln(sprintf('  %s: %d anúncio(s)', $portal->nome(), $indexados));
        }

        if (0 === $total) {
            $tela->warning(
                'Nada foi indexado. Sem ELASTICSEARCH_URL, a busca usa o banco e '
                .'o indexador não faz nada.'
            );

            return Command::SUCCESS;
        }

        $tela->success(sprintf('%d anúncio(s) no índice.', $total));

        return Command::SUCCESS;
    }
}
