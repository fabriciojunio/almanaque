<?php

declare(strict_types=1);

namespace App\Aplicacao\Anuncio;

use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Busca\Indexador;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tira do guia o que passou do prazo.
 *
 * Roda depois da cobrança, e não antes: quem pagou hoje de manhã teve o prazo
 * empurrado e não pode ser expirado à tarde.
 */
final readonly class ExpirarAnuncios
{
    public function __construct(
        private AnuncioRepositorio $anuncios,
        private Indexador $indexador,
        private EntityManagerInterface $gerenciador,
        private LoggerInterface $log,
    ) {
    }

    public function executar(\DateTimeImmutable $agora = new \DateTimeImmutable(), int $lote = 100): int
    {
        $expirados = 0;

        foreach ($this->anuncios->comPrazoVencido($agora, $lote) as $anuncio) {
            if (!$anuncio->expirar($agora)) {
                continue;
            }

            ++$expirados;
            $this->indexador->remover($anuncio);
        }

        $this->gerenciador->flush();

        if ($expirados > 0) {
            $this->log->info('Anúncios expirados.', ['quantidade' => $expirados]);
        }

        return $expirados;
    }
}
