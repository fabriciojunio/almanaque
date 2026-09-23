import { execSync } from 'node:child_process'

/*
  Recarrega os dados de exemplo antes da rodada.

  Os testes do console mexem no estado (assumem chamado, classificam,
  resolvem), então sem isto a segunda rodada encontra o mundo que a primeira
  deixou e falha por um motivo que não é o dela.
*/
export default function preparar(): void {
  // Contra o endereço publicado não se recarrega nada: o teste de fluxo abre
  // o próprio chamado, e o resto é leitura.
  if (process.env.ALVO) {
    return
  }

  execSync('php bin/console doctrine:fixtures:load --env=test -n --quiet', {
    stdio: 'inherit',
    env: { ...process.env, APP_ENV: 'test' },
  })
}
