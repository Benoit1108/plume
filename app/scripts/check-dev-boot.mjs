// Garde-fou : le SERVEUR DE DÉVELOPPEMENT doit démarrer proprement.
//
// Le 2026-08-07, un override de dépendance a passé lint, type-check, build de prod, vitest ET la
// suite E2E — puis a cassé `nuxt dev` avec deux `unhandledRejection` (le dev et la prod n'empruntent
// pas le même chemin de chargement de modules). C'est l'outil quotidien : rien ne le surveillait.
//
// Ce script démarre `nuxt dev`, attend qu'il annonce son URL, et échoue si une erreur fatale
// apparaît avant. Il n'ouvre aucune page : on vérifie le DÉMARRAGE, pas le rendu (l'E2E s'en charge).
import { spawn } from 'node:child_process'
import { rmSync } from 'node:fs'

const TIMEOUT_MS = 120_000
/** Motifs FATALS : le serveur peut « démarrer » en apparence tout en étant cassé. */
const FATAL = [/unhandledRejection/i, /uncaughtException/i, /Cannot find module/i, /Failed to load/i]
const READY = /Local:\s+http:\/\//

// Caches VIDÉS d'abord : à chaud, jiti et `.nuxt` court-circuitent le chargement des modules et
// l'erreur ne se reproduit plus. C'est un démarrage à froid qu'on veut vérifier — celui de la CI,
// et celui d'un `git clone` frais.
for (const cache of ['.nuxt', 'node_modules/.cache', 'node_modules/.vite']) {
  rmSync(cache, { recursive: true, force: true })
}

const child = spawn('npx', ['nuxt', 'dev', '--port', '3999'], {
  env: { ...process.env, NODE_ENV: 'development', CI: '1' },
  stdio: ['ignore', 'pipe', 'pipe'],
})

let output = ''
let settled = false

const finish = (code, message) => {
  if (settled) return
  settled = true
  console[0 === code ? 'log' : 'error'](message)
  if (0 !== code) {
    console.error('\n--- sortie du serveur ---\n' + output.slice(-3000))
  }
  child.kill('SIGTERM')
  // Laisse Nuxt fermer ses handles avant de rendre la main.
  setTimeout(() => process.exit(code), 500)
}

const inspect = (chunk) => {
  output += chunk
  const fatal = FATAL.find(pattern => pattern.test(chunk))
  if (fatal) {
    finish(1, `❌ Le serveur de dev a émis une erreur fatale (${fatal}).`)
    return
  }
  if (READY.test(output)) {
    // Petit délai : les modules Nuxt se chargent APRÈS l'annonce de l'URL (c'est précisément là
    // que l'erreur i18n survenait). On observe encore un instant avant de déclarer la victoire.
    setTimeout(() => finish(0, '✅ Le serveur de dev démarre proprement.'), 8000)
  }
}

child.stdout.on('data', d => inspect(d.toString()))
child.stderr.on('data', d => inspect(d.toString()))
child.on('error', error => finish(1, `❌ Impossible de lancer le serveur de dev : ${error.message}`))
child.on('exit', code => finish(1, `❌ Le serveur de dev s'est arrêté (code ${code}) avant d'être prêt.`))

setTimeout(() => finish(1, `❌ Le serveur de dev n'a pas démarré en ${TIMEOUT_MS / 1000} s.`), TIMEOUT_MS)
