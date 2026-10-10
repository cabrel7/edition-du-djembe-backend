#!/usr/bin/env bash
#
# Backup quotidien de la base MySQL (à planifier via cron).
#
# Usage :
#   DB_NAME=laravel DB_USER=root BACKUP_DIR=/var/backups/leseditionsdudjembe ./scripts/backup-db.sh
#
# Cron (quotidien 03:00) :
#   0 3 * * * DB_NAME=laravel DB_USER=root BACKUP_DIR=/var/backups/editions \
#     /home/cabrel/projects/leseditionsdudjembe/backend/scripts/backup-db.sh >> /var/log/editions-backup.log 2>&1
#
set -euo pipefail

DB_NAME="${DB_NAME:-laravel}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/leseditionsdudjembe}"
KEEP_DAYS="${KEEP_DAYS:-14}"

mkdir -p "$BACKUP_DIR"

STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$BACKUP_DIR/db-$DB_NAME-$STAMP.sql.gz"

MYSQL_PWD="$DB_PASS" mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  -u "$DB_USER" \
  "$DB_NAME" | gzip > "$FILE"

echo "Backup créé : $FILE ($(du -h "$FILE" | cut -f1))"

# Rétention
find "$BACKUP_DIR" -name "db-$DB_NAME-*.sql.gz" -mtime +"$KEEP_DAYS" -delete
