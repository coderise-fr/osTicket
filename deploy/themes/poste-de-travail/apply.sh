#!/bin/sh
# Install the "Poste de travail" theme into the Docker overlay.
#
#   deploy/themes/poste-de-travail/apply.sh [-n] [OVERLAY_DIR]
#
#   OVERLAY_DIR  default: deploy/docker/overlay (next to this folder)
#   -n           dry run: checks only, copies nothing
#
# Copies every file listed in MANIFEST from overlay/ to OVERLAY_DIR, at the
# same relative path. Refuses (and copies nothing) when:
#   - MANIFEST and overlay/ disagree;
#   - another theme of deploy/themes/ is installed in OVERLAY_DIR (one of its
#     overlay files is present there, identical);
#   - a file it would write already exists in OVERLAY_DIR with other content
#     (another theme, or a local customisation: remove it first).
# Never touches the other files of OVERLAY_DIR (plugins, language packs).
# Idempotent: running it twice gives the same result.
set -eu

THEME=poste-de-travail
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

files="$(grep -v '^#' "$manifest" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | LC_ALL=C sort)"
actual="$(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort)"
[ "$files" = "$actual" ] || die "MANIFEST does not match overlay/ (update MANIFEST):
$(printf '%s\n' "$files" > "${TMPDIR:-/tmp}/ptdt-m.$$"; printf '%s\n' "$actual" | diff "${TMPDIR:-/tmp}/ptdt-m.$$" - || true; rm -f "${TMPDIR:-/tmp}/ptdt-m.$$")"

# 1. Another theme of deploy/themes/ already installed?
for other in "$here"/../*/; do
    other="$(cd "$other" && pwd)"
    name="$(basename "$other")"
    [ "$name" = "$THEME" ] && continue
    [ -d "$other/overlay" ] || continue
    found="$(cd "$other/overlay" && find . -type f | sed 's|^\./||' | while IFS= read -r f; do
        if [ -f "$target/$f" ] && cmp -s "$other/overlay/$f" "$target/$f"; then echo "$f"; fi
    done)"
    if [ -n "$found" ]; then
        die "the theme \"$name\" is installed in $target (for example: $(printf '%s\n' "$found" | head -1)).
Remove it first: deploy/themes/$name/remove.sh"
    fi
done

# 2. Files that exist with other content
conflicts=""
printf '%s\n' "$files" | {
    while IFS= read -r f; do
        if [ -e "$target/$f" ] && ! cmp -s "$src/$f" "$target/$f"; then
            echo "$f"
        fi
    done
} > "${TMPDIR:-/tmp}/ptdt-c.$$"
conflicts="$(cat "${TMPDIR:-/tmp}/ptdt-c.$$")"; rm -f "${TMPDIR:-/tmp}/ptdt-c.$$"
if [ -n "$conflicts" ]; then
    die "these files already exist in $target with other content (another theme or a local change):
$conflicts
Nothing was copied."
fi

# 3. Copy
n=0; same=0
for f in $files; do
    if [ -e "$target/$f" ]; then same=$((same + 1)); continue; fi
    n=$((n + 1))
    [ $dry = 1 ] && { echo "would copy $f"; continue; }
    mkdir -p "$target/$(dirname "$f")"
    cp "$src/$f" "$target/$f"
done
if [ $dry = 1 ]; then
    echo "apply.sh: dry run, $n file(s) to copy, $same already in place."
else
    echo "apply.sh: theme $THEME installed in $target ($n copied, $same already in place)."
    echo "Next: commit deploy/docker/overlay, then deploy/docker/scripts/build.sh (see README.md)."
fi
