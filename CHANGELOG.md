# Mudanças

Formato baseado no [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

## [Não publicado]

### Adicionado

- Guia multi-inquilino: portal, categorias em dois níveis, anúncio com
  contato, mapa e destaque.
- Assinatura com cobrança recorrente, inadimplência em três tentativas e
  cancelamento que tira o anúncio do ar.
- Busca em Elasticsearch com relevância, acento e erro de digitação, e busca
  de reserva no banco quando o índice cai.
- Console de suporte: fila por impacto, triagem em quatro caixas, base de
  problemas conhecidos e registro das publicações de versão.
- API pública do guia e API autenticada de chamados, com token de hash no
  banco.
- Três comandos de operação: cobrar, expirar e reindexar.
- 151 testes automatizados e 32 de navegador, no desktop e no celular.
- CI que roda a bateria em SQLite, em MySQL 8 e com Elasticsearch no ar, e que
  reprova se os testes de busca se pularem.
- Imagem de produção sem Composer, sem Xdebug e sem root; manifestos do
  Kubernetes com conferidor no CI.
- Demonstração pública em <https://almanaque-ecru.vercel.app>, com PostgreSQL
  gerenciado e a busca respondendo pelo banco.

### Corrigido antes de existir versão

- A comparação de portal por id deixava passar categoria de outro portal, que
  é o caso que a verificação existe para pegar.
- A política de conteúdo bloqueava a folha do Google Fonts, e o site inteiro
  era desenhado com as fontes de reserva sem ninguém perceber.
- A barra de topo do console não cabia num aparelho de 412 pontos.
- A busca de problemas conhecidos só achava frase exata.
- A contagem de anúncios da categoria raiz ignorava as filhas.
- O LIKE das buscas era sensível a maiúscula no PostgreSQL: procurar por
  "padaria" não achava "Padaria Estrela".
- O ponto de entrada `public/index.php` era servido como arquivo estático na
  raiz da demonstração, entregando o código-fonte como download.
- Atrás do proxy que termina o TLS, o redirecionamento saía como http e o
  cookie de sessão não voltava, derrubando quem acabara de entrar.
- O cabeçalho `X-Powered-By` posto pelo próprio PHP não passava pelo objeto de
  resposta e escapava da limpeza.
