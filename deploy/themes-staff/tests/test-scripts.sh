#!/bin/sh
# Tests des scripts du thème coderise-agent (apply.sh, remove.sh) sur des copies
# temporaires de l'overlay. Usage : sh deploy/themes-staff/tests/test-scripts.sh
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
pkg="$repo/deploy/themes-staff/coderise-agent"
real_overlay="$repo/deploy/docker/overlay"
THEME=coderise-agent
mrel="include/staff/$THEME/installed.manifest"

root="$(mktemp -d)"
trap 'rm -rf "$root"' EXIT INT TERM
fails=0

ok() { echo "ok    $1"; }
bad() { echo "FAIL  $1"; fails=$((fails + 1)); }
fresh() { mktemp -d "$root/case.XXXXXX"; }
snap() { (cd "$1" && find . | LC_ALL=C sort | while read -r p; do
    if [ -f "$p" ]; then echo "$p $(sha256sum < "$p" | cut -c1-64)"; else echo "$p"; fi
done | sha256sum | cut -c1-64); }
manifest_files() { grep -v '^#' "$pkg/MANIFEST" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d'; }

# (a) --dry-run writes nothing
d="$(fresh)"
arc=0; sh "$pkg/apply.sh" --dry-run "$d" >/dev/null 2>&1 || arc=1
if [ "$arc" = 0 ] && [ -z "$(find "$d" -mindepth 1 | head -n 1)" ]; then ok "a: dry-run n'écrit rien"; else bad "a: dry-run a écrit dans l'overlay"; fi

# (b) install: every MANIFEST file + installed.manifest
d="$(fresh)"
if sh "$pkg/apply.sh" "$d" >/dev/null 2>&1; then
    miss=0
    for f in $(manifest_files); do [ -f "$d/$f" ] || miss=1; done
    if [ "$miss" = 0 ] && [ -f "$d/$mrel" ]; then ok "b: installe MANIFEST et écrit installed.manifest"; else bad "b: fichiers ou manifeste manquants"; fi
else bad "b: apply.sh a échoué"; fi

# (h) manifest path and folder name = theme directory name
if [ -f "$d/$mrel" ] && [ "$(basename "$(dirname "$d/$mrel")")" = "$(basename "$pkg")" ]; then
    ok "h: installed.manifest dans include/staff/$THEME/"
else bad "h: installed.manifest absent ou dossier différent du slug"; fi

# (c) second apply: 0 changes
out="$(sh "$pkg/apply.sh" "$d" 2>&1 || true)"
case "$out" in *"déjà à jour"*) ok "c: seconde application sans changement" ;; *) bad "c: seconde application: $out" ;; esac

# (d) remove restores the starting state
d="$(fresh)"
mkdir -p "$d/include/plugins/x"; echo keep > "$d/include/plugins/x/a.txt"
before="$(snap "$d")"
drc=0; sh "$pkg/apply.sh" "$d" >/dev/null 2>&1 || drc=1
[ -f "$d/$mrel" ] || drc=1; sh "$pkg/remove.sh" "$d" >/dev/null 2>&1 || drc=1
after="$(snap "$d")"
if [ "$drc" = 0 ] && [ "$before" = "$after" ]; then ok "d: remove.sh restaure l'état initial"; else bad "d: l'overlay diffère après remove.sh"; fi

# (e) coexistence with client themes (copy of the real overlay)
d="$(fresh)"
cp -R "$real_overlay/." "$d/"
e=0
sh "$repo/deploy/themes/parcours/apply.sh" "$d" >/dev/null 2>&1 || e=1
sh "$pkg/apply.sh" "$d" >/dev/null 2>&1 || e=2
sh "$repo/deploy/themes/parcours/remove.sh" "$d" >/dev/null 2>&1 || e=3
sh "$repo/deploy/themes/sommet/apply.sh" "$d" >/dev/null 2>&1 || e=4
if [ "$e" = 0 ]; then ok "e: coexistence avec les thèmes client"; else bad "e: coexistence (étape $e)"; fi

# (f) hand-edited file: refuse; --force overwrites
d="$(fresh)"
sh "$pkg/apply.sh" "$d" >/dev/null 2>&1 || true
f1="$(manifest_files | head -n 1)"
echo edited >> "$d/$f1"
rc=0; sh "$pkg/apply.sh" "$d" >/dev/null 2>&1 || rc=$?
if [ "$rc" = 1 ] && grep -q edited "$d/$f1"; then
    sh "$pkg/apply.sh" --force "$d" >/dev/null 2>&1 || true
    if ! grep -q edited "$d/$f1"; then ok "f: refuse un fichier modifié, --force l'écrase"; else bad "f: --force n'a pas écrasé"; fi
else bad "f: refus attendu (code $rc)"; fi

# (g) MANIFEST and overlay/ differ: refuse (on a temporary copy of the package,
# never in the real overlay/)
d="$(fresh)"
pcopy="$root/pkg/deploy/themes-staff/$THEME"
mkdir -p "$pcopy"
cp -R "$pkg/." "$pcopy/"
echo x > "$pcopy/overlay/zz-test-extra.txt"
rc=0; sh "$pcopy/apply.sh" "$d" >/dev/null 2>&1 || rc=$?
if [ "$rc" = 1 ] && [ -z "$(find "$d" -mindepth 1 | head -n 1)" ]; then ok "g: refuse si MANIFEST et overlay/ diffèrent"; else bad "g: refus attendu (code $rc)"; fi

echo
if [ "$fails" = 0 ]; then echo "Tous les contrôles sont ok."; else echo "$fails contrôle(s) en échec."; fi
[ "$fails" = 0 ]
