#!/bin/sh
# Remove the "Poste de travail" theme from the Docker overlay.
#
#   deploy/themes/poste-de-travail/remove.sh [-f] [OVERLAY_DIR]
#
#   OVERLAY_DIR  default: deploy/docker/overlay (next to this folder)
#   -f           also remove listed files that were modified in OVERLAY_DIR
#
# Deletes from OVERLAY_DIR the files listed in MANIFEST when they are identical
# to this theme's copy, then the folders that became empty (never the overlay
# folder itself, never a folder that still holds other files). A listed file
# with other content is kept and reported (exit code 1), unless -f is given.
# Other files of OVERLAY_DIR (plugins, language packs, another theme) are
# never touched. Idempotent.
set -eu

THEME=poste-de-travail
here="$(cd "$(dirname "$0")" && pwd)"
src="$here/overlay"
manifest="$here/MANIFEST"

force=0
if [ "${1:-}" = "-f" ]; then force=1; shift; fi
target="${1:-$here/../../docker/overlay}"

die() { echo "remove.sh: $*" >&2; exit 1; }
[ -d "$target" ] || die "overlay folder not found: $target"
target="$(cd "$target" && pwd)"
[ -f "$manifest" ] || die "MANIFEST missing"

files="$(grep -v '^#' "$manifest" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | LC_ALL=C sort)"
removed=0; kept=""
for f in $files; do
    [ -e "$target/$f" ] || continue
    if cmp -s "$src/$f" "$target/$f" || [ $force = 1 ]; then
        rm -f "$target/$f"
        removed=$((removed + 1))
    else
        kept="$kept
  $f"
    fi
done

# Empty folders left by the theme, deepest first
for f in $files; do
    d="$(dirname "$f")"
    while [ "$d" != "." ] && [ -n "$d" ]; do
        echo "$d"
        d="$(dirname "$d")"
    done
done | LC_ALL=C sort -u -r | while IFS= read -r d; do
    [ -d "$target/$d" ] && rmdir "$target/$d" 2>/dev/null || true
done

echo "remove.sh: $removed file(s) of theme $THEME removed from $target."
if [ -n "$kept" ]; then
    echo "remove.sh: kept (modified since installation, use -f to remove):$kept" >&2
    exit 1
fi
