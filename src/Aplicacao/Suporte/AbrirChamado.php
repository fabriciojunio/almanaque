<?php

declare(strict_types=1);

namespace App\Aplicacao\Suporte;

use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\Prioridade;
use App\Dominio\Suporte\ProblemaConhecido;
use App\Dominio\Suporte\Repositorio\ChamadoRepositorio;
use App\Dominio\Suporte\Repositorio\ProblemaConhecidoRepositorio;
use Psr\Log\LoggerInterface;

/**
 * Abre o chamado e já faz o trabalho de triagem que dá para fazer sozinho.
 *
 * Duas coisas acontecem na entrada. A prioridade sai do impacto do que foi
 * relatado, e não do adjetivo que o cliente usou: todo chamado chega urgente.
 * E o sistema procura o sintoma na base de problemas conhecidos, porque em
 * produto usado por milhares de clientes o caso já aconteceu antes, e achar
 * isso no primeiro minuto é o que separa uma resposta de dez minutos de uma
 * investigação de dois dias.
 */
final readonly class AbrirChamado
{
    /** Palavra no relato que indica o portal fora do ar. */
    private const SINAIS_DE_PARADA = [
        'fora do ar', 'não abre', 'nao abre', 'site caiu', 'erro 500',
        'página em branco', 'pagina em branco', 'ninguém consegue', 'ninguem consegue',
    ];

    /** Palavra no relato que indica dinheiro travado. */
    private const SINAIS_DE_DINHEIRO = [
        'cobrança', 'cobranca', 'cobrado', 'pagamento', 'cartão', 'cartao',
        'assinatura', 'fatura', 'estorno', 'duplicad',
    ];

    public function __construct(
        private ChamadoRepositorio $chamados,
        private ProblemaConhecidoRepositorio $problemas,
        private LoggerInterface $log,
    ) {
    }

    public function executar(
        Portal $portal,
        string $titulo,
        string $relato,
        string $autor,
        ?string $identificadorDeRequisicao = null,
    ): ResultadoDaAbertura {
        $chamado = new Chamado(
            portal: $portal,
            titulo: $titulo,
            relato: $relato,
            autor: $autor,
            prioridade: $this->prioridadePeloImpacto($titulo.' '.$relato),
            identificadorDeRequisicao: $identificadorDeRequisicao,
        );

        $this->chamados->salvar($chamado);

        $parecidos = $this->problemasParecidos($titulo, $portal->versao());

        $this->log->info('Chamado aberto.', [
            'chamado' => $chamado->id(),
            'portal' => $portal->apelido(),
            'prioridade' => $chamado->prioridade()->value,
            'problemas_parecidos' => count($parecidos),
            'requisicao' => $identificadorDeRequisicao,
        ]);

        return new ResultadoDaAbertura($chamado, $parecidos);
    }

    /**
     * Portal fora do ar é crítico, dinheiro é alto, o resto é normal. A
     * reclassificação continua na mão de quem atende, e fica registrada.
     */
    private function prioridadePeloImpacto(string $texto): Prioridade
    {
        $texto = mb_strtolower($texto);

        foreach (self::SINAIS_DE_PARADA as $sinal) {
            if (str_contains($texto, $sinal)) {
                return Prioridade::CRITICA;
            }
        }

        foreach (self::SINAIS_DE_DINHEIRO as $sinal) {
            if (str_contains($texto, $sinal)) {
                return Prioridade::ALTA;
            }
        }

        return Prioridade::NORMAL;
    }

    /**
     * Só devolve problema que ainda afeta a versão que o portal roda. Sugerir
     * um defeito já corrigido na versão do cliente manda o analista para o
     * lado errado logo no começo.
     *
     * @return list<ProblemaConhecido>
     */
    private function problemasParecidos(string $titulo, string $versao): array
    {
        return array_values(array_filter(
            $this->problemas->procurar($titulo),
            static fn (ProblemaConhecido $problema) => $problema->afeta($versao),
        ));
    }
}
