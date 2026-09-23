# Como se atende um chamado aqui

Este documento é o roteiro de quem pega o chamado. Ele descreve o que o
console cobra, e o porquê de cobrar.

## As quatro caixas

Todo chamado cai em uma delas, e o console não deixa resolver sem escolher.

| Caixa | O que significa | Quem resolve |
| --- | --- | --- |
| **Dúvida de uso** | O produto faz o que deveria; falta mostrar o caminho | Suporte, respondendo |
| **Erro de configuração** | O produto está certo, o ambiente do cliente está errado | Suporte, ajustando |
| **Defeito do produto** | O produto está errado, e provavelmente para todo mundo na mesma versão | Desenvolvimento |
| **Customização do cliente** | A alteração é do cliente, e o problema some num ambiente limpo | O cliente, com orientação |

As três primeiras são o manual de qualquer suporte de produto. A quarta existe
porque o cliente mexe no tema do portal dele, e **a pergunta que separa as
quatro é sempre a mesma: acontece num ambiente limpo, sem as alterações dele?**

Por isso o console tem a marcação "acontece em ambiente limpo" separada de
"consegui reproduzir": reproduzir no ambiente do cliente não prova nada sobre
o produto.

## O roteiro

### 1. Ler o relato e pedir o que falta, de forma específica

"Manda mais detalhes" gera mais uma ida e volta. O que fecha o caso numa volta
só é pedir coisa que a pessoa consegue responder sem ser técnica:

- o que você clicou
- o que apareceu na tela, com o texto exato ou a captura
- que horas foi, com o fuso
- qual usuário estava logado
- e, se apareceu um código na tela, qual era

O último é o que mais economiza tempo, e é o assunto da próxima seção.

### 2. Achar a requisição pelo identificador

Toda resposta do Almanaque sai com `X-Request-Id`, e o chamado guarda esse
código. É por ele que se acha a linha no log:

```bash
kubectl logs -n almanaque -l app=almanaque-api --since=6h | grep req-3f81ac92
```

Sem o código, sobra procurar por horário, e horário de cliente em outro fuso
não ajuda. Com o e-mail da conta e a hora aproximada ainda dá, mas é o caminho
longo.

### 3. Conferir a versão do portal

O chamado guarda a versão que o portal rodava **quando ele entrou**, e não a
de agora. Isso importa porque clientes diferentes ficam em versões diferentes
entre duas janelas de atualização.

Antes de investigar do zero, o console já mostra os problemas conhecidos que
**ainda afetam aquela versão**. Um defeito corrigido na 2.5.0 não aparece para
quem está na 2.5.0, e aparece para quem está na 2.4.0. A comparação usa
`version_compare`, porque comparar versão como texto coloca a 2.10 antes da
2.9 e manda a resposta errada para o cliente.

### 4. Reproduzir

Num portal limpo primeiro, se possível. Se reproduziu lá, é defeito do
produto. Se só reproduz no do cliente, olhe o que ele alterou antes de
escrever qualquer linha de código.

### 5. Olhar onde o dado está

O anúncio não aparece na busca? Três lugares, nesta ordem:

```sql
-- 1. O anúncio está publicado mesmo?
SELECT id, titulo, situacao, publicado_em, expira_em
FROM anuncios WHERE portal_id = ? AND apelido = ?;

-- 2. A assinatura está em dia?
SELECT a.situacao, a.proxima_cobranca_em, a.tentativas_seguidas_recusadas
FROM assinaturas a WHERE a.anuncio_id = ?;

-- 3. A última cobrança foi recusada?
SELECT competencia, situacao, motivo_da_recusa
FROM cobrancas WHERE assinatura_id = ? ORDER BY id DESC LIMIT 3;
```

Se os três estiverem certos, o problema é o índice, e a resposta é reindexar:

```bash
php bin/console almanaque:reindexar bauru
```

### 6. Classificar, responder e registrar

A anotação interna é onde ficam a consulta que rodou, a linha do log e a
hipótese descartada. Ela não chega ao cliente, e é o que faz diferença quando
o mesmo chamado volta seis meses depois, com outra pessoa atendendo.

Defeito novo vira problema conhecido, com o **sintoma escrito como o cliente
descreve**, não como o time descreve. A busca da base procura palavra por
palavra: quem abre o chamado escreve "não aparece na busca", e o registro diz
"demora a aparecer no índice". Se o sintoma estiver no vocabulário do time,
ninguém encontra.

## Prioridade é impacto, não urgência declarada

Todo chamado chega urgente. O que fura a fila é o que derruba o portal ou
trava dinheiro, porque nesses dois o prejuízo corre por minuto.

| Prioridade | Quando | Primeira resposta |
| --- | --- | --- |
| **Crítica** | Portal fora do ar | 1 hora |
| **Alta** | Cobrança, pagamento, assinatura | 4 horas |
| **Normal** | O resto | 24 horas |
| **Baixa** | Dúvida sem bloqueio | 72 horas |

O sistema já abre o chamado com a prioridade lida do relato, procurando os
sinais de parada ("fora do ar", "erro 500", "ninguém consegue") e de dinheiro
("cobrança", "cartão", "fatura"). Quem atende pode mudar, e a mudança fica
registrada com o motivo.

O relógio do prazo **para** enquanto a bola está com o cliente. Chamado
esperando resposta do cliente não conta contra o suporte.

## Quando o índice cai

A busca tem reserva: se o Elasticsearch não responde, o guia continua
respondendo pelo banco, com relevância pior. Três coisas acontecem juntas:

1. O resultado sai marcado, e a página avisa o visitante.
2. O log recebe um alerta com o erro e o portal.
3. O rodapé do resultado diz qual motor respondeu.

Então, se o cliente abrir chamado dizendo que "a busca piorou", a primeira
coisa a olhar é `/rest/saude`: `busca: false` explica tudo.

## Publicação de versão

Publicar é a parte fácil. O que dá trabalho é responder, meia hora depois, se
o ambiente do cliente ficou de pé. Por isso a publicação só termina depois das
verificações, e uma reprovada devolve o portal para a versão anterior na hora,
sem esperar o cliente reclamar.

As verificações são as perguntas que alguém faria na mão: o portal está ativo,
o guia tem anúncio publicado, as categorias carregam, a busca responde.

## O que não fazer

- Fechar chamado sem classificar. O console não deixa, e o motivo é este
  documento inteiro.
- Classificar como defeito sem ter reproduzido. O console também não deixa.
- Prometer prazo de correção sem falar com o desenvolvimento.
- Responder "vou verificar" e sumir. Dizer quando volta vale mais que
  responder rápido.
