# Déploiement VPS (Docker Compose) — Plume

Runbook : d'une VM nue à Plume en ligne pour la première utilisatrice. Complète
`deployment-checklist.md` (le QUOI surcharger) côté opérationnel (le COMMENT).

> ⚠️ Les fichiers `Dockerfile.prod`, `compose.prod.yaml`, `Caddyfile.prod` sont des **brouillons prêts
> à l'emploi, à valider au 1er déploiement** (build, TLS réel, flux cookie httpOnly same-origin, CSP du SPA).
> On les déroule ensemble sur la vraie VM — c'est l'étape de validation.

## 0. Prérequis
- VM **Ubuntu LTS 24.04 ou 26.04**, ≥ 2 vCPU / 4 Go / 40 Go (OVH/Scaleway). Repère confortable :
  **VPS-2 OVH** = 4 vCPU / 8 Go / 75 Go. Tout Plume tourne dans Docker → la version de l'hôte importe
  peu, il doit juste faire tourner Docker.
- **Domaine** : un enregistrement **A** `plume.exemple.fr` (et `www`) → IP publique du VPS.
- **Ports 80 + 443 ouverts** (Let's Encrypt + HTTPS).

## 1. Installer Docker
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"   # puis se reconnecter
```
> **(optionnel, utile si la VM a < 8 Go de RAM)** ajouter 2 Go de swap pour absorber le pic mémoire du
> build front (`nuxt generate`). Inutile sur un VPS-2 (8 Go), recommandé sur un VPS-1 (4 Go) :
> ```bash
> sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
> sudo mkswap /swapfile && sudo swapon /swapfile
> echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
> ```

## 2. Récupérer le code
```bash
sudo mkdir -p /opt/plume && sudo chown "$USER" /opt/plume
git clone <url-du-dépôt> /opt/plume && cd /opt/plume
```

## 3. Configurer les secrets
```bash
cp api/.env.prod.example api/.env.local             # gitignoré — défauts + rôle PROPRIÉTAIRE
cp api/.env.runtime.example api/.env.runtime.local  # gitignoré — rôle RUNTIME (plume_app, RLS active)
# Générer les secrets (openssl est présent par défaut — pas besoin de PHP sur l'hôte) :
echo "APP_SECRET=$(openssl rand -hex 32)"
echo "MAILBOX_ENCRYPTION_KEY=$(openssl rand -base64 32)"
echo "TOTP_ENCRYPTION_KEY=$(openssl rand -base64 32)"
echo "JWT_PASSPHRASE=$(openssl rand -base64 24)"
# Reporter ces 4 valeurs dans api/.env.local, puis éditer aussi : SERVER_NAME + DEFAULT_URI +
# APP_FRONTEND_URL + CORS = ton domaine ; mots de passe DB (owner `plume` et runtime `plume_app`) ;
# MAILER_DSN + MAIL_FROM.
# Enfin, reporter le MÊME mot de passe `plume_app` dans api/.env.runtime.local.
```

## 4. Démarrer la stack
```bash
docker compose -f compose.prod.yaml up -d --build
```
Services lancés : `database`, `app` (API + SPA + HTTPS), `worker`, `worker_io`, `scheduler`.

> **Rôles base (ADR-0023)** : `app`, `worker` et `worker_io` tournent sous le rôle **non-propriétaire
> `plume_app`**, donc SOUMIS à la Row-Level Security (isolation multi-tenant fail-closed en base).
> Le `scheduler`, les migrations et la console gardent le rôle **propriétaire** : leur travail est
> cross-tenant par conception. Si `api/.env.runtime.local` manque, l'API refuse de servir avec un
> message explicite — plutôt que de tourner sans isolation, la RLS échouant en silence.

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
