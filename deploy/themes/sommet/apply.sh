#!/bin/sh
# Thème « Sommet » : installe le thème dans deploy/docker/overlay/ (puis committer et
# reconstruire l'image, voir README.md).
#
#   ./apply.sh                      installe ou met à jour le thème
#   ./apply.sh --dry-run            affiche ce qui serait fait, sans rien écrire
#   ./apply.sh --force              écrase aussi les fichiers du thème modifiés à la main
#   ./apply.sh [options] DOSSIER    autre dossier d'overlay (par défaut deploy/docker/overlay)
#
# Copie les fichiers listés dans MANIFEST (identique à overlay/). Refuse, sans rien
# copier, si un autre thème (atelier, poste-de-travail, parcours) est installé, si un
# fichier de l'overlay qui n'appartient pas au thème serait écrasé, ou si MANIFEST ne
# correspond pas à overlay/. Écrit la liste des fichiers installés (avec empreintes) dans
# <overlay>/include/client/sommet/installed.manifest (non servi par Apache).
# Ne touche jamais aux autres fichiers de l'overlay (plugins, packs de langue).
# Idempotent : relancer le script ne change rien si le thème est déjà à jour.
set -eu

THEME=sommet
OTHERS="atelier poste-de-travail parcours"
here="$(cd "$(dirname "$0")" && pwd)"
src="$here/overlay"
list="$here/MANIFEST"
manifest_rel="include/client/$THEME/installed.manifest"
# Tout autre dossier de deploy/themes/ est aussi un thème alternatif (ownalytics-*, etc.).
for d in "$here"/../*/; do
    n="$(basename "$d")"
    case "$n" in "$THEME"|_*) continue ;; esac
    case " $OTHERS " in *" $n "*) ;; *) OTHERS="$OTHERS $n" ;; esac
done

dry=0; force=0; dst=""
for a in "$@"; do
    case "$a" in
        --dry-run|-n) dry=1 ;;
        --force) force=1 ;;
        -h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "apply.sh: option inconnue : $a" >&2; exit 2 ;;
        *) [ -z "$dst" ] || { echo "apply.sh: un seul dossier d'overlay" >&2; exit 2; }; dst="$a" ;;
    esac
done
[ -n "$dst" ] || dst="$here/../../docker/overlay"
[ -d "$dst" ] || { echo "apply.sh: dossier introuvable : $dst" >&2; exit 2; }
dst="$(cd "$dst" && pwd)"
manifest="$dst/$manifest_rel"

sha() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum < "$1" | cut -c1-64
    else shasum -a 256 < "$1" | cut -c1-64; fi
}
recorded() { # empreinte notée dans notre manifeste pour un chemin, ou rien
    [ -f "$manifest" ] && awk -v p="$1" '$2 == p { print $1 }' "$manifest" || true
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT INT TERM

# 0. MANIFEST et overlay/ doivent lister exactement les mêmes fichiers.
[ -f "$list" ] || { echo "apply.sh: MANIFEST absent" >&2; exit 2; }
grep -v '^#' "$list" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | LC_ALL=C sort > "$tmp/want"
(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort) > "$tmp/have"
if ! cmp -s "$tmp/want" "$tmp/have"; then
    echo "apply.sh: MANIFEST ne correspond pas à overlay/ (mettre MANIFEST à jour) :" >&2
    diff "$tmp/want" "$tmp/have" | sed 's/^/  /' >&2 || true
    exit 1
fi

# 1. Un autre thème est-il installé ? Manifeste d'installation (chemin utilisé par les
#    scripts des thèmes), ou fichiers identiques à ceux d'un autre thème de deploy/themes/.
for name in $OTHERS; do
    if [ -f "$dst/include/client/$name/installed.manifest" ]; then
        echo "apply.sh: le thème « $name » est installé dans $dst ; retirez-le d'abord (deploy/themes/$name/remove.sh)." >&2
        exit 1
    fi
done
for other in "$here"/../*/; do
    name="$(basename "$other")"
    case "$name" in "$THEME"|_shared) continue ;; esac
    [ -d "$other/overlay" ] || continue
    found="$(cd "$other/overlay" && find . -type f | sed 's|^\./||' | while read -r f; do
        [ -f "$dst/$f" ] || continue
        [ -f "$src/$f" ] && cmp -s "$src/$f" "$other/overlay/$f" && continue   # fichier commun
        cmp -s "$dst/$f" "$other/overlay/$f" && echo "$f"
    done | head -n 3)"
    if [ -n "$found" ]; then
        echo "apply.sh: des fichiers du thème « $name » sont présents dans $dst :" >&2
        echo "$found" | sed 's/^/  /' >&2
        echo "Retirez-le d'abord (deploy/themes/$name/remove.sh)." >&2
        exit 1
    fi
