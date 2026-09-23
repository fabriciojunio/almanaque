# Segurança

O que o Almanaque protege, de quem, e como. Este documento é o raciocínio; o
canal para reportar falha está em [SECURITY.md](../SECURITY.md).

## O que há para proteger

Três coisas, em ordem de gravidade se vazarem:

1. **O dado de um cliente aparecendo no portal de outro.** É multi-inquilino:
   o pior defeito possível aqui não é o sistema cair, é o guia de um cliente
   mostrar o anunciante do concorrente.
2. **Dinheiro.** Assinatura, cobrança e o que já foi pago.
3. **Dado pessoal do anunciante e do titular do chamado**, que é o assunto da
   LGPD.

## Isolamento entre inquilinos

A defesa é em três camadas, e nenhuma delas é a tela:

**No repositório.** A consulta do guia nasce de um método privado que já
recebe o portal e já filtra por ele. Não existe caminho de leitura do guia que
não passe por ali.

**No índice de busca.** O filtro por portal é o primeiro item da consulta ao
Elasticsearch, antes do termo. Um índice só guarda o anúncio de todos os
clientes, e esquecer esse filtro é exatamente o defeito número um da lista.

**No votante.** Quando alguém pede um recurso pelo identificador, quem
responde "este portal é seu?" é `VotanteDePortal`, e a resposta padrão é não.

Isso tem teste: o mesmo apelido de anúncio existe nos dois portais dos dados
de exemplo, e a bateria confere que cada endereço devolve o seu.

## Autenticação

**Token de API.** O banco guarda `sha256` do token, nunca o token. O segredo
aparece uma vez, na criação, e nunca mais: perdeu, gera outro. Vazamento de
dump não vira acesso, e nem o administrador consegue ler o token de alguém.
Validade de 30 dias, com revogação.

**Senha.** Argon2id quando a extensão existe, bcrypt quando não (`auto` do
Symfony). Nos testes o custo cai para o mínimo, porque cifrar senha de verdade
em cada teste multiplica o tempo por dez e não prova nada.

**Resposta de erro igual para tudo.** E-mail que não existe, senha errada e
conta desativada devolvem o mesmo texto e o mesmo código. Diferenciar entrega
de graça a lista de quem tem conta.

**E o mesmo tempo.** Quando o e-mail não existe, a senha é conferida assim
mesmo, contra um hash descartável. Sem isso, a resposta para e-mail
inexistente volta em um milissegundo e a de senha errada em cem, e o tempo
responde o que o texto não respondeu.

**Limite de tentativa.** Cinco por minuto e por endereço na API, cinco em
quinze minutos no formulário do console. Tem teste conferindo que a sexta é
barrada, inclusive quando a senha está certa.

## Política de conteúdo

```
default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self';
font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self';
form-action 'self'
```

Sem `unsafe-inline` e sem `unsafe-eval`. Isso cobrou dois preços, e os dois
valeram:

**Não existe `style=""` em template nenhum.** O que seria um ajuste rápido no
HTML virou classe no CSS. Um teste varre as oito páginas e reprova se algum
voltar, porque o sintoma é cruel: o navegador descarta o estilo em silêncio, o
teste de rota continua verde e o layout só aparece torto em produção.

**As fontes são servidas daqui.** A folha do Google Fonts caía na diretiva de
estilo, e o site inteiro estava sendo desenhado com as fontes de reserva sem
ninguém perceber. Hospedar resolve, e de quebra tira o endereço de rede de
todo visitante do caminho de um terceiro.

## Entrada e saída

Toda entrada passa por validação antes de virar objeto de domínio, e o domínio
recusa o que não faz sentido: coordenada fora do planeta, preço negativo,
prazo no passado, categoria de outro portal.

As consultas são Doctrine com parâmetro. Onde há `LIKE`, o termo é escapado:
`%` e `_` do visitante não viram curinga. Tem teste com `?q=%`.

O Twig escapa por padrão, e o único `|nl2br` do projeto está sobre texto que
já passou pelo escape.

## Sessão e formulário

Cookie `HttpOnly`, `SameSite=Lax`, `use_strict_mode`. Todo formulário que
altera estado carrega token, conferido no controller. Sem isso, uma aba aberta
em outro lugar conseguiria classificar ou resolver chamado no lugar de quem
está autenticado.

## Imagem de produção

Sem Composer, sem Xdebug, sem código-fonte de desenvolvimento e sem root: roda
como `www-data`, com `allowPrivilegeEscalation: false` e todas as capacidades
derrubadas. O `expose_php` está desligado e o `display_errors` também: o que o
visitante vê é a página de erro com o identificador da requisição, e o detalhe
fica no log.

No Kubernetes, o segredo versionado é um gabarito vazio, e o conferidor do CI
reprova se alguém gravar um valor nele. Uma política de rede deixa só a
aplicação falar com o banco e com o índice.

## O que ficou de fora, e por quê

**Segundo fator.** O console é usado por um time pequeno e conhecido. Entra
quando houver cliente administrando o próprio portal em escala.

**Rotação automática de token.** Hoje é manual, pela revogação. Trinta dias de
validade é o teto.

**Criptografia de campo no banco.** O dado pessoal aqui é contato comercial
publicado num guia público: não é o caso. Se um dia entrar dado sensível, a
decisão muda.

**Varredura de dependência por robô (Dependabot).** O CI roda `composer audit`
em toda proposta de mudança, que resolve o caso urgente. A automação de
atualização é a próxima.

## Reportar uma falha

Não abra issue pública. O caminho está em [SECURITY.md](../SECURITY.md).
