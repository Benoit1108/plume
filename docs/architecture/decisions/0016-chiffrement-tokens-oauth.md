# ADR-0016 — Chiffrement des tokens OAuth au repos

- **Statut : Accepté** (2026-07-14 — M2.1)
- **Contexte** : la passerelle email (ADR-0007) stocke des tokens OAuth Google/Microsoft.
  Un refresh token donne un accès durable à la boîte de l'utilisatrice : c'est la donnée la
  plus sensible du système. Il faut fixer l'algorithme, la gestion de clé et le comportement
  en cas d'absence de clé.

## Décision

1. **Chiffrement authentifié applicatif** : sodium `secretbox` (XSalsa20-Poly1305), nonce
   aléatoire préfixé au ciphertext, sortie base64 (`SodiumTokenCipher`, port `TokenCipher`).
   Le domaine ne manipule que le VO `EncryptedToken` — un token en clair n'existe qu'en
   mémoire, le temps d'un appel provider ; jamais en base, jamais en log, jamais en réponse API.
2. **Clé dédiée** `MAILBOX_ENCRYPTION_KEY` (32 octets aléatoires, base64) — distincte de la
   clé JWT et d'`APP_SECRET`, jamais commitée (gitleaks veille). Génération :
   `php -r "echo base64_encode(random_bytes(32));"`.
3. **Fail-fast en production** : clé absente ou malformée → exception au boot. **Hors prod**
   (dev/test/CI), une clé est dérivée d'`APP_SECRET` (generichash) : rien à générer pour
   travailler, aucun secret à committer.
4. **Rotation** : changer la clé invalide les ciphertexts existants → `TokenCipherFailure`
   au déchiffrement, traité comme une boîte à reconnecter (statut `ERROR`, reconsentement en
   un clic). Pas de re-chiffrement automatique en V1 (une seule utilisatrice).
5. **Effacement à la révocation** : `ConnectedMailbox::revoke()` met les tokens à `null` —
   la déconnexion ne laisse pas de ciphertext orphelin.

## Conséquences

- ✅ Un dump de base ne donne accès à aucune boîte ; la clé vit dans l'env, pas dans le dépôt.
- ✅ Dev/CI/E2E fonctionnent sans configuration (clé dérivée + connecteur OAuth factice).
- ⚠️ La rotation de clé impose une reconnexion (assumé en V1, tracer un re-chiffrement si multi-utilisateurs).
- ⚠️ La clé env est visible de quiconque lit l'environnement du conteneur — le modèle de
  menace V1 est le vol de base/backup, pas la compromission complète de l'hôte.