done

# 2. Fichiers de l'overlay que le thème écraserait sans en être propriétaire.
conflicts="$(while read -r f; do
    [ -f "$dst/$f" ] || continue
    now="$(sha "$dst/$f")"
    [ "$now" = "$(sha "$src/$f")" ] && continue          # déjà à jour
    rec="$(recorded "$f")"
    if [ -z "$rec" ]; then echo "étranger  $f"            # pas à nous
    elif [ "$rec" != "$now" ] && [ "$force" = 0 ]; then echo "modifié   $f"   # à nous, modifié à la main
    fi
done < "$tmp/want")"
if [ -n "$conflicts" ]; then
    echo "apply.sh: ces fichiers de l'overlay seraient écrasés :" >&2
    echo "$conflicts" | sed 's/^/  /' >&2
    echo "« étranger » : fichier qui n'appartient pas au thème (autre personnalisation) ; déplacez-le ou fusionnez-le à la main." >&2
    echo "« modifié » : fichier du thème modifié dans l'overlay ; relancez avec --force pour l'écraser." >&2
    echo "Rien n'a été copié." >&2
    exit 1
fi

# 3. Copie, puis retrait des fichiers d'une version précédente du thème qui n'existent plus.
changed=0
: > "$tmp/manifest"
while read -r f; do
    h="$(sha "$src/$f")"
    echo "$h  $f" >> "$tmp/manifest"
    if [ -f "$dst/$f" ] && [ "$(sha "$dst/$f")" = "$h" ]; then continue; fi
    changed=$((changed + 1))
    echo "copie     $f"
    if [ "$dry" = 0 ]; then
        mkdir -p "$(dirname "$dst/$f")"
        cp "$src/$f" "$dst/$f"
    fi
done < "$tmp/want"
if [ -f "$manifest" ]; then
    while read -r h f; do
        case "$h" in ''|'#'*) continue ;; esac
        case "$f" in /*|*..*) continue ;; esac
        grep -qxF "$f" "$tmp/want" && continue
        [ -f "$dst/$f" ] || continue
        if [ "$(sha "$dst/$f")" = "$h" ]; then
            changed=$((changed + 1))
            echo "retrait   $f (absent de la nouvelle version du thème)"
            [ "$dry" = 1 ] || rm -f "$dst/$f"
        else
            echo "conservé  $f (modifié à la main, absent de la nouvelle version du thème)" >&2
        fi
    done < "$manifest"
fi

if [ "$dry" = 1 ]; then
    echo "Simulation : $changed fichier(s) à copier ou retirer, rien n'a été écrit."
    exit 0
fi
mkdir -p "$(dirname "$manifest")"
if [ -f "$manifest" ] && cmp -s "$tmp/manifest" "$manifest"; then :; else cp "$tmp/manifest" "$manifest"; changed=$((changed + 1)); fi
if [ "$changed" = 0 ]; then
    echo "Thème Sommet déjà à jour dans $dst."
else
    echo "Thème Sommet installé dans $dst ($(wc -l < "$manifest" | tr -d ' ') fichiers)."
    echo "Ensuite : git add -A deploy/docker/overlay && git commit, puis deploy/docker/scripts/build.sh (voir README.md)."
fi
