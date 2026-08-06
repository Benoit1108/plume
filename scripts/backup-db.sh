#!/usr/bin/env bash
# Sauvegarde PostgreSQL de Plume (VPS self-managed). À planifier par cron, ex. :
#   0 3 * * *  /opt/plume/scripts/backup-db.sh >> /var/log/plume-backup.log 2>&1
#
# CHIFFRÉE (revue OPS-P2a) : le dump contient tout le carnet d'adresses des clientes. La checklist
# de déploiement l'annonçait chiffré ; ce script se contentait de gzip, sur la MÊME machine que la
# base. Désormais :
#   - chiffrement `age` avec une clé PUBLIQUE (la clé privée n'est PAS sur le serveur : sans elle,
#     un serveur compromis ne livre pas les données) ;
#   - copie hors machine si `PLUME_BACKUP_REMOTE` est défini (rclone : S3, B2, Scaleway…) ;
#   - vérification de restauration périodique (voir `--verify`, à planifier une fois par mois).
#
# Restauration :
#   age -d -i cle-privee.txt plume_<date>.sql.gz.age | gunzip \
#     | docker compose -f compose.prod.yaml exec -T database psql -U plume plume
set -euo pipefail

BACKUP_DIR="${PLUME_BACKUP_DIR:-/opt/plume/backups}"
RETENTION_DAYS="${PLUME_BACKUP_RETENTION_DAYS:-14}"
COMPOSE_FILE="${PLUME_COMPOSE_FILE:-/opt/plume/compose.prod.yaml}"
# Clé PUBLIQUE age (`age-keygen -o cle-privee.txt` → garder la privée AILLEURS, hors du serveur).
RECIPIENT="${PLUME_BACKUP_AGE_RECIPIENT:-}"
# Destination distante rclone, ex. « plume-backups:plume/db » (vide = pas de copie hors machine).
REMOTE="${PLUME_BACKUP_REMOTE:-}"
STAMP="$(date +%Y-%m-%d_%H%M%S)"

fail() { echo "ERREUR: $*" >&2; exit 1; }

# --- Vérification de restauration (mensuelle) : le dernier dump est-il restaurable ? ------------
# Restaure dans une base JETABLE et compte les tables. Une sauvegarde non testée n'est pas une
# sauvegarde — c'est le point que la checklist réclamait sans que rien ne l'exécute.
if [[ "${1:-}" == "--verify" ]]; then
  LATEST="$(find "$BACKUP_DIR" -name 'plume_*.sql.gz*' -type f -printf '%T@ %p\n' | sort -rn | head -1 | cut -d' ' -f2-)"
  [[ -n "$LATEST" ]] || fail "aucune sauvegarde à vérifier dans $BACKUP_DIR"
  echo "Vérification de $LATEST"

  decrypt() {
    if [[ "$LATEST" == *.age ]]; then
      [[ -n "${PLUME_BACKUP_AGE_IDENTITY:-}" ]] || fail "PLUME_BACKUP_AGE_IDENTITY (clé privée) requis pour --verify"
      age -d -i "$PLUME_BACKUP_AGE_IDENTITY" "$LATEST"
    else
      cat "$LATEST"
    fi
  }

  docker compose -f "$COMPOSE_FILE" exec -T database psql -U plume -d postgres \
    -c 'DROP DATABASE IF EXISTS plume_restore_check' -c 'CREATE DATABASE plume_restore_check' >/dev/null
  decrypt | gunzip | docker compose -f "$COMPOSE_FILE" exec -T database psql -q -U plume plume_restore_check >/dev/null
  TABLES="$(docker compose -f "$COMPOSE_FILE" exec -T database psql -tA -U plume plume_restore_check \
    -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'")"
  docker compose -f "$COMPOSE_FILE" exec -T database psql -U plume -d postgres \
    -c 'DROP DATABASE plume_restore_check' >/dev/null

  [[ "${TABLES//[$'\r\n ']/}" -ge 10 ]] || fail "restauration douteuse : $TABLES tables seulement"
  echo "OK  restauration vérifiée ($TABLES tables)"
  exit 0
fi

# --- Sauvegarde ---------------------------------------------------------------------------------
mkdir -p "$BACKUP_DIR"
TARGET="$BACKUP_DIR/plume_${STAMP}.sql.gz"

# pg_dump via le conteneur `database` (rôle propriétaire `plume`), compressé.
if [[ -n "$RECIPIENT" ]]; then
  command -v age >/dev/null || fail "age n'est pas installé (apt install age) alors qu'une clé est configurée"
  TARGET="${TARGET}.age"
  docker compose -f "$COMPOSE_FILE" exec -T database \
    pg_dump -U plume --no-owner --clean --if-exists plume \
    | gzip -9 | age -r "$RECIPIENT" > "$TARGET"
else
  echo "AVERTISSEMENT: PLUME_BACKUP_AGE_RECIPIENT vide — sauvegarde NON CHIFFRÉE (à corriger avant la prod)." >&2
  docker compose -f "$COMPOSE_FILE" exec -T database \
    pg_dump -U plume --no-owner --clean --if-exists plume \
    | gzip -9 > "$TARGET"
fi

# Une sauvegarde vide ou tronquée passerait inaperçue jusqu'au jour où on en a besoin.
SIZE="$(stat -c%s "$TARGET")"
[[ "$SIZE" -gt 1024 ]] || fail "sauvegarde suspecte ($SIZE octets) : $TARGET"

echo "OK  $TARGET  ($(du -h "$TARGET" | cut -f1))"

# --- Copie hors machine --------------------------------------------------------------------------
if [[ -n "$REMOTE" ]]; then
  command -v rclone >/dev/null || fail "rclone n'est pas installé alors que PLUME_BACKUP_REMOTE est défini"
  rclone copy "$TARGET" "$REMOTE" --quiet
  echo "OK  copié vers $REMOTE"
else
  echo "AVERTISSEMENT: PLUME_BACKUP_REMOTE vide — la sauvegarde ne survit pas à la perte du serveur." >&2
fi

# Rotation : supprime les sauvegardes locales plus vieilles que la rétention.
find "$BACKUP_DIR" -name 'plume_*.sql.gz*' -type f -mtime "+${RETENTION_DAYS}" -delete
