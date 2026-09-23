<?php

declare(strict_types=1);

namespace App\Aplicacao\Suporte;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Publicacao;
use App\Dominio\Suporte\Repositorio\PublicacaoRepositorio;
use Psr\Log\LoggerInterface;

/**
 * Sobe uma versão no portal e roda a conferência depois.
 *
 * A lista de verificações não é decorativa: é a resposta para a pergunta que
 * o cliente faz meia hora depois da atualização, que é se o ambiente dele
 * ficou de pé. Verificação reprovada devolve o portal para a versão anterior
 * na hora, sem esperar o cliente reclamar.
 */
final readonly class PublicarVersao
{
    public function __construct(
        private PublicacaoRepositorio $publicacoes,
        private VerificarAmbiente $verificar,
        private LoggerInterface $log,
    ) {
    }

    public function executar(Portal $portal, string $versao, string $responsavel): Publicacao
    {
        $publicacao = new Publicacao($portal, $versao, $responsavel);

        foreach ($this->verificar->executar($portal) as $nome => $aprovada) {
            $publicacao->registrarVerificacao($nome, $aprovada);
        }

        if ($publicacao->todasAsVerificacoesPassaram()) {
            $publicacao->concluir();
            $this->log->info('Versão publicada e ambiente conferido.', [
                'portal' => $portal->apelido(),
                'versao' => $versao,
            ]);
        } else {
            $reprovadas = implode(', ', $publicacao->verificacoesReprovadas());
            $publicacao->reverter('Verificação reprovada: '.$reprovadas);
            $this->log->alert('Publicação revertida.', [
                'portal' => $portal->apelido(),
                'versao' => $versao,
                'reprovadas' => $publicacao->verificacoesReprovadas(),
            ]);
        }

        $this->publicacoes->salvar($publicacao);

        return $publicacao;
    }
}
