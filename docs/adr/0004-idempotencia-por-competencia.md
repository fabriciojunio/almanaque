# 4. A cobrança é idempotente pela competência do ciclo

Data: 2026-09-23 · Status: aceito

## Contexto

A rotina de cobrança roda por horário. Ela vai ser executada duas vezes no
mesmo dia mais cedo ou mais tarde: alguém repete o comando na mão, o agendador
dispara duas vezes, o pod reinicia no meio.

## Decisão

Cada cobrança carrega a competência do ciclo (ano e mês). Abrir cobrança para
uma competência que já tem uma não recusada devolve a existente. E cada
assinatura é gravada na sua própria transação.

## Consequências

Cobrar duas vezes o mesmo anunciante deixa de ser possível por execução
repetida. Uma assinatura que estoura não desfaz as trinta que já deram certo,
e não impede as que vêm depois.

A competência vira parte do vocabulário: é ela que aparece no extrato e é por
ela que o suporte procura quando o cliente diz que foi cobrado duas vezes.

Ficou de fora uma trava distribuída. Para duas execuções simultâneas de
verdade, o `concurrencyPolicy: Forbid` do CronJob resolve, e a competência
segura o resto.
