<?php

declare(strict_types=1);

namespace App\Aplicacao\Assinatura;

/**
 * O que a rotina de cobrança fez, em números.
 *
 * É o que o comando imprime no terminal e o que vai para o log, e é o que o
 * suporte lê na manhã seguinte para saber se a noite foi normal.
 */
final class ResumoDaCobranca
{
    private int $pagas = 0;
    private int $recusadas = 0;
    private int $jaCobradas = 0;
    private int $falhas = 0;
    private int $totalEmCentavos = 0;

    public function paga(int $centavos): void
    {
        ++$this->pagas;
        $this->totalEmCentavos += $centavos;
    }

    public function recusada(): void
    {
        ++$this->recusadas;
    }

    public function jaCobrada(): void
    {
        ++$this->jaCobradas;
    }

    public function falhou(): void
    {
        ++$this->falhas;
    }

    public function pagas(): int
    {
        return $this->pagas;
    }

    public function recusadas(): int
    {
        return $this->recusadas;
    }

    public function jaCobradas(): int
    {
        return $this->jaCobradas;
    }

    public function falhas(): int
    {
        return $this->falhas;
    }

    public function totalEmCentavos(): int
    {
        return $this->totalEmCentavos;
    }

    public function totalEmReais(): string
    {
        return number_format($this->totalEmCentavos / 100, 2, ',', '.');
    }

    public function processadas(): int
    {
        return $this->pagas + $this->recusadas + $this->jaCobradas + $this->falhas;
    }

    /** @return array<string, int|string> */
    public function paraLog(): array
    {
        return [
            'pagas' => $this->pagas,
            'recusadas' => $this->recusadas,
            'ja_cobradas' => $this->jaCobradas,
            'falhas' => $this->falhas,
            'total' => $this->totalEmReais(),
        ];
    }
}
