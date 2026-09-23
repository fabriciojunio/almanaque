# 7. O prefixo da API é /rest, e não /api

Data: 2026-09-23 · Status: aceito

## Contexto

A API nasceu em `/api`, que é o nome óbvio e o que todo mundo espera. Na
publicação da demonstração, toda rota sob `/api` passou a responder 404, e
nenhuma requisição chegava ao PHP: a plataforma trata `api/` como a pasta das
funções dela e resolve esse caminho antes de olhar para as regras de reescrita
do projeto.

Foram tentadas três saídas antes desta. Declarar uma função que pegasse tudo
sob `/api` não resolve, porque o roteamento por arquivo vem primeiro. Pôr
`"handle": "filesystem"` antes da reescrita também não, pelo mesmo motivo.
Mover o ponto de entrada para fora de `api/` deixa a função sem ser detectada.

A alternativa que sobrava era não publicar em plataforma sem servidor. Mas o
ponto da demonstração é existir um endereço que o interessado abre sem
instalar nada, e o produto de verdade continua sendo o container no
Kubernetes, onde `/api` funcionaria.

## Decisão

O prefixo é `/rest` em todo o projeto: rotas, `access_control`, documentação,
coleção de exemplos e testes.

## Consequências

O caminho é o mesmo em qualquer lugar onde o sistema rode. A alternativa seria
um prefixo por ambiente, o que faria a documentação mentir para metade de quem
a lê e transformaria "qual é a URL da API?" numa pergunta com duas respostas.

`/rest` é menos convencional que `/api` e vai causar estranheza na primeira
leitura. Este documento é a resposta para ela.

A sonda de saúde mudou junto, para `/rest/saude`, e é o que o `livenessProbe`
dos manifestos e o `docker-compose` chamam.

Fica registrado que a restrição é da plataforma, não do produto. Se a
demonstração sair de lá, o prefixo pode voltar a `/api` numa troca mecânica,
mas não há motivo para fazer isso só por estética.
