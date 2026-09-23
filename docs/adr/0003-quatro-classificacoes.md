# 3. Chamado tem quatro caixas, e não três

Data: 2026-09-23 · Status: aceito

## Contexto

O manual de suporte de produto separa chamado em três: dúvida de uso, erro de
configuração e defeito do produto. Num produto onde o cliente mexe no tema e
no código do portal dele, essa separação deixa de fora o caso mais comum de
discussão: o comportamento estranho que o próprio cliente causou.

## Decisão

A quarta caixa é **customização do cliente**, e ela só pode ser escolhida em
portal marcado como customizado. O chamado guarda, separadamente, se foi
reproduzido e se acontece em ambiente limpo.

## Consequências

A pergunta que separa as quatro fica explícita na tela, e a resposta fica
gravada: "acontece num ambiente limpo, sem as alterações dele?".

Chamado não se resolve sem classificação, e não se classifica como defeito sem
ter sido reproduzido. As duas regras estão na entidade, não no controller, e
têm teste.

O custo é uma etapa a mais para quem atende. É deliberado: chamado fechado sem
dizer o que era é o que impede descobrir, três meses depois, que o mesmo
defeito voltou.
