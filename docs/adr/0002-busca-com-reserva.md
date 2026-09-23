# 2. A busca tem reserva, e o resultado diz quem respondeu

Data: 2026-09-23 · Status: aceito

## Contexto

O guia é o produto do cliente. Se a busca parar, o portal dele vira uma lista
de categorias.

## Decisão

Um decorador tenta o Elasticsearch e, se ele falhar, responde pela busca do
banco. A falha vira alerta no log com o portal e o erro, e o resultado sai
marcado com `comReserva`.

## Consequências

Índice fora vira degradação, não parada. A página avisa o visitante com todas
as letras, e o rodapé do resultado diz qual motor respondeu.

O suporte ganha a informação mais útil possível: ao abrir o chamado "a busca
piorou", `/rest/saude` já responde se o índice estava fora.

O que se perde é a chance de perceber a queda pelo sintoma: o guia continua
funcionando, e sem o alerta no log ninguém olharia. Por isso o alerta é em
nível `alert`, e não `warning`.

Foi descartado tentar o Elasticsearch de novo antes de cair para a reserva:
quando ele está fora, a segunda tentativa só adiciona o tempo de espera à
página do visitante.
