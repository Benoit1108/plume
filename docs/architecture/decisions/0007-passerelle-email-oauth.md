# 0007 — Passerelle email OAuth Gmail/Outlook, sans tracking d'ouverture

- **Statut** : Accepté
- **Date** : 2026-07-11

## Contexte
Il faut envoyer les mails de démarchage, capter les réponses, et (M3) ingérer des alertes. Une seule intégration email peut servir les trois.

## Décision
**Passerelle email** = bounded context technique encapsulant **Gmail API + Microsoft Graph via OAuth** derrière des ports (`EnvoiEmail`, `LectureBoite`). Envoi depuis la vraie boîte de la Traductrice, captation des réponses par threading (`Message-ID`/`References`), lecture d'un **label dédié** pour les alertes. **Tokens OAuth chiffrés** au repos, **scopes minimaux**. **Pas de tracking d'ouverture** (pixel/lien).

## Conséquences
- ✅ Bonne délivrabilité (envoi depuis l'adresse réelle), pas de mot de passe stocké.
- ✅ Une seule connexion couvre envoi + réponses + ingestion.
- ✅ Respect de la délivrabilité et du RGPD (pas de pixel espion).
- ⚠️ Gestion OAuth (consentement, refresh, révocation) par provider.
- 🔀 Adaptateur IMAP/SMTP générique possible en fallback ultérieur (non retenu en V1).

---

> **Livré (M2, 2026-07-14)** : Gmail (M2.1→M2.4) et **Outlook / Microsoft Graph** (M2.4)
> derrière un même jeu de ports (`MailboxConnector`/`MailSender`/`ReplyFetcher`), sélectionnés
> PAR FOURNISSEUR via des registres (le provider voyage signé dans le `state` OAuth). Envoi
> depuis l'adresse réelle, threading (Gmail `threadId` / Graph `conversationId`), relève par
> polling (ADR-0017), tokens chiffrés (ADR-0016), **aucun tracking d'ouverture** (tenu).
> Adaptateur factice par fournisseur pour dev/CI/E2E sans compte réel.
