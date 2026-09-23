# Como mexer neste projeto

## Começar

```bash
git clone https://github.com/fabriciojunio/almanaque.git
cd almanaque
composer install
make banco
php -d variables_order=EGPCS -S 127.0.0.1:8000 -t public
```

Sem Docker, sem MySQL e sem Elasticsearch: a bateria roda em SQLite e a busca
cai para o banco. Para o ambiente completo, `make subir`.

## Antes de abrir uma proposta de mudança

```bash
make revisar   # formatação, análise estática nível 8, auditoria de dependência
make testar    # 149 testes
make e2e       # 32 testes de navegador, quando mexer na interface
```

É o mesmo que o CI cobra. Rodar antes economiza uma volta.

## Padrões que valem aqui

**Português no domínio.** Classe, método, propriedade, rota e coluna usam o
vocabulário de quem usa o sistema: `anuncio`, `chamado`, `assinatura`,
`cobranca`. O que fica em inglês é o que o framework impõe (`__construct`,
`handle`, `getUserIdentifier`) e termo técnico consagrado.

**Acento certo.** Em texto de tela, em comentário, em mensagem de erro e em
mensagem de commit. Identificador de código continua sem acento, porque é
identificador.

**Comentário explica decisão, não repete o código.** Se o comentário diz o que
a linha abaixo faz, ele sobra. Se diz por que foi assim e o que foi
descartado, fica.

**Regra de negócio na entidade, não no controller.** Se dá para burlar
chamando o método de outro lugar, está no lugar errado.

**Nada de `style=""` no template.** A política de conteúdo bloqueia, e um
teste reprova. Ver [docs/SEGURANCA.md](docs/SEGURANCA.md).

## Testes

- **Unidade** (`tests/Unidade`): regra de domínio, sem banco e sem HTTP.
- **Integração** (`tests/Integracao`): rotina de cobrança contra o banco, e a
  busca contra um Elasticsearch de verdade, que se pula sozinha fora do CI.
- **HTTP** (`tests/Http`): o caminho inteiro, incluindo as regras de acesso.
- **Navegador** (`e2e`): o que só aparece na tela.

Teste descreve comportamento, e o nome é frase: `nao_resolve_sem_dizer_o_que_era`.
O formatador está configurado para não converter esses nomes para camelCase.

Correção de defeito entra com o teste que pega o defeito. Se o teste passa
antes da correção, ele está olhando para o lugar errado.

## Commits

Conventional Commits, com o escopo em ASCII e a prosa em português acentuado:

```
fix(busca): escapa o curinga do LIKE no termo do visitante
feat(suporte): triagem em quatro caixas
```

O corpo explica o porquê e o que foi descartado. Quem lê daqui a seis meses
quer o motivo, não o diff, que já está ali do lado.

Nada de travessão nas mensagens.

## Proposta de mudança

Branch a partir da `main`, uma mudança por proposta, CI verde. Na descrição: o
que muda, por que, e como testar na mão. Se mexeu na aparência, uma captura de
tela poupa discussão.
