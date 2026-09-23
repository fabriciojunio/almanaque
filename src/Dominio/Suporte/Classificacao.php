<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

/**
 * As quatro caixas em que todo chamado cai.
 *
 * As três primeiras são as do manual de qualquer suporte de produto. A quarta
 * existe porque o cliente tem acesso ao código e ao tema do portal dele: um
 * comportamento estranho pode ser alteração feita por ele, e não defeito do
 * produto. A pergunta que separa as quatro é sempre a mesma: acontece num
 * ambiente limpo, sem as alterações dele?
 */
enum Classificacao: string
{
    case DUVIDA_DE_USO = 'duvida_de_uso';
    case CONFIGURACAO = 'configuracao';
    case DEFEITO = 'defeito';
    case CUSTOMIZACAO_DO_CLIENTE = 'customizacao_do_cliente';

    public function rotulo(): string
    {
        return match ($this) {
            self::DUVIDA_DE_USO => 'Dúvida de uso',
            self::CONFIGURACAO => 'Erro de configuração',
            self::DEFEITO => 'Defeito do produto',
            self::CUSTOMIZACAO_DO_CLIENTE => 'Customização do cliente',
        };
    }

    public function explicacao(): string
    {
        return match ($this) {
            self::DUVIDA_DE_USO => 'O produto faz o que deveria; falta mostrar o caminho.',
            self::CONFIGURACAO => 'O produto está certo e o ambiente do cliente está errado.',
            self::DEFEITO => 'O produto está errado, e provavelmente para todo mundo na mesma versão.',
            self::CUSTOMIZACAO_DO_CLIENTE => 'A alteração é do cliente, e o problema some num ambiente limpo.',
        };
    }

    /** Defeito é o único que gera trabalho para o time de desenvolvimento. */
    public function viraTarefaDeDesenvolvimento(): bool
    {
        return self::DEFEITO === $this;
    }
}
