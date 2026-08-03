# ADR-0032 — Garde-fou de coût de l'IA générative

- **Statut** : accepté (2026-08-03)
- **Contexte** : la rédaction assistée peut appeler l'API Anthropic (payante). Avant toute mise en
  production, il faut une garantie forte contre une **facture démesurée** (boucle, abus, pic
  d'usage) — préoccupation explicite du porteur.

## Décision

Trois lignes de défense, de la plus fine à la plus dure :

1. **Défaut gratuit** (déjà en place, M1.4) : sans `ANTHROPIC_API_KEY`, le générateur `canned`
   (local, déterministe) est utilisé — coût zéro en dev/CI/E2E.
2. **Plafond par tenant / heure** (déjà en place) : rate-limiter `drafting_generation` (30/h) —
   borne un compte emballé.
3. **Garde-fou GLOBAL** (cet ADR), au-dessus de tous les tenants :
   - **Coupe-circuit** `AI_GENERATION_ENABLED=0` → plus aucun appel payant, instantanément.
   - **Plafond mensuel de jetons** `AI_MONTHLY_TOKEN_BUDGET` (entrée+sortie ; 0 = illimité).

**Comportement au blocage** : repli automatique sur le générateur **gratuit** (`canned`). Le produit
continue de fonctionner budget épuisé — **jamais d'échec de génération** ni de facture surprise.

## Mécanique

- Port `App\Drafting\Application\AiBudget` (`allowsGeneration()`, `record()`, `snapshot()`),
  adaptateur `DoctrineAiBudget`.
- Compteur durable `ai_usage` (agrégat mensuel `YYYY-MM` : jetons entrée/sortie + appels), table
  **hors tenant / hors RLS** (comme `audit_log`, hors ORM via `schema_filter`), upsert atomique
  (`ON CONFLICT`) — correct sous la concurrence des workers.
- `MessageGeneratorSelector` consulte `allowsGeneration()` **avant** d'appeler Claude ;
  `ClaudeMessageGenerator` appelle `record()` avec `usage.input_tokens/output_tokens` **dès** qu'une
  réponse est obtenue (le coût est engagé même si le contenu est ensuite jugé vide).
- **Lecture fail-open** : un compteur illisible ne bloque pas la génération — le **coupe-circuit**
  reste la garantie dure.
- **Visibilité** : `GET /admin/status` expose `aiUsage` (activé, plafond, jetons du mois, appels) ;
  affiché dans le back-office.

## Conséquences

- ✅ Une facture ne peut pas s'emballer : plafond + coupe-circuit, repli gratuit sans coupure de
  service. Testé (unité sélecteur/adaptateur + fonctionnel compteur/plafond/coupe-circuit/mois).
- ⚠️ Le plafond est en **jetons** (proxy du coût) et somme entrée+sortie sans pondérer leurs prix
  respectifs — volontairement **conservateur** pour un garde-fou (raffinable si besoin).
- 🟦 En prod : fixer `AI_MONTHLY_TOKEN_BUDGET` (défaut illimité) ; `AI_GENERATION_ENABLED` est le
  bouton d'arrêt d'urgence. Voir [`docs/ops/deployment-checklist.md`](../../ops/deployment-checklist.md).
