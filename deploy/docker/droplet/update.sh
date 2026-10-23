#!/bin/sh
# Deploy an image tag already loaded on this server (by the CI through ci-receive.sh, or
# by hand with `docker load`), with a backup first and an automatic rollback to the
# previous tag if the stack does not become healthy.
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
image="$(get OST_IMAGE)"
prev="$(get OST_VERSION)"
[ "$prev" != "$new" ] || echo "update: $new is already the configured tag, re-checking."

docker image inspect "$image:$new" >/dev/null 2>&1 || {
    echo "update: image $image:$new is not on this server (the CI loads it first)." >&2
    exit 1
}

set_tag() { sed -i "s|^OST_VERSION=.*|OST_VERSION=$1|" .env; }

echo "update: backup"
./droplet/backup-local.sh

echo "update: restart on $new"
set_tag "$new"
if docker compose up -d --no-build --pull never --wait --remove-orphans; then
    docker compose ps
    echo "update: $prev -> $new OK."
    # keep the 4 newest images (current, previous, and two spares for a rollback)
    docker images "$image" --format '{{.Repository}}:{{.Tag}}' | grep -v -- '-install$' \
        | tail -n +5 | xargs -r docker rmi 2>/dev/null || true
else
    echo "update: NOT healthy, rolling back to $prev" >&2
    set_tag "$prev"
    docker compose up -d --no-build --pull never --wait --remove-orphans || true
    docker compose ps
    exit 1
fi
