# 6. O guia é renderizado no servidor

Data: 2026-09-23 · Status: aceito

## Contexto

O guia existe para ser encontrado. Quem chega nele veio do buscador,
procurando por "chaveiro perto de mim", e quase sempre num celular com rede
ruim.

## Decisão

Twig no servidor, sem framework de tela. O JavaScript da página é zero.

## Consequências

A primeira tela chega pronta, sem esperar por um pacote de JavaScript, e o
buscador lê o conteúdo sem precisar executar nada.

O console de suporte também é formulário e recarga. É uma tela de trabalho
usada o dia inteiro, e não ter estado no navegador significa não ter estado
para se perder quando a rede oscila.

O preço aparece nas interações que ficariam melhores sem recarregar a página,
como a marcação de "acontece em ambiente limpo". Aceitável: são poucos cliques
por chamado, e o ganho de simplicidade vale.

Se um dia a triagem virar uma tela de muitos passos, a decisão se revê por
partes, e não trocando a base.
