# 5. As fontes são servidas do próprio servidor

Data: 2026-09-23 · Status: aceito

## Contexto

A política de conteúdo do Almanaque só libera `self` em `style-src`. A folha
do Google Fonts é um estilo de outro domínio, e caía na diretiva.

O sintoma era silencioso: o navegador descartava a folha, a página continuava
abrindo, e o site inteiro era desenhado com as fontes de reserva do sistema.
Nenhum teste de rota reclamava. Quem descobriu foi um teste de navegador que
conta erro no console.

## Decisão

Os arquivos woff2 dos subconjuntos latin e latin-ext ficam em
`public/fontes`, com o `@font-face` em `public/estilos/fontes.css`.

## Consequências

A política continua sem exceção de domínio, que era a alternativa e teria
enfraquecido justamente a proteção que mais vale nesta aplicação.

O endereço de rede de todo visitante do guia deixa de passar por um terceiro,
o que também é um problema de privacidade, não só de segurança.

A página abre igual com internet ruim ou dentro de rede fechada, e a fonte não
depende de um serviço que não é nosso.

O custo é 720 KB no repositório e a obrigação de refazer o download quando
quiser trocar uma fonte. O ganho é maior.
