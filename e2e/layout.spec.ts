import { test, expect, type Page } from '@playwright/test'

/*
  Duas coisas que não quebram teste de rota e estragam a tela do mesmo jeito.

  Em página curta o rodapé parava no fim do conteúdo, e sobrava papel em branco
  embaixo dele até o fim da janela. A correção foi prender o rodapé no fim da
  janela, e ela mesma causou a segunda: com o corpo virando flex, o miolo
  passou a ter a largura do conteúdo, a tabela do console empurrou a página
  para 773 pontos num aparelho de 412 e o guia ganhou rolagem lateral.

  As duas conferências andam juntas porque foi assim que apareceram.
*/

const publicas = [
  ['capa', '/'],
  ['guia', '/g/bauru'],
  ['busca sem resultado', '/g/bauru/busca?q=zzzzzz'],
  ['busca com resultado', '/g/bauru/busca?q=padaria'],
  ['ficha do anúncio', '/g/bauru/padaria-estrela'],
  ['entrar', '/entrar'],
]

const doSuporte = [
  ['fila do suporte', '/suporte'],
  ['problemas conhecidos', '/suporte/problemas'],
  ['publicações', '/suporte/publicacoes'],
]

async function conferir(page: Page, caminho: string) {
  await page.goto(caminho)

  const medida = await page.evaluate(() => {
    const raiz = document.documentElement
    const rodape = document.querySelector('.rodape')
    if (!rodape) throw new Error('a página não tem rodapé')

    return {
      janela: window.innerHeight,
      altura: raiz.scrollHeight,
      largura: raiz.clientWidth,
      larguraRolavel: raiz.scrollWidth,
      baseDoRodape: rodape.getBoundingClientRect().bottom + window.scrollY,
    }
  })

  expect(medida.baseDoRodape, `${caminho} deixa espaço vazio abaixo do rodapé`)
    .toBeGreaterThanOrEqual(medida.janela - 1)

  expect(medida.altura - medida.baseDoRodape, `${caminho} desenha alguma coisa depois do rodapé`)
    .toBeLessThanOrEqual(1)

  expect(medida.larguraRolavel, `${caminho} passa da largura da tela e rola para o lado`)
    .toBeLessThanOrEqual(medida.largura + 1)
}

for (const [nome, caminho] of publicas) {
  test(`a tela fecha certo: ${nome}`, async ({ page }) => {
    await conferir(page, caminho)
  })
}

test('a tela fecha certo também no console de quem atende', async ({ page }) => {
  await page.goto('/entrar')
  await page.getByLabel('E-mail').fill('suporte@almanaque.com.br')
  await page.getByLabel('Senha').fill('demonstracao2026')
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page).toHaveURL(/suporte/)

  for (const [, caminho] of doSuporte) {
    await conferir(page, caminho)
  }
})
