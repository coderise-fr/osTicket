#!/bin/sh
# Thème « Ownalytics Atelier » : retire le thème de deploy/docker/overlay/ (puis committer et
# reconstruire l'image : le portail retrouve le thème d'origine d'osTicket).
#
#   ./remove.sh            retire les fichiers installés par apply.sh
#   ./remove.sh --dry-run  affiche ce qui serait retiré, sans rien écrire
#   ./remove.sh --force    retire aussi les fichiers du thème modifiés à la main
#
# Ne retire que les fichiers listés dans le manifeste écrit par apply.sh, et seulement
# s'ils n'ont pas été modifiés depuis (sinon ils sont signalés et conservés). Les autres
# fichiers de l'overlay (plugins, packs de langue, autre thème) ne sont jamais touchés.
# Idempotent : sans manifeste, le script indique que le thème n'est pas installé.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
dst="$repo/deploy/docker/overlay"
manifest_rel="include/client/ownalytics-atelier/installed.manifest"
manifest="$dst/$manifest_rel"

dry=0; force=0
for a in "$@"; do
    case "$a" in
        --dry-run) dry=1 ;;
        --force) force=1 ;;
        -h|--help) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "remove.sh: option inconnue : $a" >&2; exit 2 ;;
    esac
done

if [ ! -f "$manifest" ]; then
    echo "Thème Ownalytics Atelier non installé dans $dst (pas de manifeste) : rien à faire."
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
    if [ ! -f "$dst/$f" ]; then continue; fi
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
# Remove the folders the theme created, only when they are now empty.
sort -ru "$dirs" | while read -r d; do
    while [ -n "$d" ] && [ "$d" != . ]; do
        rmdir "$dst/$d" 2>/dev/null || break
        d="$(dirname "$d")"
    done
done
if [ "$kept" = 0 ]; then
    echo "Thème Ownalytics Atelier retiré de $dst ($removed fichier(s))."
else
    echo "Thème Ownalytics Atelier retiré partiellement : $kept fichier(s) modifié(s) conservé(s), manifeste gardé." >&2
    exit 1
fi
