#!/bin/sh
# Back up the osTicket stack: SQL export of the database + archive of the configuration
# volume (ost-config.php holds SECRET_SALT, which the encrypted mailbox passwords in the
# database depend on: the two files only make sense together).
#
#   ./scripts/backup.sh [destination-folder]        (default: ./backups)
#
# Attachments are stored in the database by default (osTicket "Database" file storage),
# so the SQL export contains them. If you switched attachment storage to a plugin
# (filesystem, S3), back that location up too.
#
# Works on the running stack (consistent snapshot thanks to --single-transaction).
# Run from anywhere; docker compose reads deploy/docker/.env (or COMPOSE_ENV_FILES).
# Keep the output and secrets/ OFF the server, encrypted.
set -eu

here="$(cd "$(dirname "$0")/.." && pwd)"
dest="${1:-$here/backups}"
stamp="$(date +%Y-%m-%d-%H%M%S)"

umask 077
mkdir -p "$dest"
dest="$(cd "$dest" && pwd)"
sql="$dest/osticket-$stamp.sql"
cfg="$dest/osticket-config-$stamp.tar"

cd "$here"

echo "backup: database -> $sql"
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$(cat /run/secrets/db_root_password)" --single-transaction --routines --triggers osticket' > "$sql"
tail -n 1 "$sql" | grep -q 'Dump completed' || { echo "backup: the SQL export looks incomplete: $sql" >&2; rm -f "$sql"; exit 1; }

echo "backup: configuration volume -> $cfg"
# MSYS_NO_PATHCONV: Git Bash on Windows would rewrite "/etc/osticket" into a Windows path.
MSYS_NO_PATHCONV=1 docker compose run --rm --no-deps -T --entrypoint tar web -C /etc/osticket -cf - . > "$cfg"
tar -tf "$cfg" | grep -q 'ost-config.php' || { echo "backup: ost-config.php missing from $cfg" >&2; rm -f "$cfg"; exit 1; }

echo "backup: done"
ls -l "$sql" "$cfg"
