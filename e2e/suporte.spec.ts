import { test, expect, type Page } from '@playwright/test'

/*
  O caminho do analista: entrar, pegar o chamado da fila, investigar,
  classificar e resolver. É o fluxo que o console existe para sustentar.
*/

async function entrar(page: Page, email = 'suporte@almanaque.com.br') {
  await page.goto('/entrar')
  await page.getByLabel('E-mail').fill(email)
  await page.getByLabel('Senha').fill('demonstracao2026')
  await page.getByRole('button', { name: 'Entrar' }).click()
}

test('senha errada não entra e não diz o motivo', async ({ page }) => {
  await page.goto('/entrar')
  await page.getByLabel('E-mail').fill('suporte@almanaque.com.br')
  await page.getByLabel('Senha').fill('chute')
  await page.getByRole('button', { name: 'Entrar' }).click()

  await expect(page.locator('.aviso-erro')).toHaveText('E-mail ou senha inválidos.')
})

test('quem não é do suporte não abre o console', async ({ page }) => {
  await entrar(page, 'dono@guiadebauru.com.br')

  const resposta = await page.goto('/suporte')

  expect(resposta?.status()).toBe(403)
})

test('a fila abre com o chamado crítico na frente', async ({ page }) => {
  await entrar(page)

  await expect(page).toHaveURL(/\/suporte$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Fila do suporte')

  const primeira = page.locator('.tabela tbody tr').first()
  await expect(primeira).toContainText('fora do ar')
  await expect(primeira.locator('.tarja-alerta').first()).toContainText('Crítica')
})

test('o analista assume, anota, classifica e resolve', async ({ page, request }) => {
  // O chamado deste teste é aberto pela API, com título único. Ele mexe no
  // estado (assume, classifica, resolve), e depender de um chamado dos dados
  // de exemplo deixaria a segunda rodada falhando por causa da primeira.
  const titulo = `Anúncio sumiu da busca ${Date.now()}`

  const autenticacao = await request.post('/rest/tokens', {
    data: { email: 'dono@guiadebauru.com.br', senha: 'demonstracao2026' },
  })
  expect(autenticacao.ok()).toBeTruthy()
  const { token } = await autenticacao.json()

  const abertura = await request.post('/rest/chamados', {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      titulo,
      relato: 'Publiquei ontem à tarde e não acho pelo nome na busca do guia.',
    },
  })
  expect(abertura.status()).toBe(201)

  await entrar(page)

  await page.getByRole('link', { name: titulo }).click()
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Anúncio sumiu da busca')

  // Antes de assumir, o chamado está sem responsável.
  await expect(page.locator('.ficha-dados')).toContainText('ninguém assumiu')

  await page.getByRole('button', { name: 'Assumir chamado' }).click()
  await expect(page.locator('.ficha-dados')).toContainText('suporte@almanaque.com.br')

  await page.getByLabel('Anotar').fill('Reindexei o portal na mão e o anúncio entrou na busca.')
  await page.getByRole('button', { name: 'Gravar anotação' }).click()
  await expect(page.locator('.lista-anuncios')).toContainText('Reindexei o portal na mão')

  // Sem classificar, não resolve: o botão nem aparece.
  await expect(page.getByRole('button', { name: 'Marcar como resolvido' })).toHaveCount(0)

  await page.getByRole('radio', { name: 'Defeito do produto' }).check()
  await page.getByText('consegui reproduzir').check()
  await page.getByText('acontece em ambiente limpo').check()
  await page.getByLabel('Problema conhecido').fill('PC-001')
  await page.getByRole('button', { name: 'Classificar' }).click()

  await expect(page.locator('.aviso')).toContainText('Defeito do produto')

  await page.getByRole('button', { name: 'Marcar como resolvido' }).click()
  await expect(page.locator('.aviso')).toContainText('Chamado resolvido')
})

test('classificar como customização exige portal customizado', async ({ page }) => {
  await entrar(page)

  // O Vale Negócios não tem customização registrada.
  await page.getByRole('link', { name: 'O site está fora do ar desde as 9h' }).click()
  await page.getByRole('radio', { name: 'Customização do cliente' }).check()
  await page.getByRole('button', { name: 'Classificar' }).click()

  await expect(page.locator('.aviso-erro')).toContainText('não tem customização registrada')
})

test('a base de problemas conhecidos procura pelas palavras do cliente', async ({ page }) => {
  await entrar(page)

  await page.getByRole('link', { name: 'Problemas conhecidos' }).click()
  await page.getByLabel('Procurar problema').fill('não aparece na busca')
  await page.getByRole('button', { name: 'Procurar' }).click()

  await expect(page.locator('.tabela')).toContainText('PC-001')
  await expect(page.locator('.tabela')).toContainText('corrigido na 2.5.0')
})

test('a publicação revertida aparece com a verificação que reprovou', async ({ page }) => {
  await entrar(page)

  await page.getByRole('link', { name: 'Publicações' }).click()

  await expect(page.locator('.tabela')).toContainText('2.5.0 → 2.6.0')
  await expect(page.locator('.tarja-alerta').first()).toContainText('busca responde')
  await expect(page.locator('.tabela')).toContainText('busca parou de responder')
})

test('sair encerra a sessão', async ({ page }) => {
  await entrar(page)

  await page.getByRole('link', { name: 'Sair' }).click()

  const resposta = await page.goto('/suporte')
  expect(resposta?.url()).toContain('/entrar')
})
