#!/bin/sh
# Deploy an image tag built by GitHub Actions, with a backup first and an automatic
# rollback to the previous tag if the stack does not become healthy.
#
#   droplet/update.sh sha-abc1234
#
# Run as the deploy user from anywhere. After the update, if the schema changed
# (include/upgrader/streams/core.sig differs between the two commits), open
# https://<domain>/scp/upgrade.php as an administrator.
set -eu

[ $# -eq 1 ] || { echo "usage: $0 <image-tag>   (e.g. sha-abc1234)" >&2; exit 2; }
new="$1"
here="$(cd "$(dirname "$0")/.." && pwd)"
cd "$here"
[ -f .env ] || { echo "update: .env missing (see droplet/README.md)" >&2; exit 1; }

get() { sed -n "s/^$1=//p" .env | tail -n 1; }
prev="$(get OST_VERSION)"
[ "$prev" != "$new" ] || echo "update: $new is already the configured tag, re-checking."

set_tag() { sed -i "s|^OST_VERSION=.*|OST_VERSION=$1|" .env; }

echo "update: backup"
./droplet/backup-local.sh

echo "update: pull $(get OST_IMAGE):$new"
set_tag "$new"
if ! docker compose pull web cron; then
    set_tag "$prev"
    echo "update: pull failed (wrong tag, or 'docker login registry.digitalocean.com' missing). Nothing changed." >&2
    exit 1
fi

echo "update: restart"
if docker compose up -d --no-build --wait --remove-orphans; then
    docker compose ps
    echo "update: $prev -> $new OK. Keep the previous image until you have checked the site."
else
    echo "update: NOT healthy, rolling back to $prev" >&2
    set_tag "$prev"
    docker compose up -d --no-build --wait --remove-orphans || true
    docker compose ps
    exit 1
fi
