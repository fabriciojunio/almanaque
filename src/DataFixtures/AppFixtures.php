<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Dominio\Anuncio\Anuncio;
use App\Dominio\Anuncio\Categoria;
use App\Dominio\Assinatura\Assinatura;
use App\Dominio\Assinatura\Ciclo;
use App\Dominio\Assinatura\Plano;
use App\Dominio\Identidade\Papel;
use App\Dominio\Identidade\Usuario;
use App\Dominio\Portal\Portal;
use App\Dominio\Suporte\Chamado;
use App\Dominio\Suporte\Classificacao;
use App\Dominio\Suporte\Prioridade;
use App\Dominio\Suporte\ProblemaConhecido;
use App\Dominio\Suporte\Publicacao;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Os dados da demonstração.
 *
 * Dois portais de propósito: um deles customizado pelo cliente, que é o que
 * permite mostrar a quarta classificação de chamado. E um problema conhecido
 * já corrigido numa versão, para o portal atrasado ainda pegar o defeito e o
 * outro não.
 */
class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $cifrador)
    {
    }

    public function load(ObjectManager $gerenciador): void
    {
        $bauru = new Portal('Guia de Bauru', 'bauru', '2.4.0', 'guiadebauru.com.br');
        $bauru->marcarComoCustomizado();

        $vale = new Portal('Vale Negócios', 'vale', '2.5.0', 'valenegocios.com.br');

        $gerenciador->persist($bauru);
        $gerenciador->persist($vale);

        $this->criarEquipe($gerenciador, $bauru, $vale);
        $this->criarGuia($gerenciador, $bauru);
        $this->criarGuia($gerenciador, $vale);
        $this->criarSuporte($gerenciador, $bauru, $vale);

        $gerenciador->flush();
    }

    private function criarEquipe(ObjectManager $gerenciador, Portal $bauru, Portal $vale): void
    {
        $contas = [
            ['suporte@almanaque.com.br', 'Fabrício, do suporte', Papel::SUPORTE, null],
            ['admin@almanaque.com.br', 'Administração', Papel::ADMINISTRADOR, null],
            ['dono@guiadebauru.com.br', 'Helena Prado', Papel::DONO_DO_PORTAL, $bauru],
            ['dono@valenegocios.com.br', 'Rogério Tavares', Papel::DONO_DO_PORTAL, $vale],
            ['anunciante@guiadebauru.com.br', 'Marina Aguiar', Papel::ANUNCIANTE, $bauru],
        ];

        foreach ($contas as [$email, $nome, $papel, $portal]) {
            $usuario = new Usuario($email, $nome, $papel, $portal);
            $usuario->definirSenha($this->cifrador->hashPassword($usuario, 'demonstracao2026'));
            $gerenciador->persist($usuario);
        }
    }

    private function criarGuia(ObjectManager $gerenciador, Portal $portal): void
    {
        $basico = new Plano($portal, 'Básico', 4900, Ciclo::MENSAL);
        $destaque = new Plano($portal, 'Destaque', 9900, Ciclo::MENSAL, incluiDestaque: true, limiteDeImagens: 8);
        $anual = new Plano($portal, 'Anual', 99000, Ciclo::ANUAL, incluiDestaque: true, limiteDeImagens: 8);

        $gerenciador->persist($basico);
        $gerenciador->persist($destaque);
        $gerenciador->persist($anual);

        $cidade = 'bauru' === $portal->apelido() ? 'Bauru' : 'Jaú';

        $categorias = [];
        foreach ([
            ['Alimentação', 'alimentacao', ['Padarias' => 'padarias', 'Restaurantes' => 'restaurantes']],
            ['Serviços', 'servicos', ['Assistência técnica' => 'assistencia-tecnica', 'Construção' => 'construcao']],
            ['Saúde', 'saude', []],
            ['Automotivo', 'automotivo', []],
        ] as [$nome, $apelido, $filhas]) {
            $raiz = new Categoria($portal, $nome, $apelido);
            $gerenciador->persist($raiz);
            $categorias[$apelido] = $raiz;

            foreach ($filhas as $nomeFilha => $apelidoFilha) {
                $filha = new Categoria($portal, $nomeFilha, $apelidoFilha, $raiz);
                $gerenciador->persist($filha);
                $categorias[$apelidoFilha] = $filha;
            }
        }

        $anuncios = [
            ['Padaria Estrela', 'padarias', 'Pão quente das cinco da manhã às oito da noite, e bolo de fubá todo dia.', '(14) 3234-1010', true],
            ['Restaurante Dona Zefa', 'restaurantes', 'Comida caseira no self-service, com almoço executivo de segunda a sexta.', '(14) 3234-2020', true],
            ['Mecânica do Tião', 'automotivo', 'Injeção eletrônica, suspensão e revisão completa. Trinta anos no mesmo ponto.', '(14) 3234-3030', false],
            ['Eletro Assistência Santos', 'assistencia-tecnica', 'Conserto de geladeira, máquina de lavar e micro-ondas, com atendimento em casa.', '(14) 3234-4040', false],
            ['Materiais São Jorge', 'construcao', 'Cimento, areia, tijolo e ferragem, com entrega no mesmo dia.', '(14) 3234-5050', false],
            ['Clínica Vida Plena', 'saude', 'Clínico geral, pediatria e exames laboratoriais, com convênios.', '(14) 3234-6060', true],
            ['Sorveteria Gelo Bom', 'alimentacao', 'Sorvete de massa artesanal, açaí e picolé de fruta.', '(14) 3234-7070', false],
            ['Auto Peças Central', 'automotivo', 'Peças originais e paralelas para linha leve, com balcão e entrega.', '(14) 3234-8080', false],
        ];

        foreach ($anuncios as $posicao => [$titulo, $categoria, $descricao, $telefone, $ehDestaque]) {
            if (!isset($categorias[$categoria])) {
                throw new \LogicException(sprintf('O anúncio "%s" aponta para a categoria "%s", que não existe nos dados de exemplo.', $titulo, $categoria));
            }

            $anuncio = new Anuncio(
                $portal,
                $titulo,
                $this->apelido($titulo),
                $descricao,
                $categorias[$categoria],
            );

            $anuncio->alterarContato($telefone, null, 'Rua Exemplo, '.(100 + $posicao * 7), 'Centro', $cidade);
            $anuncio->situarNoMapa(-22.3145 + $posicao * 0.004, -49.0605 + $posicao * 0.003);

            $plano = $ehDestaque ? $destaque : $basico;
            $assinatura = new Assinatura($anuncio, $plano, new \DateTimeImmutable('-1 day'));
            $assinatura->confirmarPagamento($assinatura->abrirCobranca(new \DateTimeImmutable('-1 day')));

            $gerenciador->persist($anuncio);
            $gerenciador->persist($assinatura);
        }
    }

    private function criarSuporte(ObjectManager $gerenciador, Portal $bauru, Portal $vale): void
    {
        $indiceAtrasado = new ProblemaConhecido(
            'PC-001',
            'Anúncio novo demora a aparecer na busca',
            'publiquei o anúncio e ele não aparece quando eu procuro pelo nome',
            'A reindexação rodava só na virada do dia, então o anúncio ficava fora da busca até a madrugada seguinte.',
            '2.3.0',
        );
        $indiceAtrasado->anotarContorno('Reindexar o portal na mão pelo console de suporte resolve na hora.');
        $indiceAtrasado->marcarCorrigidoNa('2.5.0');

        $cobrancaDuplicada = new ProblemaConhecido(
            'PC-002',
            'Cobrança repetida quando a rotina roda duas vezes',
            'fui cobrado duas vezes no mesmo mês',
            'A rotina não tinha chave de idempotência por competência e abria uma cobrança nova a cada execução.',
            '2.2.0',
        );
        $cobrancaDuplicada->marcarCorrigidoNa('2.4.0');

        // Um defeito ainda sem correção, que é o caso que o suporte convive
        // com contorno enquanto o desenvolvimento não chega.
        $imagemGrande = new ProblemaConhecido(
            'PC-003',
            'Foto grande demais derruba o envio do anúncio',
            'tento subir a foto da loja e a tela fica carregando e não termina',
            'O redimensionamento roda na mesma requisição do envio e estoura o tempo limite com imagem acima de 8 MB.',
            '2.4.0',
        );
        $imagemGrande->anotarContorno('Peça ao cliente para subir a imagem com no máximo 2000 pixels de largura.');

        $gerenciador->persist($indiceAtrasado);
        $gerenciador->persist($cobrancaDuplicada);
        $gerenciador->persist($imagemGrande);

        // Chamado em aberto, no portal atrasado, que ainda pega o PC-001.
        $aberto = new Chamado(
            $bauru,
            'Publiquei ontem e não acho na busca',
            'Cadastrei a Sorveteria Gelo Bom ontem à tarde, o anúncio aparece no meu painel como publicado, mas quando eu procuro por sorveteria não vem nada.',
            'helena@guiadebauru.com.br',
            Prioridade::NORMAL,
            'req-3f81ac92',
            new \DateTimeImmutable('-3 hours'),
        );
        $gerenciador->persist($aberto);

        // Chamado crítico, com o portal fora do ar.
        $critico = new Chamado(
            $vale,
            'O site está fora do ar desde as 9h',
            'Ninguém consegue abrir o portal, dá erro 500 em qualquer página.',
            'rogerio@valenegocios.com.br',
            Prioridade::CRITICA,
            'req-7d20bb14',
            new \DateTimeImmutable('-40 minutes'),
        );
        $critico->assumir('Fabrício, do suporte');
        $critico->anotar('Fabrício, do suporte', 'Estou olhando agora, já identifiquei o horário no log.', true);
        $critico->anotar('Fabrício, do suporte', 'O erro começa junto com a publicação da 2.5.0.', false);
        $gerenciador->persist($critico);

        // Chamado já resolvido, classificado como customização do cliente.
        $customizacao = new Chamado(
            $bauru,
            'O botão de contato sumiu do anúncio',
            'Depois da última atualização o botão de WhatsApp não aparece mais nos anúncios.',
            'helena@guiadebauru.com.br',
            Prioridade::ALTA,
            'req-91c0ee37',
            new \DateTimeImmutable('-2 days'),
        );
        $customizacao->assumir('Fabrício, do suporte');
        $customizacao->registrarReproducao(emAmbienteLimpo: false);
        $customizacao->anotar(
            'Fabrício, do suporte',
            'Num portal sem alteração de tema o botão aparece. O tema do cliente sobrescreve o bloco de contato.',
            false,
        );
        $customizacao->anotar(
            'Fabrício, do suporte',
            'O botão está escondido por uma alteração no tema do portal. Mandei o trecho para ajustar.',
            true,
        );
        $customizacao->classificar(Classificacao::CUSTOMIZACAO_DO_CLIENTE);
        $customizacao->resolver(new \DateTimeImmutable('-1 day'));
        $gerenciador->persist($customizacao);

        $publicacao = new Publicacao($vale, '2.6.0', 'Fabrício, do suporte');
        $publicacao->registrarVerificacao('portal está ativo', true);
        $publicacao->registrarVerificacao('guia tem anúncio publicado', true);
        $publicacao->registrarVerificacao('categorias carregam', true);
        $publicacao->registrarVerificacao('busca responde', false);
        $publicacao->reverter('A busca parou de responder logo depois da subida.');
        $gerenciador->persist($publicacao);
    }

    private function apelido(string $titulo): string
    {
        $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT', $titulo) ?: $titulo;
        $apelido = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $semAcento) ?? '');

        return trim($apelido, '-');
    }
}
