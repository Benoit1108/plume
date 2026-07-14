# ADR-0015 — Import CSV du Répertoire & dédoublonnage

- **Statut : Accepté** (2026-07-14 — livré en M1.1, tracé a posteriori lors de la revue fin M1 ;
  il était annoncé dans `M1-conception.md` §12)
- **Contexte** : l'import CSV amorce le Répertoire. Il faut fixer le format accepté, les
  bornes de sécurité et la règle de dédoublonnage.

## Décision

1. **Orchestration en couche Application** (`OrganizationImporter`) ; le parsing CSV est un
   détail d'Infrastructure (`CsvOrganizationParser` : délimiteur auto-détecté, en-têtes FR/EN).
2. **Bornes strictes** : 1 Mo / 1 000 lignes par fichier — un import ne doit jamais pouvoir
   saturer la base ni bloquer une requête.
3. **Dédoublonnage par nom normalisé** (casse/espaces), aligné sur l'invariant « nom unique
   par tenant » (index unique en filet) : doublon → ligne **ignorée** (comptée `skipped`),
   jamais de fusion silencieuse.
4. **Rapport d'import** systématique : `imported` / `skipped` / `failed` avec erreurs par ligne.

## Conséquences

- ✅ Idempotent en pratique : rejouer le même fichier n'écrase rien.
- ⚠️ Pas de fusion/enrichissement d'organisations existantes en V1 — le **dédoublonnage
  avancé** (nom + domaine email, écran de fusion) est différé à M3 (ingestion), qui en aura
  besoin pour les pistes candidates.
