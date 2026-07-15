# ADR-0018 — Authentification par cookies httpOnly (SPA même-origine)

- **Statut : Accepté** (2026-07-14 — M2.0 ; amende ADR-0006)
- **Contexte** : jusqu'à M1, le SPA Nuxt stockait les JWT (access + refresh) dans des cookies
  lisibles par JavaScript. M2 introduit l'affichage de **contenus d'emails tiers** (aperçus
  de réponses) : la surface XSS devient réelle, et un vol de token par XSS donnerait un accès
  durable. La revue fin M1 avait tracé ce durcissement comme dette prioritaire de M2.

## Décision

1. **Les deux tokens vivent en cookies `httpOnly`**, posés/rafraîchis/effacés **par l'API**
   (lexik `set_cookies` pour l'access `plume_jwt` ; gesdinet `cookie` pour le refresh, path
   restreint `/api/v1/token`). `SameSite=Lax`, `secure`. Le JS ne voit jamais un token ;
   les corps de réponse ne les exposent plus (`remove_token_from_body`).
2. **Même-origine obligatoire** : le SPA parle à l'API via le proxy Nitro (`/api` en dev ET
   en build de prod), pour que les cookies `SameSite=Lax` voyagent en première partie.
3. **Le front ne connaît que son identité** : nouvel endpoint `GET /me` (le JS ne peut plus
   décoder le JWT) ; un témoin **non sensible** `plume_email` sert la garde de route.
   Extracteur `Authorization: Bearer` conservé **en parallèle** (outillage, tests fonctionnels).
4. **Garde anti-XSS outillée** : `vue/no-v-html` en erreur ESLint ; tout contenu tiers est
   rendu par interpolation texte échappée.

## Conséquences

- ✅ Un XSS ne peut plus exfiltrer les tokens (hors de portée du JS).
- ✅ La rotation single_use et la révocation au logout restent inchangées (gesdinet).
- ⚠️ Le SPA doit être servi même-origine (proxy) — contrainte de déploiement assumée.
- ⚠️ Le témoin `plume_email` n'est PAS une preuve d'authentification (l'autorité reste l'API :
  401 → refresh → logout) ; il n'est qu'un indice d'affichage.
