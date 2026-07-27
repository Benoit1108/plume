// Gate de sécurité BLOQUANT sur les dépendances runtime (remplace `npm audit --omit=dev
// --audit-level=high` : durcissement M1). Il bloque tout advisory high/critical runtime, SAUF ceux
// listés ci-dessous — des advisories SANS correctif upstream, tracés, à revoir régulièrement.
//
// Règle : on ne tolère un advisory QUE s'il est ici, daté et justifié. Toute NOUVELLE vulnérabilité
// high/critical fait échouer la CI. Retirer une entrée dès qu'un correctif existe (`npm audit fix`).
import { execSync } from 'node:child_process'

/** @type {Record<string, string>} advisory GHSA -> justification (datée). */
const ALLOWED = {
  'GHSA-mh99-v99m-4gvg':
    'brace-expansion DoS (2026-07-27) — transitive de l’OUTILLAGE DE BUILD de Nuxt '
    + '(glob/minimatch via nitropack/archiver). Aucun correctif forward (nuxt ≥4.2 est flaggé, le '
    + 'seul « fix » npm est un downgrade cassant en 4.1.3), zéro exposition au runtime servi. '
    + 'À retirer dès qu’un Nuxt embarquant un brace-expansion patché sort.',
}
const BLOCKING = new Set(['high', 'critical'])

let raw
try {
  raw = execSync('npm audit --omit=dev --json', { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })
}
catch (error) {
  // `npm audit` sort en code 1 dès qu'il trouve des vulnérabilités : le JSON est quand même émis.
  raw = error.stdout?.toString() ?? ''
}
if ('' === raw.trim()) {
  console.error('check-npm-audit: sortie `npm audit` vide — échec par prudence.')
  process.exit(1)
}

const report = JSON.parse(raw)

// Collecte les advisories DIRECTES (objets `via`), dédupliquées par identifiant GHSA.
/** @type {Map<string, {severity: string, module: string, title: string}>} */
const advisories = new Map()
for (const info of Object.values(report.vulnerabilities ?? {})) {
  for (const via of info.via ?? []) {
    if ('object' === typeof via && via.url) {
      advisories.set(via.url.split('/').pop(), {
        severity: via.severity ?? info.severity,
        module: via.name ?? '?',
        title: (via.title ?? '').slice(0, 80),
      })
    }
  }
}

const offenders = [...advisories.entries()].filter(([id, a]) => BLOCKING.has(a.severity) && !(id in ALLOWED))

if (offenders.length > 0) {
  console.error('❌ Audit runtime BLOQUANT — advisories high/critical NON tracées :')
  for (const [id, a] of offenders) {
    console.error(`  - ${id} [${a.severity}] ${a.module} — ${a.title}`)
  }
  console.error('\nCorrige-les (`npm audit fix`), ou — si aucun correctif n’existe — ajoute une '
    + 'exception DATÉE et JUSTIFIÉE dans app/scripts/check-npm-audit.mjs.')
  process.exit(1)
}

console.log('✅ Audit runtime OK — seules des advisories high/critical tracées subsistent :')
for (const id of Object.keys(ALLOWED)) {
  console.log(`  - ${id} : toléré (cf. justification dans le script).`)
}
