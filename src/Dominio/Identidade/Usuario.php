<?php

declare(strict_types=1);

namespace App\Dominio\Identidade;

use App\Dominio\Identidade\Repositorio\UsuarioRepositorio;
use App\Dominio\Portal\Portal;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UsuarioRepositorio::class)]
#[ORM\Table(name: 'usuarios')]
#[ORM\UniqueConstraint(name: 'usuario_email', columns: ['email'])]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 120)]
    private string $nome;

    #[ORM\Column]
    private string $senha;

    #[ORM\Column(length: 30, enumType: Papel::class)]
    private Papel $papel;

    /**
     * O portal a que a pessoa pertence. Quem é do suporte não pertence a
     * nenhum, porque atende todos.
     */
    #[ORM\ManyToOne(targetEntity: Portal::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Portal $portal = null;

    #[ORM\Column]
    private bool $ativo = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $ultimoAcesso = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $criadoEm;

    public function __construct(string $email, string $nome, Papel $papel, ?Portal $portal = null)
    {
        $normalizado = filter_var(mb_strtolower(trim($email)), FILTER_VALIDATE_EMAIL);

        if (false === $normalizado) {
            throw new \InvalidArgumentException('E-mail inválido.');
        }

        if ($papel->atravessaPortais() && null !== $portal) {
            throw new \InvalidArgumentException('Quem atravessa portais não pertence a um portal.');
        }

        if (!$papel->atravessaPortais() && null === $portal) {
            throw new \InvalidArgumentException('Este papel precisa de um portal.');
        }

        $this->email = $normalizado;
        $this->nome = $nome;
        $this->papel = $papel;
        $this->portal = $portal;
        $this->senha = '';
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function papel(): Papel
    {
        return $this->papel;
    }

    public function portal(): ?Portal
    {
        return $this->portal;
    }

    public function estaAtivo(): bool
    {
        return $this->ativo;
    }

    public function ultimoAcesso(): ?\DateTimeImmutable
    {
        return $this->ultimoAcesso;
    }

    public function criadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function definirSenha(string $senhaCifrada): void
    {
        $this->senha = $senhaCifrada;
    }

    public function registrarAcesso(): void
    {
        $this->ultimoAcesso = new \DateTimeImmutable();
    }

    public function desativar(): void
    {
        $this->ativo = false;
    }

    public function podeVer(Portal $portal): bool
    {
        return $this->papel->atravessaPortais() || $this->portal === $portal;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('Usuário gravado sem e-mail; o banco está inconsistente.');
        }

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return [$this->papel->value];
    }

    public function getPassword(): string
    {
        return $this->senha;
    }

    public function eraseCredentials(): void
    {
    }
}
