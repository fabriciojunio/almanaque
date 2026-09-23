<?php

declare(strict_types=1);

namespace App\Dominio\Suporte;

use App\Dominio\Suporte\Repositorio\ProblemaConhecidoRepositorio;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Um defeito que já foi investigado uma vez.
 *
 * Em produto usado por milhares de clientes, metade do trabalho do suporte é
 * reconhecer que aquele chamado é um caso já visto. Por isso o problema
 * guarda o sintoma pelas palavras do cliente, e não só pelas do time: é assim
 * que a busca encontra.
 *
 * As versões importam: um portal pode estar rodando uma versão anterior à da
 * correção, e aí a resposta não é "já foi corrigido", é "já foi corrigido, e
 * você precisa atualizar".
 */
#[ORM\Entity(repositoryClass: ProblemaConhecidoRepositorio::class)]
#[ORM\Table(name: 'problemas_conhecidos')]
#[ORM\UniqueConstraint(name: 'problema_codigo', columns: ['codigo'])]
class ProblemaConhecido
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Código curto para citar no chamado e na conversa: PC-014. */
    #[ORM\Column(length: 20)]
    private string $codigo;

    #[ORM\Column(length: 160)]
    private string $titulo;

    /** O sintoma como o cliente descreve, não como o time descreve. */
    #[ORM\Column(type: Types::TEXT)]
    private string $sintoma;

    #[ORM\Column(type: Types::TEXT)]
    private string $causa;

    /** O que dizer ao cliente hoje, enquanto a correção não chega nele. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contorno = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $versaoAfetadaDe = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $corrigidoNaVersao = null;

    /** @var Collection<int, Chamado> */
    #[ORM\OneToMany(mappedBy: 'problemaConhecido', targetEntity: Chamado::class)]
    private Collection $chamados;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $registradoEm;

    public function __construct(
        string $codigo,
        string $titulo,
        string $sintoma,
        string $causa,
        ?string $versaoAfetadaDe = null,
    ) {
        $this->codigo = $codigo;
        $this->titulo = $titulo;
        $this->sintoma = $sintoma;
        $this->causa = $causa;
        $this->versaoAfetadaDe = $versaoAfetadaDe;
        $this->chamados = new ArrayCollection();
        $this->registradoEm = new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function sintoma(): string
    {
        return $this->sintoma;
    }

    public function causa(): string
    {
        return $this->causa;
    }

    public function contorno(): ?string
    {
        return $this->contorno;
    }

    public function versaoAfetadaDe(): ?string
    {
        return $this->versaoAfetadaDe;
    }

    public function corrigidoNaVersao(): ?string
    {
        return $this->corrigidoNaVersao;
    }

    /** @return Collection<int, Chamado> */
    public function chamados(): Collection
    {
        return $this->chamados;
    }

    public function registradoEm(): \DateTimeImmutable
    {
        return $this->registradoEm;
    }

    public function quantidadeDeChamados(): int
    {
        return $this->chamados->count();
    }

    public function anotarContorno(string $contorno): void
    {
        $this->contorno = $contorno;
    }

    public function marcarCorrigidoNa(string $versao): void
    {
        $this->corrigidoNaVersao = $versao;
    }

    public function estaCorrigido(): bool
    {
        return null !== $this->corrigidoNaVersao;
    }

    /**
     * Diz se um portal numa dada versão ainda pega este problema.
     *
     * A comparação usa version_compare, que entende 2.10 como maior que 2.9.
     * Comparar versão como texto é o erro clássico: "2.10" < "2.9" em ordem
     * alfabética, e a resposta ao cliente sai errada.
     */
    public function afeta(string $versao): bool
    {
        if (null !== $this->versaoAfetadaDe && version_compare($versao, $this->versaoAfetadaDe, '<')) {
            return false;
        }

        if (null === $this->corrigidoNaVersao) {
            return true;
        }

        return version_compare($versao, $this->corrigidoNaVersao, '<');
    }
}
