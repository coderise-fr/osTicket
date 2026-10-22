#!/bin/sh
# Thème « Ticket de caisse » (Ownalytics) : retire le thème de l'overlay Docker
# (puis committer et reconstruire l'image : le portail retrouve le thème d'origine).
#
#   ./remove.sh [--dry-run] [--force] [OVERLAY_DIR]
#
#   OVERLAY_DIR  par défaut : deploy/docker/overlay
#   --dry-run    (-n) affiche ce qui serait retiré, sans rien écrire
#   --force      (-f) retire aussi les fichiers du thème modifiés à la main
#
# Ne retire que les fichiers notés dans le manifeste écrit par apply.sh
# (include/client/ownalytics-ticket-de-caisse/installed.manifest), et seulement s'ils
# n'ont pas été modifiés depuis (sinon ils sont signalés et conservés, avec le
# manifeste ; code de sortie 1). Retire ensuite les dossiers devenus vides, jamais
# l'overlay lui-même. Les autres fichiers (plugins, packs de langue, autre thème)
# ne sont jamais touchés. Idempotent : sans manifeste, il n'y a rien à faire.
set -eu

THEME=ownalytics-ticket-de-caisse
LABEL="Ticket de caisse"
here="$(cd "$(dirname "$0")" && pwd)"

dry=0; force=0; target=""
for a in "$@"; do
    case "$a" in
        -n|--dry-run) dry=1 ;;
        -f|--force) force=1 ;;
        -h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "remove.sh: option inconnue : $a" >&2; exit 2 ;;
        *) target="$a" ;;
    esac
done
[ -n "$target" ] || target="$here/../../docker/overlay"
[ -d "$target" ] || { echo "remove.sh: dossier introuvable : $target" >&2; exit 2; }
dst="$(cd "$target" && pwd)"
manifest_rel="include/client/$THEME/installed.manifest"
manifest="$dst/$manifest_rel"

if [ ! -f "$manifest" ]; then
    echo "Thème $LABEL non installé dans $dst (pas de manifeste) : rien à faire."
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
# Dossiers créés par le thème, retirés seulement s'ils sont vides (du plus profond au moins profond).
sort -ru "$dirs" | while read -r d; do
    while [ -n "$d" ] && [ "$d" != . ]; do
        rmdir "$dst/$d" 2>/dev/null || break
        d="$(dirname "$d")"
    done
done
if [ "$kept" = 0 ]; then
    echo "Thème $LABEL retiré de $dst ($removed fichier(s))."
else
    echo "Thème $LABEL retiré partiellement : $kept fichier(s) modifié(s) conservé(s), manifeste gardé." >&2
    exit 1
fi
