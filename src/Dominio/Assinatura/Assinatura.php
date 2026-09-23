<?php

declare(strict_types=1);

namespace App\Dominio\Assinatura;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Assinatura\Repositorio\AssinaturaRepositorio;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A assinatura que mantém um anúncio no ar.
 *
 * Toda a regra de cobrança recorrente está aqui: quando cobrar, o que fazer
 * quando o pagamento é recusado, quanto tempo o anúncio continua no ar em
 * atraso e quando a assinatura morre. É a parte do sistema que mexe com
 * dinheiro, e chamado sobre dinheiro fura a fila do suporte, então ela é
 * também a parte com mais teste.
 */
#[ORM\Entity(repositoryClass: AssinaturaRepositorio::class)]
#[ORM\Table(name: 'assinaturas')]
#[ORM\Index(name: 'assinatura_proxima_cobranca', columns: ['situacao', 'proxima_cobranca_em'])]
class Assinatura
{
    /** Depois disso, a assinatura é cancelada e o anúncio sai do guia. */
    public const TENTATIVAS_ANTES_DE_CANCELAR = 3;

    /** Dias entre uma tentativa recusada e a seguinte. */
    public const DIAS_ENTRE_TENTATIVAS = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Anuncio::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Anuncio $anuncio;

    #[ORM\ManyToOne(targetEntity: Plano::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Plano $plano;

    #[ORM\Column(length: 20, enumType: SituacaoAssinatura::class)]
    private SituacaoAssinatura $situacao = SituacaoAssinatura::ATIVA;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $proximaCobrancaEm;

    #[ORM\Column]
    private int $tentativasSeguidasRecusadas = 0;

    /** @var Collection<int, Cobranca> */
    #[ORM\OneToMany(mappedBy: 'assinatura', targetEntity: Cobranca::class, cascade: ['persist'])]
    #[ORM\OrderBy(['competencia' => 'DESC'])]
    private Collection $cobrancas;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $criadaEm;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $canceladaEm = null;

    public function __construct(Anuncio $anuncio, Plano $plano, \DateTimeImmutable $primeiraCobranca)
    {
        if ($plano->portal() !== $anuncio->portal()) {
            throw new \InvalidArgumentException('O plano é de outro portal.');
        }

        $this->anuncio = $anuncio;
        $this->plano = $plano;
        $this->proximaCobrancaEm = $primeiraCobranca->setTime(0, 0);
        $this->cobrancas = new ArrayCollection();
        $this->criadaEm = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function anuncio(): Anuncio
    {
        return $this->anuncio;
    }

    public function plano(): Plano
    {
        return $this->plano;
    }

    public function situacao(): SituacaoAssinatura
    {
        return $this->situacao;
    }

    public function proximaCobrancaEm(): \DateTimeImmutable
    {
        return $this->proximaCobrancaEm;
    }

    public function tentativasSeguidasRecusadas(): int
    {
        return $this->tentativasSeguidasRecusadas;
    }

    /** @return Collection<int, Cobranca> */
    public function cobrancas(): Collection
    {
        return $this->cobrancas;
    }

    public function criadaEm(): \DateTimeImmutable
    {
        return $this->criadaEm;
    }

    public function canceladaEm(): ?\DateTimeImmutable
    {
        return $this->canceladaEm;
    }

    public function estaVencida(\DateTimeImmutable $agora = new \DateTimeImmutable()): bool
    {
        return SituacaoAssinatura::CANCELADA !== $this->situacao
            && $this->proximaCobrancaEm <= $agora->setTime(0, 0);
    }

    /**
     * Abre a cobrança do ciclo, ou devolve a que já existe.
     *
     * A competência (ano e mês) é a chave: rodar a rotina duas vezes no mesmo
     * dia, o que acontece quando alguém repete o comando na mão, não pode
     * cobrar o anunciante duas vezes.
     */
    public function abrirCobranca(\DateTimeImmutable $agora = new \DateTimeImmutable()): Cobranca
    {
        if (SituacaoAssinatura::CANCELADA === $this->situacao) {
            throw new AssinaturaCanceladaException();
        }

        $competencia = $this->proximaCobrancaEm->format('Y-m');

        foreach ($this->cobrancas as $cobranca) {
            if ($cobranca->competencia() === $competencia && !$cobranca->foiRecusada()) {
                return $cobranca;
            }
        }

        $cobranca = new Cobranca(
            $this,
            $this->plano->precoEmCentavos(),
            $competencia,
            $agora,
        );

        $this->cobrancas->add($cobranca);

        return $cobranca;
    }

    /**
     * Confirma o pagamento: zera o atraso, empurra o ciclo e renova a
     * publicação do anúncio pelo prazo contratado.
     */
    public function confirmarPagamento(Cobranca $cobranca): void
    {
        if ($cobranca->assinatura() !== $this) {
            throw new \InvalidArgumentException('A cobrança é de outra assinatura.');
        }

        $cobranca->marcarComoPaga();

        $this->situacao = SituacaoAssinatura::ATIVA;
        $this->tentativasSeguidasRecusadas = 0;
        $this->proximaCobrancaEm = $this->proximaCobrancaEm->modify(
            sprintf('+%d months', $this->plano->ciclo()->meses())
        );

        $this->anuncio->publicar($this->proximaCobrancaEm, $this->plano->incluiDestaque());
    }

    /**
     * Registra a recusa. O anúncio continua no ar enquanto houver tentativa,
     * porque tirar o guia do cliente do ar por causa de um cartão vencido é
     * pior para as duas partes do que esperar três dias.
     */
    public function registrarRecusa(Cobranca $cobranca, string $motivo): void
    {
        if ($cobranca->assinatura() !== $this) {
            throw new \InvalidArgumentException('A cobrança é de outra assinatura.');
        }

        $cobranca->marcarComoRecusada($motivo);
        ++$this->tentativasSeguidasRecusadas;

        if ($this->tentativasSeguidasRecusadas >= self::TENTATIVAS_ANTES_DE_CANCELAR) {
            $this->cancelar();

            return;
        }

        $this->situacao = SituacaoAssinatura::INADIMPLENTE;
        $this->proximaCobrancaEm = $this->proximaCobrancaEm->modify(
            sprintf('+%d days', self::DIAS_ENTRE_TENTATIVAS)
        );
    }

    public function cancelar(): void
    {
        if (SituacaoAssinatura::CANCELADA === $this->situacao) {
            return;
        }

        $this->situacao = SituacaoAssinatura::CANCELADA;
        $this->canceladaEm = new \DateTimeImmutable();
        $this->anuncio->retirarDoAr();
    }

    public function totalPagoEmCentavos(): int
    {
        $total = 0;

        foreach ($this->cobrancas as $cobranca) {
            if ($cobranca->foiPaga()) {
                $total += $cobranca->valorEmCentavos();
            }
        }

        return $total;
    }
}
