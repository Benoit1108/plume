# Déploiement VPS (Docker Compose) — Plume

Runbook : d'une VM nue à Plume en ligne pour la première utilisatrice. Complète
`deployment-checklist.md` (le QUOI surcharger) côté opérationnel (le COMMENT).

> ⚠️ Les fichiers `Dockerfile.prod`, `compose.prod.yaml`, `Caddyfile.prod` sont des **brouillons prêts
> à l'emploi, à valider au 1er déploiement** (build, TLS réel, flux cookie httpOnly same-origin, CSP du SPA).
> On les déroule ensemble sur la vraie VM — c'est l'étape de validation.

## 0. Prérequis
- VM **Ubuntu 24.04 LTS**, ~2 vCPU / 4 Go / 40 Go (OVH/Scaleway).
- **Domaine** : un enregistrement **A** `plume.exemple.fr` (et `www`) → IP publique du VPS.
- **Ports 80 + 443 ouverts** (Let's Encrypt + HTTPS).

## 1. Installer Docker
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"   # puis se reconnecter
```

## 2. Récupérer le code
```bash
sudo mkdir -p /opt/plume && sudo chown "$USER" /opt/plume
git clone <url-du-dépôt> /opt/plume && cd /opt/plume
```

## 3. Configurer les secrets
```bash
cp api/.env.prod.example api/.env.local     # gitignoré
# Générer les secrets :
php -r "echo 'APP_SECRET='.bin2hex(random_bytes(32)).PHP_EOL;"
php -r "echo 'MAILBOX_ENCRYPTION_KEY='.base64_encode(random_bytes(32)).PHP_EOL;"
php -r "echo 'TOTP_ENCRYPTION_KEY='.base64_encode(random_bytes(32)).PHP_EOL;"
# Éditer api/.env.local : SERVER_NAME + DEFAULT_URI + APP_FRONTEND_URL + CORS = ton domaine ;
# mots de passe DB (owner `plume` et runtime `plume_app`) ; MAILER_DSN + MAIL_FROM.
```

## 4. Démarrer la stack
```bash
docker compose -f compose.prod.yaml up -d --build
```
Services lancés : `database`, `app` (API + SPA + HTTPS), `worker`, `worker_io`, `scheduler`.

## 5. Initialiser l'application (une fois)
```bash
# Clés JWT (à persister : voir note volume ci-dessous) — passphrase = JWT_PASSPHRASE d'.env.local
docker compose -f compose.prod.yaml exec app php bin/console lexik:jwt:generate-keypair --overwrite
# Migrations (rôle propriétaire) + rôle runtime RLS + cache prod
docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f compose.prod.yaml exec app php bin/console app:db:provision-app-role
docker compose -f compose.prod.yaml exec app php bin/console cache:warmup
# Compte de la première utilisatrice (mot de passe demandé) :
docker compose -f compose.prod.yaml exec app php bin/console app:user:create <email-copine>
```
> **JWT keys** : persistées via le volume `jwt-keys:/app/config/jwt` (déjà déclaré dans `compose.prod.yaml`) —
> générées une fois par la commande ci-dessus, elles survivent aux rebuilds.

## 6. Vérifier
```bash
curl -fsS https://plume.exemple.fr/api/v1/health      # 200
# Ouvrir https://plume.exemple.fr → écran de connexion, se connecter avec le compte créé.
docker compose -f compose.prod.yaml logs -f app       # TLS obtenu ? erreurs ?
```
Points à valider ce jour-là : certificat Let's Encrypt OK, le SPA se charge, **le login pose bien le
cookie httpOnly** (même origine via `/api`), pas d'erreur CSP en console (sinon affiner la CSP du SPA
dans `Caddyfile.prod`).

## 7. Sauvegardes (cron)
```bash
chmod +x scripts/backup-db.sh
( crontab -l 2>/dev/null; echo "0 3 * * * PLUME_COMPOSE_FILE=/opt/plume/compose.prod.yaml /opt/plume/scripts/backup-db.sh >> /var/log/plume-backup.log 2>&1" ) | crontab -
```

## 8. Mettre à jour (déploiement suivant)
```bash
cd /opt/plume && git pull
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f compose.prod.yaml exec app php bin/console cache:warmup
```

## Reste dépendant d'étapes ultérieures
- **OAuth (P2)** : renseigner `GOOGLE_*` / `MICROSOFT_*` + `*_REDIRECT_URI` = domaine (client OAuth de prod).
- **Plafond IA** : fixer `AI_MONTHLY_TOKEN_BUDGET` avant ouverture publique.
- **Stripe** : clés + Price IDs + secret webhook (compte de la copine = offert → non bloquant).
- **Sentry** : `SENTRY_DSN` si error-tracking souhaité.
- **Multi-instances / public** : Redis + réplicas `worker_io` (checklist §5), `scheduler` reste unique.
