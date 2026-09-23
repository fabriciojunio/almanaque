import { test, expect } from '@playwright/test'

/*
  O caminho de quem chega pelo buscador: capa, guia, busca, ficha do anúncio.
  Sem login, que é como 99% das visitas acontecem.
*/

test('a capa lista os guias publicados', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { level: 1 })).toContainText('O guia da sua cidade')
  await expect(page.getByRole('link', { name: 'Guia de Bauru' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Vale Negócios' })).toBeVisible()
})

test('do guia até a ficha do anúncio, clicando', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('link', { name: 'Guia de Bauru' }).click()

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Guia de Bauru')
  await expect(page.locator('.indice')).toContainText('Alimentação')

  await page.getByRole('link', { name: 'Padaria Estrela' }).click()

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Padaria Estrela')
  await expect(page.locator('.ficha-dados')).toContainText('(14) 3234-1010')
})

test('a busca acha pelo nome e diz quem respondeu', async ({ page }) => {
  await page.goto('/g/bauru')

  await page.getByLabel('Buscar no guia').fill('mecânica')
  await page.getByRole('button', { name: 'Procurar' }).click()

  await expect(page).toHaveURL(/busca\?q=/)
  await expect(page.locator('.lista-anuncios')).toContainText('Mecânica do Tião')
  await expect(page.locator('body')).toContainText('busca respondida por banco')
})

test('busca sem resultado explica, em vez de mostrar lista vazia', async ({ page }) => {
  await page.goto('/g/bauru/busca?q=nave espacial')

  await expect(page.locator('.vazio')).toContainText('Nada encontrado')
})

test('o índice de categorias filtra e marca onde o visitante está', async ({ page }) => {
  await page.goto('/g/bauru')

  await page.getByRole('link', { name: /^Automotivo/ }).click()

  await expect(page.locator('.indice .marcado')).toContainText('Automotivo')
  await expect(page.locator('.lista-anuncios .anuncio')).toHaveCount(2)
})

test('um guia não mostra o anúncio do outro', async ({ page }) => {
  await page.goto('/g/bauru/busca?q=Padaria')
  await expect(page.locator('.lista-anuncios .anuncio')).toHaveCount(1)

  await page.goto('/g/vale/padaria-estrela')
  await expect(page.locator('.ficha-dados')).toContainText('Jaú')
})

test('endereço de guia que não existe responde 404', async ({ page }) => {
  const resposta = await page.goto('/g/nao-existe')

  expect(resposta?.status()).toBe(404)
})

test('a página não gera erro no console do navegador', async ({ page }) => {
  const erros: string[] = []
  page.on('console', (mensagem) => {
    if (mensagem.type() === 'error') erros.push(mensagem.text())
  })

  await page.goto('/g/bauru')
  await page.goto('/g/bauru/padaria-estrela')

  // A política de conteúdo é restritiva: estilo ou script embutido apareceria
  // aqui como violação, mesmo sem quebrar a tela de forma óbvia.
  expect(erros).toEqual([])
})
