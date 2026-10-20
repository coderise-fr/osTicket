#!/bin/sh
# Build the osTicket images from the COMMITTED tree (git archive), not from the
# working directory. Reasons: the image is exactly the commit it is labelled with,
# uncommitted files can never leak into it, and Windows line-ending conversion
# (core.autocrlf) cannot corrupt the sources.
#
#   ./scripts/build.sh                                         # runtime image
#   ./scripts/build.sh -f compose.yaml -f compose.install.yaml build web   # install image
#
# Any arguments are passed to `docker compose` (default: `build`). Run from anywhere.
set -eu

here="$(cd "$(dirname "$0")/.." && pwd)"
repo="$(cd "$here/../.." && pwd)"

if [ -n "$(git -C "$repo" status --porcelain -- . ':!CLAUDE.md' 2>/dev/null)" ]; then
    echo "build.sh: warning: uncommitted changes are NOT part of the image (it is built from HEAD)." >&2
fi

ctx="$(mktemp -d)"
trap 'rm -rf "$ctx"' EXIT INT TERM
git -C "$repo" archive --format=tar HEAD | tar -x -C "$ctx"

# Docker on Windows needs a Windows path for the build context.
if command -v cygpath >/dev/null 2>&1; then
    OST_CONTEXT="$(cygpath -m "$ctx")"
else
    OST_CONTEXT="$ctx"
fi
OST_REVISION="$(git -C "$repo" rev-parse HEAD)"
export OST_CONTEXT OST_REVISION

[ $# -gt 0 ] || set -- build
cd "$here"
docker compose "$@"
