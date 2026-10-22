#!/bin/sh
# Which osTicket core files overridden by this theme changed since the theme
# was written? Run it after updating osTicket (merge of upstream, new release).
#
#   deploy/themes/poste-de-travail/check-drift.sh [OSTICKET_ROOT]
#   deploy/themes/poste-de-travail/check-drift.sh --rev GIT_REVISION [REPO]
#
#   OSTICKET_ROOT  osTicket tree to check (default: the root of this repository)
#   --rev          read the files from a git revision instead (e.g. upstream/develop)
#
# BASE.sha256 holds the SHA-256 of each overridden core file as it was in the
# commit the theme was built on (line endings normalised to LF, as in git).
# Output: one line per file, OK / CHANGED / MISSING. Exit code 0 = no drift,
# 1 = at least one file changed or disappeared (rebase the theme's copy:
# `git diff <base> <new> -- <file>` shows what to report), 2 = usage error.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
base="$here/BASE.sha256"
[ -f "$base" ] || { echo "check-drift.sh: BASE.sha256 missing" >&2; exit 2; }

rev=""
if [ "${1:-}" = "--rev" ]; then
    [ -n "${2:-}" ] || { echo "usage: check-drift.sh --rev GIT_REVISION [REPO]" >&2; exit 2; }
    rev="$2"; root="${3:-$here/../../..}"
else
    root="${1:-$here/../../..}"
fi
[ -d "$root" ] || { echo "check-drift.sh: not a folder: $root" >&2; exit 2; }

if command -v sha256sum >/dev/null 2>&1; then
    sha() { sha256sum | cut -d' ' -f1; }
else
    sha() { shasum -a 256 | cut -d' ' -f1; }
fi

drift=0; total=0
basecommit="$(sed -n 's/^# base commit: *//p' "$base" | head -1)"
while IFS= read -r line; do
    line="$(printf '%s' "$line" | sed 's/\r$//')"
    case "$line" in ''|'#'*) continue ;; esac
    want="${line%% *}"
    path="${line#*  }"
    total=$((total + 1))
    if [ -n "$rev" ]; then
        if ! git -C "$root" cat-file -e "$rev:$path" 2>/dev/null; then
            echo "MISSING  $path"; drift=1; continue
        fi
        got="$(git -C "$root" show "$rev:$path" | sha)"
    else
        if [ ! -f "$root/$path" ]; then
            echo "MISSING  $path"; drift=1; continue
        fi
        # Windows checkouts (core.autocrlf) have CRLF: compare as LF, like git does
        got="$(sed 's/\r$//' "$root/$path" | sha)"
    fi
    if [ "$got" = "$want" ]; then
        echo "OK       $path"
    else
        echo "CHANGED  $path"; drift=1
    fi
done < "$base"

if [ $drift = 0 ]; then
    echo "check-drift.sh: $total core file(s) unchanged since ${basecommit:-the base commit}."
else
    echo "check-drift.sh: core files changed since ${basecommit:-the base commit}: report the changes into overlay/ (see README.md, \"Mise a jour d'osTicket\")." >&2
fi
exit $drift
