#!/bin/sh
# Restore a backup made by scripts/backup.sh onto an EMPTY stack (no containers, no volumes).
#
#   ./scripts/restore.sh osticket-<stamp>.sql osticket-config-<stamp>.tar
#
# Prerequisites: the same secrets/ folder as the backed-up stack (db_password.txt must match
# the password written in ost-config.php), and an image of the same osTicket version as the
# backup (or a newer one: then finish with the upgrade, see "Mises a jour" in README.md).
#
# What it does: starts the database only, loads the SQL export, unpacks the configuration
# archive into the (new) configuration volume, then starts web + cron.
set -eu

[ $# -eq 2 ] || { echo "usage: $0 <backup.sql> <config.tar>" >&2; exit 2; }
sql="$1"; cfg="$2"
[ -s "$sql" ] || { echo "restore: $sql not found" >&2; exit 1; }
[ -s "$cfg" ] || { echo "restore: $cfg not found" >&2; exit 1; }
tail -n 1 "$sql" | grep -q 'Dump completed' || { echo "restore: $sql is not a complete mariadb-dump export" >&2; exit 1; }
tar -tf "$cfg" | grep -q 'ost-config.php' || { echo "restore: $cfg does not contain ost-config.php" >&2; exit 1; }
sql="$(cd "$(dirname "$sql")" && pwd)/$(basename "$sql")"
cfg="$(cd "$(dirname "$cfg")" && pwd)/$(basename "$cfg")"

cd "$(cd "$(dirname "$0")/.." && pwd)"

# Refuse to overwrite a live stack: restoring is for a blank one.
if [ -n "$(docker compose ps -a -q)" ]; then
    echo "restore: this Compose project already has containers. Destroy it first (docker compose down -v) if you really want a blank stack." >&2
    exit 1
fi
project="$(docker compose config | sed -n 's/^name: //p' | head -n 1)"
if [ -n "$(docker volume ls -q --filter "label=com.docker.compose.project=$project")" ]; then
    echo "restore: project '$project' already has volumes. Remove them first (docker compose down -v)." >&2
    exit 1
fi

echo "restore: starting the database"
docker compose up -d --wait db

echo "restore: loading $sql"
docker compose exec -T db sh -c 'mariadb -uroot -p"$(cat /run/secrets/db_root_password)" osticket' < "$sql"

echo "restore: unpacking $cfg into the configuration volume"
# The services mount the volume read-only and `compose run -v` cannot override that, so a
# plain one-off container writes to it. `create` makes Compose create (and label) the volume.
docker compose create web
volume="${project}_ost-config"
image="$(docker compose config --images | grep '^coderise/osticket:' | grep -v -- '-install$' | head -n 1)"
# MSYS_NO_PATHCONV: Git Bash on Windows would rewrite "/etc/osticket" into a Windows path.
MSYS_NO_PATHCONV=1 docker run --rm -i --network none --cap-drop ALL --security-opt no-new-privileges \
    --user 33:33 -v "$volume:/etc/osticket" --entrypoint tar "$image" -C /etc/osticket -xf - < "$cfg"

echo "restore: starting web and cron"
docker compose up -d --wait
docker compose ps
echo "restore: done. Check the login page, a ticket with its attachment, and the mail account (Admin > Emails)."
