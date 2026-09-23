<?php

declare(strict_types=1);

namespace App\Infraestrutura\Busca;

use App\Dominio\Anuncio\Repositorio\AnuncioRepositorio;
use App\Dominio\Anuncio\Repositorio\CategoriaRepositorio;
use App\Dominio\Busca\Consulta;
use App\Dominio\Busca\ItemEncontrado;
use App\Dominio\Busca\MotorDeBusca;
use App\Dominio\Busca\Resultado;

/**
 * A busca de reserva, direto no MySQL.
 *
 * Não tem relevância, não corrige acento e não sugere o que o visitante quis
 * dizer. Serve para o guia continuar de pé quando o Elasticsearch cai, e o
 * rodapé do resultado diz que está em modo reserva, porque o cliente merece
 * saber por que a busca ficou pior hoje.
 */
final readonly class BuscaNoBanco implements MotorDeBusca
{
    public function __construct(
        private AnuncioRepositorio $anuncios,
        private CategoriaRepositorio $categorias,
    ) {
    }

    public function buscar(Consulta $consulta): Resultado
    {
        $categoria = null !== $consulta->categoria
            ? $this->categorias->porApelido($consulta->portal, $consulta->categoria)
            : null;

        $encontrados = $this->anuncios->buscar(
            portal: $consulta->portal,
            termo: $consulta->termoLimpo(),
            categoria: $categoria,
            cidade: $consulta->cidade,
            limite: $consulta->tamanhoDaPagina,
            deslocamento: $consulta->deslocamento(),
        );

        if ($consulta->somenteDestaques) {
            $encontrados = array_values(array_filter(
                $encontrados,
                static fn ($anuncio) => $anuncio->ehDestaque(),
            ));
        }

        $itens = array_map(
            static fn ($anuncio) => new ItemEncontrado(
                id: (int) $anuncio->id(),
                titulo: $anuncio->titulo(),
                apelido: $anuncio->apelido(),
                resumo: mb_substr($anuncio->descricao(), 0, 180),
                categoria: $anuncio->categoria()->nome(),
                cidade: $anuncio->cidade(),
                telefone: $anuncio->telefone(),
                destaque: $anuncio->ehDestaque(),
                imagem: $anuncio->imagens()[0] ?? null,
            ),
            $encontrados,
        );

        return new Resultado(
            itens: $itens,
            total: $this->contar($consulta, count($itens)),
            facetasPorCategoria: $this->facetas($consulta),
            motor: $this->nome(),
        );
    }

    public function nome(): string
    {
        return 'banco';
    }

    public function estaDisponivel(): bool
    {
        return true;
    }

    /**
     * Sem termo, o total é a contagem do guia. Com termo, contar exige uma
     * segunda consulta que varre o texto inteiro, e o número serve só para a
     * paginação: aproximar pela página atual custa zero e erra pouco.
     */
    private function contar(Consulta $consulta, int $nestaPagina): int
    {
        if (!$consulta->temTermo()) {
            return $this->anuncios->contarNoGuia($consulta->portal);
        }

        return $consulta->deslocamento() + $nestaPagina;
    }

    /** @return array<string, int> */
    private function facetas(Consulta $consulta): array
    {
        $contagem = $this->anuncios->contagemPorCategoria($consulta->portal);
        $facetas = [];

        foreach ($this->categorias->doPortal($consulta->portal) as $categoria) {
            $quantidade = $contagem[(int) $categoria->id()] ?? 0;
            if ($quantidade > 0) {
                $facetas[$categoria->nome()] = $quantidade;
            }
        }

        arsort($facetas);

        return $facetas;
    }
}
