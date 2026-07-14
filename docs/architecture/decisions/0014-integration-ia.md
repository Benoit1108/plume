# ADR-0014 — Intégration IA (rédaction assistée)

- **Statut : Accepté** (2026-07-14 — livré en M1.4, tracé a posteriori lors de la revue fin M1 ;
  il était annoncé dans `M1-conception.md` §12)
- **Contexte** : la rédaction assistée (contexte `Drafting`) génère des brouillons de
  candidature/relance. Il faut choisir le fournisseur, l'isolation architecturale, la maîtrise
  des coûts, la gestion d'erreurs et le régime RGPD d'un appel à un service tiers.

## Décision

1. **Port applicatif `MessageGenerator`** : le domaine et l'application ignorent « Claude ».
   L'ACL (`ClaudeMessageGenerator`, Infrastructure) contient tout le vocabulaire Anthropic
   (endpoint, headers, format de réponse, parsing `SUBJECT:`/`OBJET:`).
2. **Générateur local par défaut** : sans `ANTHROPIC_API_KEY`, l'adaptateur
   `CannedMessageGenerator` (déterministe, gratuit) sert dev, tests, CI et E2E.
   La bascule est un pur choix d'environnement (`MessageGeneratorSelector`).
3. **Modèle piloté par env** : `DRAFTING_MODEL` (défaut `claude-sonnet-5` — la qualité
   rédactionnelle est la vitrine du produit), surchargeable sans redéploiement.
4. **Génération asynchrone** (worker, outbox) : jamais de requête HTTP bloquée par l'appel
   IA. Machine à états `GENERATING → READY | FAILED` avec **gardes d'état** (une redélivrance
   Messenger ne peut ni écraser un brouillon relu ni facturer un second appel).
5. **Maîtrise des coûts** : sortie bornée (`max_tokens` 1024), timeout 30 s, **rate limiting
   30 générations/heure par tenant** (sliding window) sur les deux routes de génération.
6. **Erreurs** : tout échec devient un **code stable** (`generation_failed`,
   `lead_unavailable`, `contact_not_allowed`) traduit côté front — jamais un message interne.
   Le détail technique part dans les logs (sans contenu de prompt).

## RGPD — sous-traitance Anthropic

Données transmises dans le prompt (minimisation) : nom de l'organisation cible, segment,
statut de la piste, paire de langues, **nom du contact** (personnalisation), présentation de
l'utilisatrice (bio, spécialités, signature) et le gabarit choisi. **Ne partent jamais** :
emails, téléphones, notes, historique du journal. La garde `doNotContact` est vérifiée à la
commande **et re-vérifiée par le worker** avant tout appel.

⚠️ Le nom du contact est une donnée personnelle d'un tiers transmise à un sous-traitant
(Anthropic, API Messages — pas d'entraînement sur les données API par défaut). Assumé en V1
(base légale : intérêt légitime B2B, cohérent avec la conception RGPD du produit) ;
**piste tracée pour M2** : interpoler `{{contact}}` localement après génération pour ne plus
transmettre le nom. Avant l'ouverture SaaS (V2) : registre de traitement + DPA (cf. ROADMAP).

## Conséquences

- ✅ Coût zéro par défaut, activation par une variable d'env, testable sans réseau.
- ✅ Le fournisseur est remplaçable (le port a deux implémentations dès le premier jour).
- ⚠️ Le rendu « canned » est volontairement rudimentaire : il valide le flux, pas la plume.
- ⚠️ Un seul worker consomme génération ET projections : si les volumes montent,
  dédier une queue (`messenger.transport` séparé) à la génération.
