#!/usr/bin/env bash
# Sauvegarde PostgreSQL de Plume (VPS self-managed). À planifier par cron, ex. :
#   0 3 * * *  /opt/plume/scripts/backup-db.sh >> /var/log/plume-backup.log 2>&1
# Restauration : gunzip -c <fichier>.sql.gz | docker compose -f compose.prod.yaml exec -T database psql -U plume plume
set -euo pipefail

BACKUP_DIR="${PLUME_BACKUP_DIR:-/opt/plume/backups}"
RETENTION_DAYS="${PLUME_BACKUP_RETENTION_DAYS:-14}"
COMPOSE_FILE="${PLUME_COMPOSE_FILE:-/opt/plume/compose.prod.yaml}"
STAMP="$(date +%Y-%m-%d_%H%M%S)"

mkdir -p "$BACKUP_DIR"

# pg_dump via le conteneur `database` (rôle propriétaire `plume`), compressé.
docker compose -f "$COMPOSE_FILE" exec -T database \
  pg_dump -U plume --no-owner --clean --if-exists plume \
  | gzip -9 > "$BACKUP_DIR/plume_${STAMP}.sql.gz"

echo "OK  $BACKUP_DIR/plume_${STAMP}.sql.gz  ($(du -h "$BACKUP_DIR/plume_${STAMP}.sql.gz" | cut -f1))"

# Rotation : supprime les sauvegardes plus vieilles que la rétention.
find "$BACKUP_DIR" -name 'plume_*.sql.gz' -type f -mtime "+${RETENTION_DAYS}" -delete
