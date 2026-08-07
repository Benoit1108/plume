// Gate de sécurité BLOQUANT sur les dépendances runtime (remplace `npm audit --omit=dev
// --audit-level=high` : durcissement M1). Il bloque tout advisory high/critical runtime, SAUF ceux
// listés ci-dessous — des advisories SANS correctif upstream, tracés, à revoir régulièrement.
//
// Règle : on ne tolère un advisory QUE s'il est ici, daté et justifié. Toute NOUVELLE vulnérabilité
// high/critical fait échouer la CI. Retirer une entrée dès qu'un correctif existe (`npm audit fix`).
import { execSync } from 'node:child_process'

/** @type {Record<string, string>} advisory GHSA -> justification (datée). */
const ALLOWED = {
  // 2026-08-03 : les advisories brace-expansion (GHSA-mh99-v99m-4gvg + GHSA-rgw5-rvv9-x895) sont
  // CORRIGÉES via un override ciblé (package.json → 2.1.4 / 5.0.9), pas une exception.

  // 2026-08-07 — js-yaml, consommation CPU quadratique sur `!!omap` (high, 4.0.0–4.3.x).
  // Correctif UPSTREAM inaccessible : il n'existe qu'en 5.x, et toute la chaîne épingle `^4`
  // (@nuxtjs/i18n → @rollup/plugin-yaml, openapi-typescript → @redocly/openapi-core, @nuxt/eslint).
  // Les deux forçages possibles ont été ESSAYÉS et cassent l'outillage :
  //   - override global → @redocly/openapi-core appelle `types.merge`, retiré en v5 : `gen:types` meurt ;
  //   - override ciblé sur @nuxtjs/i18n → `nuxt dev` lève deux `unhandledRejection` (assertion du
  //     translateur CJS→ESM de Node, via jiti). Le build de PROD, lui, passait : d'où le piège.
  // EXPOSITION NULLE : js-yaml n'est utilisé qu'au BUILD, pour lire NOS propres fichiers (locales
  // i18n, openapi.json). L'application ne parse aucun YAML à l'exécution, a fortiori aucun YAML
  // fourni par un tiers — or l'advisory est un déni de service au parsing d'un document hostile.
  // À RETIRER dès que @rollup/plugin-yaml (ou @nuxtjs/i18n) passe à js-yaml 5.
  'GHSA-5p4m-2wfm-xmqj': '2026-08-07 — build-time only (nos propres fichiers) ; correctif 5.x inatteignable, forçages cassants (redocly, nuxt dev). Revoir au prochain bump de @rollup/plugin-yaml.',
}
const RANK = { info: 0, low: 1, moderate: 2, high: 3, critical: 4 }
const BLOCKING_FROM = RANK.high

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

// Collecte les advisories DIRECTES (objets `via`), dédupliquées par identifiant GHSA en gardant la
// sévérité MAXIMALE observée (une occurrence sans `severity` ne doit jamais rétrograder un high).
/** @type {Map<string, {severity: string, module: string, title: string}>} */
const advisories = new Map()
for (const info of Object.values(report.vulnerabilities ?? {})) {
  for (const via of info.via ?? []) {
    if ('object' === typeof via && via.url) {
      const id = via.url.split('/').pop()
      const severity = via.severity ?? info.severity ?? 'high'
      const previous = advisories.get(id)
      if (previous && (RANK[previous.severity] ?? 0) >= (RANK[severity] ?? 0)) {
        continue
      }
      advisories.set(id, { severity, module: via.name ?? '?', title: (via.title ?? '').slice(0, 80) })
    }
  }
}

// Bloque tout advisory ≥ high non tracé. Un `critical` bloque TOUJOURS, même s'il est dans
// l'allowlist (plafond de sévérité : une exception ne vaut que pour du high).
const offenders = [...advisories.entries()].filter(([id, a]) => {
  const rank = RANK[a.severity] ?? RANK.high
  if (rank < BLOCKING_FROM) {
    return false
  }

  return 'critical' === a.severity || !(id in ALLOWED)
})

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
