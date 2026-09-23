<?php

declare(strict_types=1);

namespace App\Dominio\Anuncio;

use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Portal\Portal;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * O cadastro do guia: uma empresa, um profissional ou um classificado.
 *
 * O ciclo de vida é o que mais gera chamado em produto de diretório, então
 * ele fica todo aqui e não espalhado em controller: rascunho, publicação com
 * prazo, expiração e recusa.
 */
#[ORM\Entity(repositoryClass: AnuncioRepositorio::class)]
#[ORM\Table(name: 'anuncios')]
#[ORM\Index(name: 'anuncio_portal_situacao', columns: ['portal_id', 'situacao'])]
#[ORM\Index(name: 'anuncio_expira_em', columns: ['expira_em'])]
#[ORM\UniqueConstraint(name: 'anuncio_apelido_portal', columns: ['portal_id', 'apelido'])]
class Anuncio
{
    public const DIAS_DE_AVISO_ANTES_DE_EXPIRAR = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Portal::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 150)]
    private string $titulo;

    #[ORM\Column(length: 160)]
    private string $apelido;

    #[ORM\Column(type: Types::TEXT)]
    private string $descricao;

    #[ORM\ManyToOne(targetEntity: Categoria::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Categoria $categoria;

    #[ORM\Column(length: 20, enumType: SituacaoAnuncio::class)]
    private SituacaoAnuncio $situacao = SituacaoAnuncio::RASCUNHO;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $endereco = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $bairro = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $cidade = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $site = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $imagens = [];

    #[ORM\Column]
    private bool $destaque = false;

    #[ORM\Column]
    private int $visualizacoes = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publicadoEm = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiraEm = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $atualizadoEm;

    public function __construct(
        Portal $portal,
        string $titulo,
        string $apelido,
        string $descricao,
        Categoria $categoria,
    ) {
        // Comparação por objeto, não por id: em entidade recém-criada os dois
        // ids ainda são nulos, e nulo diferente de nulo é falso, o que deixava
        // a verificação passar justamente no caso que ela existe para pegar.
        if ($categoria->portal() !== $portal) {
            throw new \InvalidArgumentException('A categoria é de outro portal.');
        }

        $this->portal = $portal;
        $this->titulo = $titulo;
        $this->apelido = $apelido;
        $this->descricao = $descricao;
        $this->categoria = $categoria;
        $this->criadoEm = new \DateTimeImmutable();
        $this->atualizadoEm = $this->criadoEm;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function portal(): Portal
    {
        return $this->portal;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function apelido(): string
    {
        return $this->apelido;
    }

    public function descricao(): string
    {
        return $this->descricao;
    }

    public function categoria(): Categoria
    {
        return $this->categoria;
    }

    public function situacao(): SituacaoAnuncio
    {
        return $this->situacao;
    }

    public function endereco(): ?string
    {
        return $this->endereco;
    }

    public function bairro(): ?string
    {
        return $this->bairro;
    }

    public function cidade(): ?string
    {
        return $this->cidade;
    }

    public function latitude(): ?float
    {
        return $this->latitude;
    }

    public function longitude(): ?float
    {
        return $this->longitude;
    }

    public function telefone(): ?string
    {
        return $this->telefone;
    }

    public function site(): ?string
    {
        return $this->site;
    }

    /** @return list<string> */
    public function imagens(): array
    {
        return $this->imagens;
    }

    public function ehDestaque(): bool
    {
        return $this->destaque;
    }

    public function visualizacoes(): int
    {
        return $this->visualizacoes;
    }

    public function publicadoEm(): ?\DateTimeImmutable
    {
        return $this->publicadoEm;
    }

    public function expiraEm(): ?\DateTimeImmutable
    {
        return $this->expiraEm;
    }

    public function criadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function atualizadoEm(): \DateTimeImmutable
    {
        return $this->atualizadoEm;
    }

    public function estaNoGuia(): bool
    {
        return $this->situacao->apareceNoGuia();
    }

    public function alterarDados(
        string $titulo,
        string $descricao,
        Categoria $categoria,
    ): void {
        if ($categoria->portal() !== $this->portal) {
            throw new \InvalidArgumentException('A categoria é de outro portal.');
        }

        $this->titulo = $titulo;
        $this->descricao = $descricao;
        $this->categoria = $categoria;
        $this->tocar();
    }

    public function alterarContato(
        ?string $telefone,
        ?string $site,
        ?string $endereco,
        ?string $bairro,
        ?string $cidade,
    ): void {
        $this->telefone = $telefone;
        $this->site = $site;
        $this->endereco = $endereco;
        $this->bairro = $bairro;
        $this->cidade = $cidade;
        $this->tocar();
    }

    public function situarNoMapa(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Coordenada fora do planeta.');
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->tocar();
    }

    /** @param array<int|string, string> $imagens */
    public function definirImagens(array $imagens): void
    {
        $this->imagens = array_values($imagens);
        $this->tocar();
    }

    /**
     * Publica por um prazo. Republicar um anúncio expirado é o caminho normal
     * da renovação, então não é erro: só empurra a data.
     */
    public function publicar(\DateTimeImmutable $ate, bool $destaque = false): void
    {
        if (SituacaoAnuncio::RECUSADO === $this->situacao) {
            throw new AnuncioRecusadoException($this->titulo);
        }

        if ($ate <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('O prazo de publicação precisa ser no futuro.');
        }

        $this->situacao = SituacaoAnuncio::PUBLICADO;
        $this->publicadoEm ??= new \DateTimeImmutable();
        $this->expiraEm = $ate;
        $this->destaque = $destaque;
        $this->tocar();
    }

    public function recusar(): void
    {
        $this->situacao = SituacaoAnuncio::RECUSADO;
        $this->destaque = false;
        $this->tocar();
    }

    public function expirar(\DateTimeImmutable $agora = new \DateTimeImmutable()): bool
    {
        if (SituacaoAnuncio::PUBLICADO !== $this->situacao) {
            return false;
        }

        if (null === $this->expiraEm || $this->expiraEm > $agora) {
            return false;
        }

        $this->situacao = SituacaoAnuncio::EXPIRADO;
        $this->destaque = false;
        $this->tocar();

        return true;
    }

    /**
     * Tira do guia na hora, sem esperar o prazo.
     *
     * É o que acontece quando a assinatura é cancelada por falta de
     * pagamento: o prazo pago ainda não venceu, mas o direito de estar no ar
     * acabou.
     */
    public function retirarDoAr(): void
    {
        if (SituacaoAnuncio::PUBLICADO !== $this->situacao) {
            return;
        }

        $this->situacao = SituacaoAnuncio::EXPIRADO;
        $this->destaque = false;
        $this->expiraEm = new \DateTimeImmutable();
        $this->tocar();
    }

    public function vaiExpirarEm(\DateTimeImmutable $agora = new \DateTimeImmutable()): ?int
    {
        if (SituacaoAnuncio::PUBLICADO !== $this->situacao || null === $this->expiraEm) {
            return null;
        }

        // Dia de calendário, não tempo decorrido. Quem publicou agora com
        // prazo de três dias quer ler "faltam 3 dias", e não "faltam 2", que
        // é o que sai quando a conta é por 24 horas cheias.
        $dias = (int) $agora->setTime(0, 0)->diff($this->expiraEm->setTime(0, 0))->days;

        return $dias <= self::DIAS_DE_AVISO_ANTES_DE_EXPIRAR ? $dias : null;
    }

    public function registrarVisualizacao(): void
    {
        ++$this->visualizacoes;
    }

    private function tocar(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
    }
}
