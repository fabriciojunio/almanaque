<?php

declare(strict_types=1);

namespace App\Tests\Unidade\Busca;

use App\Dominio\Busca\Consulta;
use App\Dominio\Busca\ItemEncontrado;
use App\Dominio\Busca\MotorDeBusca;
use App\Dominio\Busca\Resultado;
use App\Dominio\Portal\Portal;
use App\Infraestrutura\Busca\MotorComReserva;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class MotorComReservaTest extends TestCase
{
    private Consulta $consulta;

    protected function setUp(): void
    {
        $this->consulta = new Consulta(
            new Portal('Guia de Bauru', 'bauru', '2.4.0'),
            termo: 'padaria',
        );
    }

    #[Test]
    public function com_o_motor_principal_de_pe_a_reserva_nao_e_chamada(): void
    {
        $principal = $this->motorQueResponde('elasticsearch', ['Padaria Estrela']);
        $reserva = $this->motorQueExplode('banco');

        $resultado = (new MotorComReserva($principal, $reserva, new NullLogger()))
            ->buscar($this->consulta);

        $this->assertSame('elasticsearch', $resultado->motor);
        $this->assertFalse($resultado->comReserva);
        $this->assertCount(1, $resultado->itens);
    }

    #[Test]
    public function com_o_indice_fora_o_guia_continua_respondendo(): void
    {
        $principal = $this->motorQueExplode('elasticsearch');
        $reserva = $this->motorQueResponde('banco', ['Padaria Estrela', 'Padaria do Bairro']);

        $resultado = (new MotorComReserva($principal, $reserva, new NullLogger()))
            ->buscar($this->consulta);

        $this->assertSame('banco', $resultado->motor);
        $this->assertTrue($resultado->comReserva, 'a página precisa saber que está em modo reserva');
        $this->assertCount(2, $resultado->itens);
    }

    #[Test]
    public function a_queda_do_indice_vira_alerta_no_log_com_o_erro_e_o_portal(): void
    {
        $log = new class extends AbstractLogger {
            /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
            public array $registros = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->registros[] = [(string) $level, (string) $message, $context];
            }
        };

        $motor = new MotorComReserva(
            $this->motorQueExplode('elasticsearch', 'connection refused'),
            $this->motorQueResponde('banco', []),
            $log,
        );

        $motor->buscar($this->consulta);

        $this->assertCount(1, $log->registros);
        [$nivel, , $contexto] = $log->registros[0];
        $this->assertSame('alert', $nivel);
        $this->assertSame('connection refused', $contexto['erro']);
        $this->assertSame('bauru', $contexto['portal']);
    }

    #[Test]
    public function o_sistema_esta_disponivel_enquanto_um_dos_dois_responder(): void
    {
        $motor = new MotorComReserva(
            $this->motorIndisponivel('elasticsearch'),
            $this->motorQueResponde('banco', []),
            new NullLogger(),
        );

        $this->assertTrue($motor->estaDisponivel());
    }

    /** @param list<string> $titulos */
    private function motorQueResponde(string $nome, array $titulos): MotorDeBusca
    {
        return new class($nome, $titulos) implements MotorDeBusca {
            /** @param list<string> $titulos */
            public function __construct(private string $nome, private array $titulos)
            {
            }

            public function buscar(Consulta $consulta): Resultado
            {
                $itens = array_map(
                    static fn (string $titulo, int $posicao) => new ItemEncontrado(
                        $posicao + 1,
                        $titulo,
                        'apelido-'.$posicao,
                        'resumo',
                        'Padarias',
                        'Bauru',
                        null,
                        false,
                    ),
                    $this->titulos,
                    array_keys($this->titulos),
                );

                return new Resultado($itens, count($itens), [], $this->nome);
            }

            public function nome(): string
            {
                return $this->nome;
            }

            public function estaDisponivel(): bool
            {
                return true;
            }
        };
    }

    private function motorQueExplode(string $nome, string $erro = 'index_not_found'): MotorDeBusca
    {
        return new class($nome, $erro) implements MotorDeBusca {
            public function __construct(private string $nome, private string $erro)
            {
            }

            public function buscar(Consulta $consulta): Resultado
            {
                throw new \RuntimeException($this->erro);
            }

            public function nome(): string
            {
                return $this->nome;
            }

            public function estaDisponivel(): bool
            {
                return false;
            }
        };
    }

    private function motorIndisponivel(string $nome): MotorDeBusca
    {
        return $this->motorQueExplode($nome);
    }
}
