# Arquitetura

## As quatro camadas

```
src/
  Interface/        Controllers (Api e Web), comandos de console
  Aplicacao/        Casos de uso e seus resumos
  Dominio/          Entidades, regras e as portas (interfaces)
  Infraestrutura/   Doctrine, Elasticsearch, pagamento, HTTP, segurança
```

A dependência aponta sempre para dentro. O domínio não conhece Symfony, não
conhece Elasticsearch e não conhece o adquirente: ele declara o que precisa
como interface, e a infraestrutura implementa.

Três portas, e o que cada uma comprou:

| Porta | Implementações | O que isso permite |
| --- | --- | --- |
| `MotorDeBusca` | Elasticsearch, banco, e o decorador com reserva | O guia continua de pé com o índice fora |
| `Indexador` | Elasticsearch e um inerte | A bateria roda sem serviço no ar |
| `Gateway` | Adquirente de demonstração | Testar recusa sem sandbox de terceiro |

Quem decide qual implementação entra é o container, na partida, olhando a
variável de ambiente. Sem `ELASTICSEARCH_URL`, o sistema inteiro roda na busca
do banco e o indexador não faz nada, sem um `if` sequer no código de negócio.

### Onde o Doctrine mora

As entidades ficam em `Dominio/`, com as anotações de mapeamento nelas. É uma
concessão consciente: o purismo mandaria separar entidade de domínio de
entidade de persistência, e o preço seria uma camada inteira de tradução para
um sistema deste tamanho. A regra que continua valendo é que **nenhum caso de
uso monta consulta**: quem fala com o banco é o repositório.

## O domínio, em cinco frases

- **Portal** é o inquilino. Tudo pendura nele.
- **Anúncio** tem um ciclo de vida próprio: rascunho, publicado com prazo,
  expirado, recusado. É onde nasce a maior parte dos chamados de um diretório.
- **Assinatura** carrega a cobrança recorrente inteira, incluindo o que fazer
  quando o pagamento é recusado.
- **Chamado** obriga a classificação antes da resolução, e a reprodução antes
  da classificação como defeito.
- **Publicação** só fecha depois das verificações, e uma reprovada devolve o
  portal para a versão anterior.

## Decisões que valem citar

### A comparação de portal é por objeto, não por id

Entidade recém-criada tem id nulo. `null !== null` é falso, então comparar por
id fazia a verificação passar justamente no caso que ela existe para pegar:
categoria de um portal usada em anúncio de outro. Foi um teste de unidade que
mostrou.

### O tipo da busca tem reserva, e o resultado diz qual respondeu

O decorador `MotorComReserva` tenta o Elasticsearch e cai para o banco se ele
falhar. A falha vira alerta no log com o portal e o erro, e o resultado sai
marcado. A página avisa o visitante, e o suporte sabe, ao abrir o chamado, que
o índice estava fora naquele momento.

### Dinheiro em centavos, como inteiro

`float` para dinheiro é o caminho curto para o total da fatura fechar com um
centavo de diferença.

### Idempotência por competência

A cobrança de um ciclo é identificada pelo ano e mês. Rodar a rotina duas
vezes no mesmo dia, o que acontece quando alguém repete o comando na mão, não
abre cobrança nova. E cada assinatura é gravada na sua própria transação: uma
que estoura não leva junto as trinta que já deram certo.

### Categoria com dois níveis, e não árvore

Guia comercial não precisa de profundidade arbitrária, e a consulta recursiva
em MySQL só ficou razoável na versão 8 e continua cara. O construtor recusa
neta.

## Fluxo de uma requisição

```
navegador → Nginx → PHP-FPM → IdentificadorDeRequisicao → firewall → votante
                                    ↓                                   ↓
                             X-Request-Id no log              Controller → Caso de uso
                                                                              ↓
                                                              Repositório / Motor de busca
                                    ↓
                             CabecalhosDeSeguranca na saída
```

O identificador da requisição entra primeiro e sai por último. É ele que liga
a tela de erro do cliente, o chamado e a linha do log.

## O que não tem aqui, de propósito

**Framework de tela.** O guia é renderizado no servidor. A página precisa
abrir rápido em celular ruim e ser lida pelo buscador, que é de onde vem quem
procura por chaveiro perto de casa. O console de suporte também: formulário e
recarga, sem estado no navegador para se perder.

**Fila de mensagem.** O que roda fora da requisição são duas rotinas diárias,
e CronJob resolve. Fila entra quando houver trabalho disparado por evento com
volume.

**Microserviço.** Cinco entidades num domínio só. A separação que importa aqui
é entre camadas, não entre processos.

## Decisões registradas

Uma por arquivo, com o contexto e o que foi descartado, em [adr/](adr/).
