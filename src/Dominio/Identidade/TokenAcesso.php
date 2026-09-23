<?php

declare(strict_types=1);

namespace App\Dominio\Identidade;

use App\Dominio\Identidade\Repositorio\TokenAcessoRepositorio;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Token de acesso à API.
 *
 * O banco guarda só o hash. Vazamento de dump não vira acesso, e nem o
 * administrador consegue ler o token de alguém: se a pessoa perdeu, gera
 * outro. É a mesma razão de não guardar senha em claro.
 */
#[ORM\Entity(repositoryClass: TokenAcessoRepositorio::class)]
#[ORM\Table(name: 'tokens_acesso')]
#[ORM\UniqueConstraint(name: 'token_hash', columns: ['hash'])]
class TokenAcesso
{
    private const DIAS_DE_VALIDADE = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Usuario $usuario;

    #[ORM\Column(length: 64)]
    private string $hash;

    #[ORM\Column(length: 80)]
    private string $descricao;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiraEm;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revogadoEm = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usadoEm = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $criadoEm;

    private function __construct(Usuario $usuario, string $hash, string $descricao)
    {
        $this->usuario = $usuario;
        $this->hash = $hash;
        $this->descricao = $descricao;
        $this->criadoEm = new \DateTimeImmutable();
        $this->expiraEm = $this->criadoEm->modify(sprintf('+%d days', self::DIAS_DE_VALIDADE));
    }

    /**
     * Gera o token e devolve o par: a entidade, que vai para o banco, e o
     * segredo em claro, que só existe neste retorno.
     *
     * @return array{0: self, 1: string}
     */
    public static function gerar(Usuario $usuario, string $descricao): array
    {
        $segredo = bin2hex(random_bytes(32));

        return [new self($usuario, self::cifrar($segredo), $descricao), $segredo];
    }

    public static function cifrar(string $segredo): string
    {
        return hash('sha256', $segredo);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function usuario(): Usuario
    {
        return $this->usuario;
    }

    public function descricao(): string
    {
        return $this->descricao;
    }

    public function expiraEm(): \DateTimeImmutable
    {
        return $this->expiraEm;
    }

    public function usadoEm(): ?\DateTimeImmutable
    {
        return $this->usadoEm;
    }

    public function criadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function estaValido(\DateTimeImmutable $agora = new \DateTimeImmutable()): bool
    {
        return null === $this->revogadoEm
            && $this->expiraEm > $agora
            && $this->usuario->estaAtivo();
    }

    public function registrarUso(): void
    {
        $this->usadoEm = new \DateTimeImmutable();
    }

    public function revogar(): void
    {
        $this->revogadoEm ??= new \DateTimeImmutable();
    }
}
