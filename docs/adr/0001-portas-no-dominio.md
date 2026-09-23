# 1. O domínio declara a porta, a infraestrutura implementa

Data: 2026-09-23 · Status: aceito

## Contexto

Três dependências externas importam aqui: o Elasticsearch, que é o motor de
busca; o adquirente, que cobra; e o armazenamento da imagem do anúncio. Todas
as três são caras de subir na máquina de quem programa e no CI.

## Decisão

Cada uma vira uma interface no domínio (`MotorDeBusca`, `Indexador`,
`Gateway`), com pelo menos duas implementações na infraestrutura. Quem decide
qual entra é o container, olhando a variável de ambiente.

## Consequências

O sistema inteiro roda sem Elasticsearch: a busca cai para o banco e o
indexador não faz nada. A bateria de testes não precisa de serviço no ar, o
que a deixa em seis segundos.

O preço é uma camada de indireção onde poderia haver chamada direta, e a
tentação de testar só contra a implementação falsa. Por isso existe um teste
de integração contra um Elasticsearch de verdade, que se pula fora do CI, e um
passo do CI que reprova se esses testes se pularem lá.
