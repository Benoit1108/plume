# 0001 — DDD hexagonal, monolithe modulaire par contexte

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Produit destiné à évoluer (prospection → missions → facturation) tout en gardant un code propre et testable. Dev solo maîtrisant Symfony.

## Décision
Architecture **DDD hexagonale** en **monolithe modulaire**, découpé par **bounded context** (Prospection, Répertoire, Rédaction assistée, Passerelle email, Sourcing, Compte, Shared). Trois couches par contexte : `Domain` (PHP pur) / `Application` / `Infrastructure`. Dépendances vers l'intérieur uniquement.

## Conséquences
- ✅ Cœur métier isolé, testable sans framework ni DB.
- ✅ Ajout/retrait de contextes périphériques sans toucher au cœur.
- ✅ Extraction en services possible plus tard si nécessaire (frontières déjà nettes).
- ⚠️ Discipline requise sur le sens des dépendances et la pureté du domaine.
- ❌ Pas de microservices (complexité injustifiée à ce stade).
