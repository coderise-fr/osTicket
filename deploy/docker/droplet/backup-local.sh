#!/bin/sh
# Daily local backup (run by the systemd timer, or by hand): scripts/backup.sh into
# OST_BACKUP_DIR, then delete exports older than OST_BACKUP_KEEP_DAYS.
# A local copy protects against mistakes, NOT against losing the Droplet: copy
# OST_BACKUP_DIR and OST_SECRETS_DIR off the server (see README).
set -eu

here="$(cd "$(dirname "$0")/.." && pwd)"
cd "$here"
get() { [ -f .env ] && sed -n "s/^$1=//p" .env | tail -n 1 || true; }
dest="$(get OST_BACKUP_DIR)"; dest="${dest:-/var/backups/osticket}"
keep="$(get OST_BACKUP_KEEP_DAYS)"; keep="${keep:-14}"

./scripts/backup.sh "$dest"
find "$dest" -maxdepth 1 -type f -name 'osticket-*' -mtime "+$keep" -delete
echo "backup-local: kept the last $keep days in $dest"
