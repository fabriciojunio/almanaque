# Implantação

## Com Docker, que é o caminho curto

```bash
cp .env.example .env.local        # preencha as duas senhas
docker compose up -d
docker compose exec api php bin/console doctrine:migrations:migrate -n
docker compose exec api php bin/console doctrine:fixtures:load -n
docker compose exec api php bin/console almanaque:reindexar
```

Guia em <http://localhost:8080>, console em `/suporte`, sonda em `/rest/saude`.

O `.env.example` não traz senha padrão de propósito: subir um MySQL com senha
conhecida na porta 3306 da máquina é deixar a porta encostada.

## Em função sem servidor (é o que está no ar)

A demonstração pública roda na Vercel, com PostgreSQL gerenciado e sem
Elasticsearch. O alvo real do produto é o Kubernetes descrito mais abaixo;
esta forma existe para haver um endereço que qualquer pessoa abre.

O ponto de entrada é `api/index.php`, e o `vercel.json` manda todo caminho
para lá, menos fonte, estilo, favicon e `robots.txt`, que são servidos como
arquivo. O `public/index.php` fica no `.vercelignore`: sem isso a plataforma
o trata como arquivo estático e entrega o código-fonte como download na raiz.

Variáveis que o ambiente precisa:

```
APP_ENV=prod
APP_SECRET=<32 bytes em hexa>
DATABASE_URL=postgresql://usuario:senha@host/banco?sslmode=require
```

Sem `ELASTICSEARCH_URL`, a busca cai para o banco, que é o comportamento
projetado e não um remendo.

Quatro coisas mudam em relação a um servidor comum:

**Disco somente leitura.** O `Kernel` separa o que é gerado na construção do
que é escrito em execução e, quando `VERCEL` está no ambiente, joga cache e
log em `/tmp`. É por isso que `getBuildDir()` existe: o container compilado
guarda dentro dele o caminho absoluto do cache, e o caminho da máquina que
constrói não existe na que executa.

**Sessão e limitador de tentativa no banco.** Instância não dura, e duas
requisições seguidas caem em processos diferentes: sessão em arquivo
deslogaria o analista, e limitador em memória nunca contaria a segunda
tentativa. Os dois vão para o PostgreSQL (`PdoSessionHandler` na tabela
`sessoes`, e `cache.adapter.doctrine_dbal` para o resto).

**Proxy que termina o TLS.** Sem `trusted_proxies`, todo redirecionamento sai
como `http`, o navegador não devolve o cookie marcado como seguro e o login
não gruda.

**A pasta `api` é reservada.** A plataforma responde 404 em qualquer caminho
sob `/api` que não seja um arquivo dela. Por isso o prefixo da API é `/rest`
— ver [ADR 0007](adr/0007-prefixo-rest.md).

## Sem Docker

Precisa de PHP 8.3 com `intl`, `pdo_sqlite` e `pdo_mysql`, e do Composer.

```bash
composer install
php bin/console doctrine:schema:create --env=test
php bin/console doctrine:fixtures:load --env=test -n
php -d variables_order=EGPCS -S 127.0.0.1:8000 -t public
```

O `variables_order=EGPCS` não é enfeite: sem o `E`, o servidor embutido do PHP
não põe as variáveis de ambiente em `$_ENV`, o Symfony não enxerga o `APP_ENV`
e sobe em `dev`, tentando falar com um MySQL que não está lá.

Sem `ELASTICSEARCH_URL`, a busca usa o banco. O guia funciona, com relevância
pior.

## Exercitar o S3 sem conta na AWS

```bash
docker compose --profile aws up -d localstack
```

E no `.env.local`:

```
ARMAZENAMENTO=s3
AWS_ENDPOINT=http://localstack:4566
AWS_CHAVE=teste
AWS_SEGREDO=teste
```

## Kubernetes

```bash
kubectl apply -f k8s/00-base.yaml    # namespace, configmap, gabarito do segredo
kubectl apply -f k8s/10-api.yaml     # aplicação e as duas rotinas diárias
kubectl apply -f k8s/20-borda.yaml   # Nginx, service, ingress e política de rede
```

O `Secret` do `00-base.yaml` vai vazio. Quem publica preenche pelo gerenciador
de segredo do cluster, e `python k8s/conferir-manifestos.py`, que roda no CI,
reprova se alguém gravar um valor ali.

O conferidor também cobra o que costuma escapar em revisão: container como
root, imagem em `latest`, deployment sem limite de recurso, porta exposta sem
sonda, entrada HTTP sem TLS e CronJob sem `concurrencyPolicy: Forbid`. Esse
último é o que evita duas execuções da cobrança se sobrepondo.

### As duas rotinas

| Rotina | Horário | Por quê |
| --- | --- | --- |
| `almanaque:cobrar` | 03:00 | Fora do horário comercial, antes de alguém olhar o faturamento |
| `almanaque:expirar` | 03:30 | **Depois** da cobrança: quem pagou às três teve o prazo empurrado |

### O que falta para ser produção de verdade

Os manifestos cobrem a aplicação. Banco, cache e índice aparecem como nome de
serviço, e a expectativa é que sejam gerenciados: RDS, ElastiCache e
OpenSearch na AWS, ou equivalente. Rodar banco com estado dentro do cluster é
decisão separada, e não é a recomendada aqui.

Também ficam de fora certificado (cert-manager, referenciado no ingress),
autoescala e o agregador de log, que dependem do cluster.

## Migração

`php bin/console doctrine:migrations:migrate -n` roda antes de trocar a
imagem. A migração existente é a criação do esquema; daqui para frente,
migração que derruba coluna precisa de duas publicações, porque a atualização
é sem parada (`maxUnavailable: 0`) e as duas versões convivem durante a troca.

Depois de publicar, o portal é atualizado pelo console de suporte, que
registra a publicação e roda as verificações. Uma reprovada devolve o portal
para a versão anterior.

## Reindexar

Depois de subir versão que mexe no mapeamento do índice:

```bash
php bin/console almanaque:reindexar          # todos os portais
php bin/console almanaque:reindexar bauru    # um só
```

É também o primeiro comando a rodar quando o cliente diz que publicou e o
anúncio não aparece na busca.
