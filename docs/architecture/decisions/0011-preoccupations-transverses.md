# 0011 — Préoccupations transverses (i18n, thème, TZ, versionnement…)

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Certaines préoccupations sont très coûteuses à rétrofitter une fois le projet avancé. On les décide dès le départ, même si l'implémentation complète est étalée dans le temps.

## Décisions

### Frontend
- **Design system** : **Nuxt UI** (composants accessibles, design tokens, thème intégré, Tailwind). Identité visuelle à personnaliser via `app.config.ts` pour éviter le rendu générique.
- **Thème clair/sombre** : géré par le *color mode* de Nuxt UI (`useColorMode`), aucune couleur en dur, préférence persistée.
- **i18n UI** : `@nuxtjs/i18n` (vue-i18n, format **ICU**). **FR + EN maintenus dès la V1**, ES ultérieurement. Stratégie `no_prefix` (locale via préférence/cookie, pas via l'URL).

### Distinction fondamentale
- **Locale de l'UI** (langue que voit la traductrice) **≠ langue cible du contenu généré** (langue que lit le prospect). La première relève de l'i18n ; la seconde du domaine (`LanguagePair`, génération). Ne jamais les confondre.

### Backend
- **i18n back** : composant Translation de Symfony (`default_locale: fr`, fallback `en`), ICU. Pour messages d'erreur et (M2) emails.
- **Versionnement d'API** : toutes les routes sous **`/api/v1`** (route_prefix API Platform + routes d'auth). Permet une v2 sans casser les clients (futur mobile).
- **Format d'erreur** : **RFC 7807 (`application/problem+json`)** — comportement natif d'API Platform, acté comme contrat.
- **Logs structurés** : Monolog avec processor injectant `tenant_id` + correlation-id sur chaque log (débuggabilité multi-tenant).

### Transverse
- **Fuseaux horaires** : stockage en **UTC**, affichage en TZ de l'utilisatrice. Critique pour relances/deadlines.
- **Formats locaux** (dates, nombres, monnaie) : via **Intl** (front) / **ICU** (back), jamais codés en dur.
- **Préférences utilisateur** : value object `Preferences` (locale, thème, timezone, notifications) porté par le Profil (contexte Account).
- **Champs d'audit** : convention `createdAt` / `updatedAt` sur les entités persistées (trait d'infrastructure) ; soft-delete au cas par cas.
- **Accessibilité (a11y)** : sémantique HTML, contrastes validés, navigation clavier — adossé aux tokens Nuxt UI.

## Conséquences
- ✅ Aucune de ces dimensions ne devra être rétrofittée en catastrophe.
- ✅ FR+EN et thème clair/sombre disponibles dès la V1.
- ⚠️ Double catalogue de traduction UI (FR+EN) à maintenir dès le départ.
- ⚠️ Discipline : aucune chaîne visible, couleur, date ou monnaie codée en dur.
