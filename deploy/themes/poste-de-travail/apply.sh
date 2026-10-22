#!/bin/sh
# Install the "Poste de travail" theme (Coderise) into the Docker overlay.
#
#   deploy/themes/poste-de-travail/apply.sh [-n] [OVERLAY_DIR]
#
#   OVERLAY_DIR  default: deploy/docker/overlay (next to this folder)
#   -n           dry run: checks only, writes nothing
#
# Copies every file listed in MANIFEST from overlay/ to OVERLAY_DIR, at the
# same relative path, then writes the list of installed files with their
# SHA-256 to OVERLAY_DIR/include/client/poste-de-travail/installed.manifest
# (under include/: never served by Apache). Refuses (and writes nothing) when:
#   - MANIFEST and overlay/ disagree;
#   - another theme is installed in OVERLAY_DIR: its
#     include/client/<slug>/installed.manifest exists (slugs atelier, sommet,
#     parcours, and any other folder of deploy/themes/), or one of its overlay
#     files is present there, identical (installation without manifest);
#   - a file it would write already exists in OVERLAY_DIR with other content
#     and does not belong to this theme's previous installation unchanged
#     (local customisation, another theme: remove or merge it first).
# Updates an earlier installation of this theme in place (files that left the
# theme are removed if unchanged). Never touches the other files of
# OVERLAY_DIR (plugins, language packs). Idempotent.
set -eu

THEME=poste-de-travail
OTHERS="atelier sommet parcours"
here="$(cd "$(dirname "$0")" && pwd)"
src="$here/overlay"
manifest="$here/MANIFEST"

dry=0
if [ "${1:-}" = "-n" ]; then dry=1; shift; fi
target="${1:-$here/../../docker/overlay}"

die() { echo "apply.sh: $*" >&2; exit 1; }

[ -d "$target" ] || die "overlay folder not found: $target"
target="$(cd "$target" && pwd)"
[ -f "$manifest" ] || die "MANIFEST missing"
installed_rel="include/client/$THEME/installed.manifest"
installed="$target/$installed_rel"

if command -v sha256sum >/dev/null 2>&1; then
    sha() { sha256sum < "$1" | cut -c1-64; }
else
    sha() { shasum -a 256 < "$1" | cut -c1-64; }
fi
recorded() { # hash recorded for a path in our installed.manifest, or nothing
    [ -f "$installed" ] && awk -v p="$1" '$2 == p { print $1 }' "$installed" || true
}
tmp="${TMPDIR:-/tmp}/ptdt-apply.$$"
trap 'rm -f "$tmp" "$tmp.m" "$tmp.c"' EXIT INT TERM

files="$(grep -v '^#' "$manifest" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | LC_ALL=C sort)"
actual="$(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort)"
if [ "$files" != "$actual" ]; then
    printf '%s\n' "$files" > "$tmp.m"
    die "MANIFEST does not match overlay/ (update MANIFEST):
$(printf '%s\n' "$actual" | diff "$tmp.m" - || true)"
fi

# 1. Another theme already installed?
others="$OTHERS"
for d in "$here"/../*/; do
    n="$(basename "$d")"
    case " $others " in *" $n "*) ;; *) others="$others $n" ;; esac
done
for name in $others; do
    case "$name" in "$THEME"|_*) continue ;; esac
    if [ -f "$target/include/client/$name/installed.manifest" ]; then
        die "the theme \"$name\" is installed in $target (include/client/$name/installed.manifest).
Remove it first: deploy/themes/$name/remove.sh"
    fi
    other="$here/../$name/overlay"
    [ -d "$other" ] || continue
    found="$(cd "$other" && find . -type f | sed 's|^\./||' | while IFS= read -r f; do
        if [ -f "$target/$f" ] && cmp -s "$other/$f" "$target/$f"; then echo "$f"; fi
    done | head -n 1)"
    if [ -n "$found" ]; then
        die "the theme \"$name\" is installed in $target (for example: $found).
Remove it first: deploy/themes/$name/remove.sh"
    fi
done

# 2. Files that exist with other content and are not ours, unchanged
printf '%s\n' "$files" | while IFS= read -r f; do
    [ -e "$target/$f" ] || continue
    cmp -s "$src/$f" "$target/$f" && continue
    rec="$(recorded "$f")"
    if [ -z "$rec" ] || [ "$rec" != "$(sha "$target/$f")" ]; then echo "$f"; fi
done > "$tmp.c"
if [ -s "$tmp.c" ]; then
    die "these files already exist in $target with other content (another theme or a local change):
$(cat "$tmp.c")
Nothing was written."
fi

# 3. Copy, then remove the files of a previous version that left the theme
n=0; same=0; gone=0
for f in $files; do
    if [ -e "$target/$f" ] && cmp -s "$src/$f" "$target/$f"; then same=$((same + 1)); continue; fi
    n=$((n + 1))
    [ $dry = 1 ] && { echo "would copy $f"; continue; }
    mkdir -p "$target/$(dirname "$f")"
    cp "$src/$f" "$target/$f"
done
if [ -f "$installed" ]; then
    while read -r h f; do
        case "$h" in ''|'#'*) continue ;; esac
        printf '%s\n' "$files" | grep -qxF "$f" && continue
        [ -f "$target/$f" ] || continue
        if [ "$(sha "$target/$f")" = "$h" ]; then
            gone=$((gone + 1))
            if [ $dry = 1 ]; then echo "would remove $f (no longer in the theme)"; else rm -f "$target/$f"; fi
        else
            echo "apply.sh: kept $f (no longer in the theme, modified since installation)" >&2
        fi
    done < "$installed"
fi

if [ $dry = 1 ]; then
    echo "apply.sh: dry run, $n file(s) to copy, $same already in place, $gone to remove."
    exit 0
fi
: > "$tmp"
for f in $files; do echo "$(sha "$target/$f")  $f" >> "$tmp"; done
mkdir -p "$(dirname "$installed")"
cmp -s "$tmp" "$installed" 2>/dev/null || cp "$tmp" "$installed"
echo "apply.sh: theme $THEME installed in $target ($n copied, $same already in place, $gone removed)."
echo "Next: commit deploy/docker/overlay, then deploy/docker/scripts/build.sh (see README.md)."
