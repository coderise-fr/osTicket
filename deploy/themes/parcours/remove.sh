#!/bin/sh
# Thème « Parcours » (Coderise) : retire le thème de l'overlay Docker (puis committer
# et reconstruire l'image : le portail retrouve le thème d'origine d'osTicket).
#
#   ./remove.sh [--dry-run] [--force] [OVERLAY_DIR]
#
#   --dry-run    affiche ce qui serait retiré, n'écrit rien
#   --force      retire aussi les fichiers du thème modifiés à la main
#   OVERLAY_DIR  dossier de l'overlay (défaut : deploy/docker/overlay du dépôt)
#
# Ne retire que les fichiers listés dans OVERLAY_DIR/include/client/parcours/
# installed.manifest (écrit par apply.sh), et seulement s'ils n'ont pas été modifiés
# depuis (sinon ils sont signalés et conservés, avec le manifeste ; code de sortie 1).
# Retire ensuite les dossiers devenus vides, jamais l'overlay lui-même. Les autres
# fichiers de l'overlay (plugins, packs de langue, autre thème) ne sont jamais touchés.
# Idempotent : sans manifeste, le script indique que le thème n'est pas installé.
set -eu

THEME=parcours
here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
manifest_rel="include/client/$THEME/installed.manifest"

dry=0; force=0; dst=""
for a in "$@"; do
    case "$a" in
        --dry-run|-n) dry=1 ;;
        --force|-f) force=1 ;;
        -h|--help) sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "remove.sh: option inconnue : $a" >&2; exit 2 ;;
        *) dst="$a" ;;
    esac
done
dst="${dst:-$repo/deploy/docker/overlay}"
[ -d "$dst" ] || { echo "remove.sh: dossier introuvable : $dst" >&2; exit 2; }
dst="$(cd "$dst" && pwd)"
manifest="$dst/$manifest_rel"

if [ ! -f "$manifest" ]; then
    echo "Thème Parcours non installé dans $dst (pas de manifeste) : rien à faire."
    exit 0
fi

sha() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum < "$1" | cut -c1-64
    else shasum -a 256 < "$1" | cut -c1-64; fi
}

kept=0; removed=0
dirs="$(mktemp)"
trap 'rm -f "$dirs"' EXIT INT TERM
while read -r h f; do
    case "$h" in ''|'#'*) continue ;; esac
    case "$f" in /*|*..*) echo "remove.sh: chemin refusé dans le manifeste : $f" >&2; exit 1 ;; esac
    [ -f "$dst/$f" ] || continue
    if [ "$(sha "$dst/$f")" != "$h" ] && [ "$force" = 0 ]; then
        echo "conservé  $f (modifié depuis l'installation ; --force pour le retirer)" >&2
        kept=$((kept + 1))
        continue
    fi
    echo "retrait   $f"
    removed=$((removed + 1))
    [ "$dry" = 1 ] || rm -f "$dst/$f"
    dirname "$f" >> "$dirs"
done < "$manifest"

if [ "$dry" = 1 ]; then
    echo "Simulation : $removed fichier(s) à retirer, $kept conservé(s), rien n'a été écrit."
    exit 0
fi
if [ "$kept" = 0 ]; then
    rm -f "$manifest"
    dirname "$manifest_rel" >> "$dirs"
fi
# Dossiers créés par le thème, retirés seulement s'ils sont vides (le plus profond d'abord)
sort -ru "$dirs" | while read -r d; do
    while [ -n "$d" ] && [ "$d" != . ]; do
        rmdir "$dst/$d" 2>/dev/null || break
        d="$(dirname "$d")"
    done
done
if [ "$kept" = 0 ]; then
    echo "Thème Parcours retiré de $dst ($removed fichier(s))."
else
    echo "Thème Parcours retiré partiellement : $kept fichier(s) modifié(s) conservé(s), manifeste gardé." >&2
    exit 1
fi
