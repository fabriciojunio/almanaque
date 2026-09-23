# Identidade visual

Guia curto para mexer na aparência sem desmontar o conjunto. Cor, fonte e
forma estão em um arquivo só: `public/estilos/almanaque.css`.

## De onde vem o visual

A referência é o **almanaque impresso e a lista telefônica**: o guia comercial
que existia em papel antes de existir em tela. Papel de jornal, coluna estreita
separada por filete, título pesado e condensado, texto em serifada, e o
amarelo da lista como única cor forte.

Quatro decisões fixas:

1. **O amarelo é a ação, e só ela.** Botão, marcação de categoria escolhida e
   a faixa do anúncio em destaque. Sempre com texto quase preto por cima, que
   é como a lista telefônica imprimia anúncio pago.
2. **A régua dupla é o cabeçalho.** Filete grosso e fino embaixo da barra de
   topo, como jornal. É a assinatura da página.
3. **O destaque é faixa lateral, não sombra.** O anúncio pago ganha fundo
   amarelo claro e uma barra de 6px à esquerda, além da manícula (☞), que é o
   dedo apontando que o almanaque usava para chamar atenção.
4. **Número é datilografado.** Telefone, contagem, versão, data e código usam
   Courier Prime com algarismo de largura fixa. Numa coluna de valores, número
   proporcional dança.

### O que ficou de fora, de propósito

Fundo creme quente com textura de papel, sepia, sombra deslocada, canto
arredondado, gradiente, vidro fosco e ícone dentro de quadradinho colorido. A
referência é impressa, mas a execução é de tela: filete de 1px, canto reto e
nenhuma sombra. Kitsch é o risco desta referência, e a linha que separa é
essa.

## Trocar uma cor

| Variável | Valor | Onde aparece |
| --- | --- | --- |
| `--papel` | `#eeece7` | fundo da página |
| `--papel-escuro` | `#e3e0d9` | faixa alternada |
| `--chapa` | `#ffffff` | ficha, tabela, campo |
| `--tinta` | `#191714` | texto e barra de topo |
| `--tinta-suave` | `#4a453d` | texto secundário |
| `--filete` | `#d3cec3` | divisória entre anúncios |
| `--filete-forte` | `#2b2721` | contorno estrutural e régua |
| `--lista` | `#f5c400` | ação, destaque, marcação |
| `--lista-fraca` | `#fbf0c4` | fundo do anúncio em destaque |
| `--carimbo` | `#a3231b` | erro, prioridade crítica, fora do prazo |
| `--confere` | `#1d6b3f` | verificação aprovada, problema corrigido |

Duas regras que valem a pena manter:

1. **Amarelo nunca é texto.** Sobre branco ele some. Existe como fundo, como
   barra e como contorno.
2. **Vermelho e verde só carregam estado.** Nunca decoram.

## Trocar a fonte

```css
--fonte-titulo: 'Archivo';        /* títulos, marca, botão */
--fonte-corpo:  'Source Serif 4'; /* texto */
--fonte-dado:   'Courier Prime';  /* número, etiqueta, código */
```

As três são servidas de `public/fontes`, e não do Google. O motivo está em
[SEGURANCA.md](SEGURANCA.md): a política de conteúdo só libera `self`, e abrir
exceção na diretiva de estilo enfraquece justamente a proteção que mais vale.
Para trocar uma fonte, baixe os subconjuntos latin e latin-ext e ajuste
`public/estilos/fontes.css`.

A etiqueta (classe `.etiqueta`) é mono em caixa-alta com espacejamento, usada
em rótulo de campo, cabeçalho de tabela e legenda. Não use em frase: em
caixa-alta, texto corrido fica ilegível a partir de umas seis palavras.

## Não existe style embutido

A política de conteúdo bloqueia, e um teste da bateria reprova se algum
voltar. O que seria um ajuste rápido no HTML vira uma classe no CSS. É chato
uma vez e evita a porta que o XSS usa.

## Acessibilidade

Contorno de foco amarelo de 3px, visível tanto no papel quanto na barra
escura. Cor sozinha não informa: prioridade, situação e classificação sempre
vêm escritas junto da tarja. A barra de topo quebra em duas linhas no celular,
porque com cinco itens os links saíam da tela: existiam para o leitor de tela
e não para o dedo.
