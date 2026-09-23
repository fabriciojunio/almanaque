# API

Base `/api`. Autenticação por token no cabeçalho `Authorization: Bearer`.

Toda resposta leva `X-Request-Id`, que é o que se procura no log e o que o
chamado guarda.

## Público

```
GET    /saude                          sonda; sem autenticação, 30 req/min
POST   /tokens                         e-mail e senha viram token; 5 req/min
GET    /g/{portal}/anuncios            busca no guia; 60 req/min
GET    /g/{portal}/anuncios/{apelido}  ficha do anúncio
GET    /g/{portal}/categorias          árvore com a contagem
```

A busca aceita `q`, `categoria`, `cidade`, `destaques`, `pagina` e
`por_pagina` (teto de 50). A resposta diz qual motor respondeu e se estava em
modo reserva:

```json
{
  "dados": [],
  "total": 8,
  "pagina": 1,
  "por_pagina": 12,
  "paginas": 1,
  "facetas": { "padarias": 1, "automotivo": 2 },
  "busca": { "motor": "elasticsearch", "em_reserva": false }
}
```

## Autenticado

```
POST   /chamados                       abre chamado; 10 por hora
GET    /chamados                       quem é do portal vê os dele; o suporte vê a fila
GET    /chamados/{id}                  anotação interna só para o suporte
POST   /chamados/{id}/triagem          classificação; só o suporte
```

A abertura devolve, junto com o chamado, os **problemas conhecidos parecidos
que ainda afetam a versão daquele portal**. É a parte da triagem que o sistema
faz sozinho:

```json
{
  "chamado": { "id": 12, "prioridade": "critica", "versao_do_portal": "2.4.0" },
  "problemas_parecidos": [
    {
      "codigo": "PC-001",
      "titulo": "Anúncio novo demora a aparecer na busca",
      "contorno": "Reindexar o portal na mão resolve na hora.",
      "corrigido_na_versao": "2.5.0"
    }
  ]
}
```

A prioridade sai do impacto lido no relato, e não do adjetivo do cliente:
"fora do ar" e "erro 500" abrem como crítica, "cobrança" e "cartão" como alta.

## Erros

| Código | Quando |
| --- | --- |
| 400 | Falta campo obrigatório |
| 401 | Sem token, ou token inválido, expirado ou revogado |
| 403 | Token válido, mas o recurso é de outro portal |
| 404 | Não existe, ou é de um portal que você não enxerga |
| 422 | Classificação inválida, ou regra de domínio recusou |
| 429 | Limite de chamadas |

A resposta de 401 no login é sempre a mesma, para e-mail inexistente, senha
errada e conta desativada. É de propósito: o raciocínio está em
[SEGURANCA.md](SEGURANCA.md).
