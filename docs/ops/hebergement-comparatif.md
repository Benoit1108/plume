# Comparatif d'hébergement — Plume

But : choisir où héberger la prod. **Étape 1 = donner accès à la copine** (mono-utilisatrice) ; ouverture
grand public plus tard. Ce doc part des **besoins runtime réels** de Plume, puis compare les options.

## 1. Ce que Plume a besoin de faire tourner (contrainte structurante)

D'après `compose.yaml` + `deployment-checklist.md` :

| Process | Rôle | Nature |
|---|---|---|
| `php` (FrankenPHP) | API Symfony + sert le front statique + **HTTPS auto** (Caddy intégré) | serveur web long |
| `worker` | `messenger:consume async` (events/projections) | **process long** |
| `worker_io` | `messenger:consume io` (relèves RSS/IMAP/Graph) | **process long** |
| `scheduler` | `messenger:consume scheduler_default` (ticks : relances, digests, purges…) | **process long, exemplaire UNIQUE** |
| `database` | PostgreSQL 17 (2 rôles : `plume` propriétaire + `plume_app` runtime RLS) | base + **sauvegardes** |
| (Redis) | store des rate-limiters — **seulement en multi-instances** | optionnel |

**Conséquences directes :**
- ✅ Il faut un hébergeur qui accepte **Docker** (ou des **process de fond** / workers) + **PostgreSQL**.
- ❌ **Exclus** : hébergement mutualisé PHP classique (o2switch, OVH mutualisé…) — pas de workers/scheduler.
- ❌ **Exclus** : serverless/edge (Vercel/Netlify) — pas de process longs, pas de PG natif.
- ℹ️ FrankenPHP gère le **TLS automatiquement** → sur un VPS, pas besoin de reverse-proxy TLS séparé.
- ℹ️ Le front (`ssr:false`) se **build en statique** et est servi par Caddy → pas de process front dédié coûteux.
- ⚖️ **RGPD** : on manipule des données personnelles (contacts prospectés). **Hébergement UE fortement préféré**
  (à répercuter dans le DPA / registre `docs/legal/`). France = idéal pour le récit RGPD.

## 2. Trois familles d'options

### A. VPS + Docker Compose — le plus DIRECT (on a déjà le compose)
Une VM, Docker, on pointe le domaine, `docker compose up`, migrations. FrankenPHP fait le HTTPS.

| Fournisseur | Offre repère | Prix (ordre) | Zone | Notes |
|---|---|---|---|---|
| **Hetzner Cloud** | CX22 — 2 vCPU / 4 Go / 40 Go | **~4–5 €/mois** | 🇩🇪🇫🇮 UE | Meilleur rapport perf/prix. Backups auto +20 %. |
| **Scaleway** | DEV1-S / PRO2-XXS | ~6–10 €/mois | 🇫🇷 | Données en France. |
| **OVHcloud** | VPS Value/Essential | ~5–8 €/mois | 🇫🇷🇪🇺 | Données en France. |
| **Infomaniak** | Public Cloud / VPS | ~6–10 €/mois | 🇨🇭 | Suisse (hors UE, adéquation). |
| DigitalOcean | Droplet 2 Go | ~12 $/mois | UE possible | Écosystème riche, un peu + cher. |

- **Sauvegardes PG** : cron `pg_dump` → stockage objet (~1–3 €/mo) **ou** managed-DB de l'hébergeur.
- **Ops à ta charge** : màj OS, sauvegardes, monitoring de base. **Très adapté mono-utilisatrice** et même début public.
- **Coût total au démarrage : ~6–10 €/mois** + domaine (~10–15 €/an).

### B. PaaS avec workers + Postgres managé — moins d'OPS (mieux pour le grand public)
On déclare 1 service web + 3 workers + un addon Postgres managé (sauvegardes/TLS/déploiement gérés).

| Fournisseur | Forme | Prix (ordre, petit) | Zone | Notes |
|---|---|---|---|---|
| **Scalingo** | web + 3 process + addon PostgreSQL | **~15–30 €/mois** | 🇫🇷 RGPD | Français, données FR, workers natifs. Très bon fit. |
| **Clever Cloud** | app PHP + workers + addon PG | ~15–35 €/mois | 🇫🇷 RGPD | Français, pay-as-you-go. |
| **Render** | Web (Docker) + 3 Background Workers + Managed PG | ~25–35 $/mois | UE possible | Docker-natif, très simple. |
| **Fly.io** | machines Docker + process groups + PG (Supabase/Neon) | ~10–25 $/mois | global | Flexible, PG managé via partenaire. |

- **Ops quasi nul** (backups + TLS + déploiement gérés). **Coût + élevé** mais scale sans effort → pratique quand on ouvre au public.
- Le `scheduler` reste en **exemplaire unique** (ne pas le répliquer).

### C. À écarter
- Mutualisé PHP (o2switch, OVH mutualisé) : pas de workers/scheduler, pas de Docker.
- Serverless (Vercel/Netlify functions) : pas de process longs ni PG natif.

## 3. Recommandation — en deux temps

**Étape 1 — accès copine (maintenant, coût mini, rapide)**
→ **VPS UE + Docker Compose.** Concrètement : **Hetzner CX22 (~5 €/mo)** si le prix prime, ou
**Scaleway / OVH FR (~6–8 €/mo)** si tu veux les **données en France** (meilleur récit RGPD). On a déjà le
`compose.yaml` + FrankenPHP-TLS → mise debout rapide. Sauvegarde PG par `pg_dump` planifié.
*Alternative « zéro ops » dès le départ + FR : **Scalingo** (~15–25 €/mo).*

**Étape 2 — ouverture grand public (plus tard)**
→ Soit on monte le VPS en gamme (+ multi-instances + Redis, déjà prévus ADR-0022 §5 / checklist §5),
soit on bascule sur **Scalingo/Clever Cloud** pour le Postgres managé + sauvegardes + scaling sans ops.

## 4. Ce qu'il faudra ensuite (une fois l'hébergeur choisi)
1. **Nom de domaine** (ex. `plume.<tld>`) → fixe l'URL de prod.
2. URL de prod → débloque : `*_REDIRECT_URI` OAuth (P2), `APP_FRONTEND_URL`, webhook Stripe, `MAIL_FROM`.
3. Un **SMTP** pour `MAILER_DSN` (les emails système) — souvent inclus/option chez l'hébergeur, ou service dédié.
4. Je déroule la `deployment-checklist.md` (secrets prod, migrations, `provision-app-role`, lancement des process).
