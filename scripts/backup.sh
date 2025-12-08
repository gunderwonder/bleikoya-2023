#!/bin/bash
#
# Backup-script for bleikoya.net
# Kjører daglig via cron: 0 4 * * * bash /www/wp-content/themes/bleikoya-2023/scripts/backup.sh >> /home/bleikoya.net/backups/backup.log 2>&1
#

set -e

DATE=$(date +%Y%m%d)
KEEP_DAYS=30

# Konfigurasjon via miljøvariabler (fallback til prod-verdier)
WP_PATH="${WP_PATH:-/www}"
BACKUP_DIR="${BACKUP_DIR:-/home/bleikoya.net/backups}"

mkdir -p "$BACKUP_DIR"

# Database-backup
wp db export --path="$WP_PATH" - 2>/dev/null | gzip > "$BACKUP_DIR/db-$DATE.sql.gz"
echo "$(date): db-$DATE.sql.gz ($(du -h "$BACKUP_DIR/db-$DATE.sql.gz" | cut -f1))"

# Slett gamle backups
find "$BACKUP_DIR" -name "db-*.sql.gz" -mtime +$KEEP_DAYS -delete
