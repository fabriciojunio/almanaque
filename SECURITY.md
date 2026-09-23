# Política de segurança

## Reportar uma falha

Não abra issue pública.

Mande os detalhes para **fabricioad444@gmail.com**, com:

- a descrição da falha e do impacto;
- os passos para reproduzir, ou a prova de conceito;
- a versão ou o commit afetado.

Primeira resposta em até 72 horas. Confirmada a falha, combinamos a janela de
correção e a divulgação.

## Versões com suporte

O suporte acompanha a branch `main`. Correção entra na versão mais recente.

## O que já está no lugar

O raciocínio completo, com o que ficou de fora e por quê, está em
[docs/SEGURANCA.md](docs/SEGURANCA.md). Em resumo:

- Token de API com hash SHA-256 no banco; o segredo aparece uma vez só.
- Resposta de login igual, e no mesmo tempo, para e-mail inexistente, senha
  errada e conta desativada.
- Limite de tentativa no login, na busca pública e na abertura de chamado.
- Isolamento entre inquilinos no repositório, no índice e no votante.
- Política de conteúdo sem `unsafe-inline`; fontes servidas pelo próprio
  servidor.
- Consulta sempre com parâmetro; curinga de `LIKE` escapado.
- Token de formulário em tudo que altera estado.
- Imagem de produção sem Composer, sem Xdebug e sem root.

## Recomendações para quem opera

- Rodar `composer audit` a cada mudança de dependência; o CI já roda.
- Servir sempre sob HTTPS. O HSTS só é emitido em conexão segura.
- Trocar as senhas dos dados de exemplo antes de qualquer uso real.
- Preencher o segredo do Kubernetes pelo gerenciador do cluster, nunca no
  arquivo versionado.
