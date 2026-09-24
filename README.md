# Almanaque

[![CI](https://github.com/fabriciojunio/almanaque/actions/workflows/ci.yml/badge.svg)](https://github.com/fabriciojunio/almanaque/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![Elasticsearch](https://img.shields.io/badge/Elasticsearch-9-005571?logo=elasticsearch&logoColor=white)](https://elastic.co)
[![Licença](https://img.shields.io/badge/licença-MIT-black)](LICENSE)

**No ar: <https://almanaque-ecru.vercel.app>** — com dados de demonstração e as
contas de acesso em [docs/DEMONSTRACAO.md](docs/DEMONSTRACAO.md).

Plataforma para publicar guias de empresas e classificados. Cada cliente tem o
portal dele: categorias próprias, anunciantes próprios, assinatura própria. O
Almanaque é o que roda embaixo de todos eles.

Junto com o produto vem o **console de quem atende**, que é a parte incomum
deste repositório: a fila de chamados ordenada por impacto, a triagem em
quatro caixas, a base de problemas conhecidos e o registro das publicações de
versão com a conferência que vem depois.

---

## O que o sistema faz

| Parte | O que resolve |
| --- | --- |
| **Guia** | Categorias em dois níveis, anúncio com contato e mapa, destaque pago |
| **Busca** | Elasticsearch com relevância e acento, e reserva no banco quando o índice cai |
| **Assinatura** | Cobrança recorrente, inadimplência com três tentativas e cancelamento |
| **Multi-inquilino** | Um portal não enxerga o dado do outro, e isso tem teste |
| **API** | Guia público, chamados autenticados, token com hash no banco |
| **Suporte** | Fila por impacto, triagem em quatro caixas, problemas conhecidos, publicações |

Três regras que o sistema não deixa furar, e que têm teste dos dois lados:

1. **Chamado não se resolve sem classificação.** Chamado fechado sem dizer o
   que era é o que impede descobrir, três meses depois, que o mesmo defeito
   voltou.
2. **Não se classifica como defeito sem ter reproduzido.** E a pergunta que
   separa defeito do produto de alteração do cliente é sempre a mesma:
   acontece num ambiente limpo?
3. **A rotina de cobrança pode rodar duas vezes sem cobrar duas vezes.** A
   competência do ciclo é a chave.

## Stack

PHP 8.3 · Symfony 7.4 · Doctrine ORM · MySQL 8 · Elasticsearch 9 · Twig ·
Redis · Docker · Kubernetes · S3 (imagens) · PHPUnit · Playwright · PHPStan
nível 8 · PHP-CS-Fixer

## Rodar

```bash
cp .env.example .env.local        # e preencha as senhas
docker compose up -d
docker compose exec api php bin/console doctrine:migrations:migrate -n
docker compose exec api php bin/console doctrine:fixtures:load -n
docker compose exec api php bin/console almanaque:reindexar
```

O guia abre em <http://localhost:8080>, o console de suporte em
<http://localhost:8080/suporte> e a sonda em
<http://localhost:8080/rest/saude>.

Entrar com `suporte@almanaque.com.br` e a senha `demonstracao2026`. As outras
contas de exemplo estão em [docs/DEMONSTRACAO.md](docs/DEMONSTRACAO.md).

Sem Docker, dá para subir só a aplicação: a bateria de testes roda em SQLite e
a busca cai para o banco quando não há Elasticsearch. Ver
[CONTRIBUTING.md](CONTRIBUTING.md).

## Arquitetura

Quatro camadas, com o domínio no centro e sem nada do framework dentro dele:

```
Interface        Controllers (Api e Web) · Comandos de console · Templates
Aplicação        Casos de uso · Resumos
Domínio          Entidades · regras · as portas (interfaces)
Infraestrutura   Doctrine · Elasticsearch · gateway de pagamento · HTTP
```

A regra que sustenta o desenho: **o domínio declara a porta e a
infraestrutura implementa**. `MotorDeBusca`, `Indexador` e `Gateway` são
interfaces do domínio; quem decide qual implementação entra é o container, na
partida. É o que permite o sistema inteiro rodar sem Elasticsearch e sem
adquirente, e é o que faz a bateria de testes não precisar de serviço no ar.

Detalhes em [docs/ARQUITETURA.md](docs/ARQUITETURA.md), e as decisões com o
contexto de cada uma em [docs/adr/](docs/adr/).

## Testes

```bash
make testar     # 157 testes: unidade, integração e HTTP
make e2e        # 46 testes de navegador, no desktop e no celular
make revisar    # PHP-CS-Fixer e PHPStan nível 8
```

Quatro níveis, e cada um existe por um motivo:

- **Unidade**: as regras do domínio, sem banco e sem HTTP.
- **Integração**: a rotina de cobrança contra o banco, e a busca contra um
  Elasticsearch de verdade (que se pula sozinha fora do CI).
- **HTTP**: o caminho inteiro, incluindo as regras de acesso.
- **Navegador**: o que só aparece na tela.

O CI roda a bateria três vezes: em SQLite, em MySQL 8 e com Elasticsearch no
ar. E confere que os testes de busca não se pularam, porque um trabalho verde
que pulou tudo é pior que um vermelho.

**O que os testes acharam, e o uso não tinha achado:** as fontes nunca
carregavam, porque a política de conteúdo bloqueava a folha do Google; a barra
do console não cabia num celular; e o aviso de "expira em três dias" dizia
dois.

## Segurança

Token com hash SHA-256 no banco e o segredo aparecendo uma vez só. Resposta de
login igual para e-mail inexistente e senha errada, com o mesmo tempo. Cinco
tentativas por minuto. Isolamento entre inquilinos em votante, não em tela.
Política de conteúdo sem `unsafe-inline`, o que obrigou o CSS a sair dos
templates e as fontes a virem daqui. Imagem de produção sem Composer, sem
Xdebug e sem root.

O raciocínio inteiro, com o que ficou de fora e por quê, está em
[docs/SEGURANCA.md](docs/SEGURANCA.md).

## Suporte

[docs/SUPORTE.md](docs/SUPORTE.md) é o roteiro de quem atende: como separar
dúvida de uso, erro de configuração, defeito do produto e customização do
cliente; como achar a requisição pelo identificador; e o que fazer quando o
índice cai no meio do expediente.

## Visual

A referência é o almanaque impresso e a lista telefônica: papel de jornal,
coluna estreita separada por filete, título pesado e condensado, texto em
serifada, e o amarelo da lista como única cor forte. O guia inteiro é
renderizado no servidor, sem framework de tela, porque a página precisa abrir
rápido em celular ruim e ser lida pelo buscador.

O guia, com o que ficou de fora de propósito, está em
[docs/IDENTIDADE_VISUAL.md](docs/IDENTIDADE_VISUAL.md).

## Operação

[docs/IMPLANTACAO.md](docs/IMPLANTACAO.md) para subir, inclusive no Kubernetes
e com o S3 local. [docs/RUNBOOK.md](docs/RUNBOOK.md) para quando alguma coisa
vai mal com o sistema no ar.

## Licença

MIT. Ver [LICENSE](LICENSE).
