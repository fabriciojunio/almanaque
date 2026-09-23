<?php

declare(strict_types=1);

namespace App\Dominio\Portal;

use App\Dominio\Portal\Repositorio\PortalRepositorio;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Um portal é o guia de um cliente: almanaque.com/bauru e almanaque.com/vale
 * são dois portais, com anúncio, categoria e assinatura separados.
 *
 * É o inquilino. Toda consulta do produto filtra por ele, e é por isso que o
 * filtro não fica espalhado pelos controllers: quem monta a consulta é o
 * repositório, que já recebe o portal.
 */
#[ORM\Entity(repositoryClass: PortalRepositorio::class)]
#[ORM\Table(name: 'portais')]
#[ORM\UniqueConstraint(name: 'portal_apelido', columns: ['apelido'])]
#[ORM\Index(name: 'portal_dominio', columns: ['dominio'])]
class Portal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $nome;

    /** Aparece na URL: /g/{apelido}. */
    #[ORM\Column(length: 60)]
    private string $apelido;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $dominio = null;

    #[ORM\Column(length: 20, enumType: SituacaoPortal::class)]
    private SituacaoPortal $situacao = SituacaoPortal::ATIVO;

    /**
     * Versão do produto que este portal está rodando. Clientes diferentes
     * ficam em versões diferentes entre duas janelas de atualização, e sem
     * esse campo não dá para saber se o defeito relatado já foi corrigido.
     */
    #[ORM\Column(length: 20)]
    private string $versao;

    /**
     * O cliente mexeu no tema ou no código dele.
     *
     * É a quarta classificação de um chamado, além de dúvida de uso, erro de
     * configuração e defeito do produto. Quando isto é verdadeiro, a primeira
     * pergunta da investigação passa a ser se o problema acontece também num
     * ambiente limpo.
     */
    #[ORM\Column]
    private bool $customizado = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $criadoEm;

    public function __construct(string $nome, string $apelido, string $versao, ?string $dominio = null)
    {
        $this->nome = $nome;
        $this->apelido = $apelido;
        $this->versao = $versao;
        $this->dominio = $dominio;
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function apelido(): string
    {
        return $this->apelido;
    }

    public function dominio(): ?string
    {
        return $this->dominio;
    }

    public function situacao(): SituacaoPortal
    {
        return $this->situacao;
    }

    public function versao(): string
    {
        return $this->versao;
    }

    public function estaCustomizado(): bool
    {
        return $this->customizado;
    }

    public function criadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function estaNoAr(): bool
    {
        return SituacaoPortal::ATIVO === $this->situacao;
    }

    public function marcarComoCustomizado(): void
    {
        $this->customizado = true;
    }

    public function suspender(): void
    {
        $this->situacao = SituacaoPortal::SUSPENSO;
    }

    public function reativar(): void
    {
        $this->situacao = SituacaoPortal::ATIVO;
    }

    public function publicarVersao(string $versao): void
    {
        $this->versao = $versao;
    }
}
