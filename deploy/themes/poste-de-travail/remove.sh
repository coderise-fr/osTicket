#!/bin/sh
# Remove the "Poste de travail" theme (Coderise) from the Docker overlay.
#
#   deploy/themes/poste-de-travail/remove.sh [-n] [-f] [OVERLAY_DIR]
#
#   OVERLAY_DIR  default: deploy/docker/overlay (next to this folder)
#   -n           dry run: lists what would be removed, writes nothing
#   -f           also remove installed files that were modified since
#
# Deletes from OVERLAY_DIR the files listed in
# include/client/poste-de-travail/installed.manifest (written by apply.sh)
# when they still have their installed SHA-256, then the manifest, then the
# folders that became empty (never the overlay folder itself, never a folder
# that still holds other files). Without that manifest (installation by an
# older apply.sh), the files of MANIFEST identical to this theme's copy are
# removed. A modified file is kept and reported (exit code 1, the manifest is
# kept too), unless -f is given. Other files of OVERLAY_DIR (plugins, language
# packs, another theme) are never touched. Idempotent.
set -eu

THEME=poste-de-travail
here="$(cd "$(dirname "$0")" && pwd)"
src="$here/overlay"
manifest="$here/MANIFEST"

dry=0; force=0
while [ $# -gt 0 ]; do
    case "$1" in
        -n) dry=1; shift ;;
        -f) force=1; shift ;;
        *) break ;;
    esac
done
target="${1:-$here/../../docker/overlay}"

die() { echo "remove.sh: $*" >&2; exit 1; }
[ -d "$target" ] || die "overlay folder not found: $target"
target="$(cd "$target" && pwd)"
installed_rel="include/client/$THEME/installed.manifest"
installed="$target/$installed_rel"

if command -v sha256sum >/dev/null 2>&1; then
    sha() { sha256sum < "$1" | cut -c1-64; }
else
    sha() { shasum -a 256 < "$1" | cut -c1-64; }
fi
tmp="${TMPDIR:-/tmp}/ptdt-remove.$$"
trap 'rm -f "$tmp"' EXIT INT TERM

# "hash  path" lines: from the installed manifest, or from the theme's copy
if [ -f "$installed" ]; then
    sed -e 's/\r$//' -e '/^#/d' -e '/^[[:space:]]*$/d' "$installed" > "$tmp"
else
    [ -f "$manifest" ] || die "MANIFEST missing"
    grep -v '^#' "$manifest" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | while IFS= read -r f; do
        [ -f "$src/$f" ] && echo "$(sha "$src/$f")  $f"
    done > "$tmp"
fi

removed=0; kept=""
while read -r h f; do
    case "$f" in /*|*..*) die "path refused in the manifest: $f" ;; esac
    [ -e "$target/$f" ] || continue
    if [ "$(sha "$target/$f")" = "$h" ] || [ $force = 1 ]; then
        removed=$((removed + 1))
        if [ $dry = 1 ]; then echo "would remove $f"; else rm -f "$target/$f"; fi
    else
        kept="$kept
  $f"
    fi
done < "$tmp"

if [ $dry = 1 ]; then
    echo "remove.sh: dry run, $removed file(s) to remove."
    [ -z "$kept" ] || echo "remove.sh: would keep (modified since installation, use -f):$kept" >&2
    exit 0
fi
[ -n "$kept" ] || rm -f "$installed"

# Empty folders left by the theme, deepest first
{ cut -c67- "$tmp"; echo "$installed_rel"; } | while IFS= read -r f; do
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
    echo "remove.sh: kept (modified since installation, use -f to remove), manifest kept:$kept" >&2
    exit 1
fi
