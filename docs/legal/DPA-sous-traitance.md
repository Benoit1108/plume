# Accord de sous-traitance (DPA, Art. 28 RGPD) — TRAME

> ⚠️ **TRAME À VALIDER JURIDIQUEMENT.** Coquille produite depuis l'architecture réelle pour gagner du
> temps. **Ne constitue pas un conseil juridique** : à relire/compléter par un professionnel avant tout
> usage contractuel. Points à renseigner par Benoit marqués 🟦.

## À quoi sert ce document

Plume a une **double casquette** (cf. [registre](registre-traitements-rgpd.md)). Ce fichier couvre les
**deux relations de sous-traitance** :

1. **Plume → ses clientes (traductrices)** : pour les **données de prospection**, Plume est **sous-traitant**
   de chaque traductrice (elle = responsable). Plume doit donc lui offrir les **garanties de l'Art. 28**
   (partie A ci-dessous — à intégrer aux CGU ou en annexe).
2. **Plume → ses sous-traitants ultérieurs** (Anthropic, Google, Microsoft, hébergeur, SMTP) : Plume doit
   s'assurer que **chacun** présente des garanties suffisantes et signer **leur** DPA respectif (partie B —
   registre des sous-traitants ultérieurs à tenir à jour).

---

## Partie A — Engagements de Plume comme sous-traitant (envers les traductrices)

> 🟦 À reformuler en clauses contractuelles (CGU / annexe DPA) par un juriste. Trame des points Art. 28.3 :

- **Objet, durée, nature, finalité** : héberger et opérer le CRM de prospection pour le compte de la
  cliente, pour la durée du contrat.
- **Traitement sur instruction documentée** uniquement (pas d'usage propre des données de prospection).
- **Confidentialité** : personnes autorisées engagées à la confidentialité.
- **Sécurité (Art. 32)** : mesures listées au [registre](registre-traitements-rgpd.md) (RLS multi-tenant,
  chiffrement des tokens, 2FA, HTTPS/HSTS, minimisation IA…).
- **Sous-traitants ultérieurs** : autorisation générale avec information préalable de tout changement et
  droit d'opposition (liste en partie B) ; répercussion des mêmes obligations.
- **Assistance** : aider la cliente à répondre aux demandes des personnes (accès/portabilité/effacement —
  outillés en self-service) et à ses obligations Art. 32-36.
- **Violation de données** : 🟦 notifier la cliente **sans délai indu** (définir le canal + le délai cible).
- **Sort des données en fin de contrat** : suppression (soft-delete + **purge à 30 j**, ADR-0025) ; export
  possible avant.
- **Audit** : mettre à disposition les informations nécessaires ; 🟦 modalités d'audit à préciser.

## Partie B — Registre des sous-traitants ultérieurs

> À tenir à jour ; toute évolution = information des clientes. 🟦 **Vérifier les liens/certifications** (DPF,
> CCT) et compléter les DPA signés avec chaque fournisseur.

| Sous-traitant | Rôle / finalité | Données concernées | Localisation | Encadrement transfert | DPA signé ? |
|---------------|-----------------|--------------------|--------------|-----------------------|-------------|
| **Anthropic** (API Claude) | Génération assistée de brouillons (optionnelle ; « canned » par défaut) | Contexte de piste **minimisé** — le nom du contact est interpolé **localement** (ADR-0014) | 🟦 États-Unis | 🟦 DPF / CCT à vérifier | 🟦 |
| **Google** (Gmail API) | Envoi & lecture des emails de démarchage de la boîte de la traductrice | Emails (objet/corps), tokens OAuth **chiffrés** | 🟦 États-Unis | 🟦 DPF / CCT à vérifier | 🟦 |
| **Microsoft** (Graph / Outlook) | Idem, fournisseur Outlook | Idem | 🟦 États-Unis | 🟦 DPF / CCT à vérifier | 🟦 |
| **Hébergeur** | Hébergement base de données + application | L'ensemble des données (au repos) | 🟦 **à choisir (UE de préférence)** | 🟦 (UE = pas de transfert) | 🟦 |
| **Emails transactionnels (SMTP/API)** | Vérification d'email, reset, notifications système | Email + contenu du message système | 🟦 à choisir | 🟦 | 🟦 |

> 💡 **Recommandation** : privilégier un **hébergeur et un service d'emails transactionnels dans l'UE**
> pour éviter/limiter les transferts hors UE (seuls Anthropic/Google/Microsoft resteraient aux US, chacun
> encadré). Choix d'hébergement **volontairement différé** (cf. [TODO Benoit](../ops/TODO-benoit.md)).

## Coordonnées & procédure

- **Responsable / point de contact RGPD** : 🟦 _nom + email._
- **Procédure de notification de violation** : 🟦 _canal, délai cible, contenu minimal._
- **Version / date** : 🟦 _à dater à la validation._
