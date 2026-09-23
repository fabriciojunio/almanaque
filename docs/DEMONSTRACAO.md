# Demonstração

## Subir

```bash
cp .env.example .env.local        # preencha as duas senhas
docker compose up -d
docker compose exec api php bin/console doctrine:migrations:migrate -n
docker compose exec api php bin/console doctrine:fixtures:load -n
docker compose exec api php bin/console almanaque:reindexar
```

O guia abre em <http://localhost:8080>.

## Contas

Todas com a senha `demonstracao2026`.

| Conta | Papel | O que enxerga |
| --- | --- | --- |
| `suporte@almanaque.com.br` | Suporte | A fila inteira, de todos os portais, com anotação interna |
| `admin@almanaque.com.br` | Administrador | Tudo |
| `dono@guiadebauru.com.br` | Dono do portal | Só o Guia de Bauru, sem anotação interna |
| `dono@valenegocios.com.br` | Dono do portal | Só o Vale Negócios |
| `anunciante@guiadebauru.com.br` | Anunciante | Só o próprio anúncio |

## Roteiro de dez minutos

**1. O guia, sem login.** Abra o Guia de Bauru, procure por "padaria" e repare
no rodapé do resultado: ele diz qual motor respondeu. Com o Elasticsearch no
ar, procure por `acai` sem acento e por `padria` com o erro de digitação; as
duas encontram.

**2. Dois portais, dados separados.** O mesmo apelido de anúncio existe nos
dois guias. `/g/bauru/padaria-estrela` e `/g/vale/padaria-estrela` devolvem
coisas diferentes, e o endereço de um nunca mostra o anúncio do outro.

**3. A fila do suporte.** Entre como `suporte@almanaque.com.br`. O chamado
crítico vem primeiro, e não é porque chegou antes: o portal está fora do ar.
Repare que ele já está marcado como fora do prazo.

**4. A triagem.** Abra o chamado "O botão de contato sumiu do anúncio", no
Guia de Bauru, que é o portal customizado pelo cliente. Ele está classificado
como customização do cliente, com a marcação de que **não** acontece em
ambiente limpo. Agora tente classificar o chamado do Vale Negócios como
customização: o sistema recusa, porque aquele portal não tem customização
registrada.

**5. Problema conhecido e versão.** Em "Problemas conhecidos", procure por
"não aparece na busca". O PC-001 está corrigido na 2.5.0. O Guia de Bauru roda
a 2.4.0, então o chamado dele ainda mostra esse problema como parecido; o Vale
Negócios, na 2.5.0, não.

**6. A publicação que voltou atrás.** Em "Publicações", a subida da 2.6.0 no
Vale Negócios foi revertida porque a verificação "busca responde" reprovou. O
motivo está escrito ali.

**7. A cobrança.** No terminal:

```bash
docker compose exec api php bin/console almanaque:cobrar --ensaio
docker compose exec api php bin/console almanaque:cobrar
docker compose exec api php bin/console almanaque:cobrar   # de novo
```

A segunda execução não cobra ninguém duas vezes. O adquirente de demonstração
recusa valor terminado em 13 centavos, que é como o caminho da inadimplência
é exercitado.

**8. A busca sem o índice.** Derrube o Elasticsearch e procure de novo:

```bash
docker compose stop indice
```

O guia continua respondendo, agora pelo banco, com um aviso na página. A sonda
em `/api/saude` mostra `busca: false` com `banco: true`.
