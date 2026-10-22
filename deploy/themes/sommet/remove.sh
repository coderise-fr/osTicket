#!/bin/sh
# Thème « Sommet » : retire le thème de deploy/docker/overlay/ (puis committer et
# reconstruire l'image : le portail retrouve le thème d'origine d'osTicket).
#
#   ./remove.sh                     retire les fichiers installés par apply.sh
#   ./remove.sh --dry-run           affiche ce qui serait retiré, sans rien écrire
#   ./remove.sh --force             retire aussi les fichiers du thème modifiés à la main
#   ./remove.sh [options] DOSSIER   autre dossier d'overlay (par défaut deploy/docker/overlay)
#
# Ne retire que les fichiers listés dans le manifeste écrit par apply.sh, et seulement
# s'ils n'ont pas été modifiés depuis (sinon ils sont signalés et conservés, code 1).
# Les autres fichiers de l'overlay (plugins, packs de langue, autre thème) ne sont
# jamais touchés ; les dossiers devenus vides sont retirés, jamais l'overlay lui-même.
# Idempotent : sans manifeste, le script indique que le thème n'est pas installé.
set -eu

THEME=sommet
here="$(cd "$(dirname "$0")" && pwd)"
manifest_rel="include/client/$THEME/installed.manifest"

dry=0; force=0; dst=""
for a in "$@"; do
    case "$a" in
        --dry-run|-n) dry=1 ;;
        --force|-f) force=1 ;;
        -h|--help) sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "remove.sh: option inconnue : $a" >&2; exit 2 ;;
        *) [ -z "$dst" ] || { echo "remove.sh: un seul dossier d'overlay" >&2; exit 2; }; dst="$a" ;;
    esac
done
[ -n "$dst" ] || dst="$here/../../docker/overlay"
[ -d "$dst" ] || { echo "remove.sh: dossier introuvable : $dst" >&2; exit 2; }
dst="$(cd "$dst" && pwd)"
manifest="$dst/$manifest_rel"

if [ ! -f "$manifest" ]; then
    echo "Thème Sommet non installé dans $dst (pas de manifeste) : rien à faire."
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
# Retire les dossiers créés par le thème, seulement s'ils sont maintenant vides.
sort -ru "$dirs" | while read -r d; do
    while [ -n "$d" ] && [ "$d" != . ]; do
        rmdir "$dst/$d" 2>/dev/null || break
        d="$(dirname "$d")"
    done
done
if [ "$kept" = 0 ]; then
    echo "Thème Sommet retiré de $dst ($removed fichier(s))."
else
    echo "Thème Sommet retiré partiellement : $kept fichier(s) modifié(s) conservé(s), manifeste gardé." >&2
    exit 1
fi
