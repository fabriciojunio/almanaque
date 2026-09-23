# Runbook

O que fazer quando o sistema está no ar e alguma coisa vai mal. Escrito para
quem pega o chamado, não para quem escreveu o código.

## Antes de tudo: o identificador

Toda resposta sai com `X-Request-Id`, e o chamado guarda o código.

```bash
kubectl logs -n almanaque -l app=almanaque-api --since=6h | grep req-3f81ac92
```

Sem o código, peça a hora com minuto e o e-mail da conta.

## A sonda

```bash
curl -s https://almanaque.com.br/api/saude | jq
```

| Resposta | O que significa | Urgência |
| --- | --- | --- |
| `no ar`, tudo `true` | Aplicação inteira | Nenhuma |
| `no ar`, `busca: false` | Índice fora; o guia responde pelo banco | Hoje, não de madrugada |
| `parado`, `banco: false` | A aplicação subiu e o MySQL não responde | Agora |
| Sem resposta | Nenhum pod de pé, ou o ingress caiu | Agora |

O `busca: false` é degradação, não parada: a página avisa o visitante e o log
tem o alerta com o erro.

## Sintomas conhecidos

### "Publiquei e não aparece na busca"

O roteiro está em [SUPORTE.md](SUPORTE.md), seção 5. Resumo: confira se o
anúncio está publicado, se a assinatura está em dia, se a última cobrança não
foi recusada, e só então reindexe.

```bash
kubectl exec -n almanaque deploy/almanaque-api -- \
  php bin/console almanaque:reindexar bauru
```

### "Fui cobrado duas vezes"

Não deveria acontecer: a cobrança é idempotente pela competência.

```sql
SELECT competencia, situacao, valor_em_centavos, identificador_no_gateway, aberta_em
FROM cobrancas WHERE assinatura_id = ? ORDER BY id DESC;
```

Duas linhas pagas na mesma competência significam defeito de verdade, e é
chamado crítico. Duas linhas em competências diferentes no mesmo mês
significam que a assinatura foi renovada antes do prazo, o que acontece quando
alguém roda a rotina com a opção de data apontando para o futuro.

### "O anúncio sumiu do guia"

Quase sempre é assinatura cancelada por três recusas seguidas.

```sql
SELECT situacao, tentativas_seguidas_recusadas, cancelada_em
FROM assinaturas WHERE anuncio_id = ?;
```

O anúncio fica no ar durante a inadimplência, por até três tentativas com três
dias entre elas. Depois disso sai. Reativar é assinatura nova.

### A fila de chamados demora a carregar

A fila busca no banco e ordena em PHP, o que é barato até uns poucos milhares
de chamados abertos. Passando disso, o sintoma é lentidão na tela do suporte e
a correção é ordenar no banco. Está registrado como dívida, não implementado.

### O índice caiu no meio do expediente

Nada a fazer com urgência: o guia continua respondendo. Suba o Elasticsearch e
reindexe. Enquanto isso, o visitante vê o aviso e o suporte vê `busca: false`
na sonda.

## Atualizar versão

1. CI verde na `main`.
2. `doctrine:migrations:migrate -n` com a imagem nova.
3. `kubectl set image` na aplicação. A atualização é sem parada.
4. Atualize cada portal pelo console de suporte, que registra a publicação e
   roda as verificações.
5. Olhe o log dos cinco primeiros minutos.

Para voltar atrás: `kubectl rollout undo`. A migração não volta sozinha, e a
existente não derruba coluna, então a versão anterior roda com o banco novo.

## Rotinas que não rodaram

```bash
kubectl get cronjob -n almanaque
kubectl get jobs -n almanaque --sort-by=.metadata.creationTimestamp | tail -5
```

As duas rotinas podem ser repetidas sem medo: a cobrança é idempotente pela
competência, e a expiração só mexe em quem já venceu.
