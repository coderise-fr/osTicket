#!/bin/sh
# Forced command of the CI deploy key (see README, "Déploiement depuis GitHub Actions").
# The key can run nothing else on this server:
#
#   ssh deploy@host load             stdin: gzip'd `docker save` of an image -> docker load
#   ssh deploy@host deploy sha-abc1234   pull the checkout, then update.sh <tag>
#
# authorized_keys line (restrict = no shell, no tty, no forwarding):
#   restrict,command="/opt/osticket/deploy/docker/droplet/ci-receive.sh" ssh-ed25519 AAAA... ci-deploy
set -eu

# shellcheck disable=SC2086
set -- ${SSH_ORIGINAL_COMMAND:-}
here="$(cd "$(dirname "$0")" && pwd)"

case "${1:-}" in
    load)
        [ $# -eq 1 ] || exit 2
        gunzip | docker load
        ;;
    deploy)
        [ $# -eq 2 ] || exit 2
        printf '%s' "$2" | grep -Eq '^sha-[0-9a-f]{7}$' || { echo "ci-receive: bad tag" >&2; exit 2; }
        # compose files and scripts may have changed with the commit being deployed
        git -C "$here/../../.." pull --ff-only || echo "ci-receive: git pull failed, using the current checkout" >&2
        exec "$here/update.sh" "$2"
        ;;
    *)
        echo "ci-receive: unknown command" >&2
        exit 2
        ;;
esac
