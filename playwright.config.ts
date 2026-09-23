import { defineConfig, devices } from '@playwright/test'

/*
  Os testes de ponta a ponta sobem o próprio servidor do PHP com o ambiente de
  teste, que usa o SQLite já carregado com os dados de exemplo. Não precisa de
  MySQL nem de Elasticsearch no ar: o mesmo comando roda no CI e na máquina de
  quem programa.

  Preparar o banco antes:
    php bin/console doctrine:schema:create --env=test
    php bin/console doctrine:fixtures:load --env=test -n
*/
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/preparar.ts',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',
  use: {
    baseURL: 'http://127.0.0.1:8099',
    trace: 'on-first-retry',
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'celular', use: { ...devices['Pixel 7'] } },
  ],
  webServer: {
    // O -d variables_order=EGPCS não é enfeite: sem o E, o servidor embutido
    // do PHP não põe as variáveis de ambiente em $_ENV, o Symfony não enxerga
    // o APP_ENV, sobe em dev e tenta falar com o MySQL que não existe aqui.
    command: 'php -d variables_order=EGPCS -S 127.0.0.1:8099 -t public',
    url: 'http://127.0.0.1:8099/api/saude',
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
    env: { APP_ENV: 'test' },
  },
})
