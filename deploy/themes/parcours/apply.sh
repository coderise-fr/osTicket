#!/bin/sh
# Thème « Parcours » (Coderise) : installe le thème dans l'overlay Docker
# (deploy/docker/overlay/ par défaut), puis committer et reconstruire l'image
# (voir README.md).
#
#   ./apply.sh [--dry-run] [--force] [OVERLAY_DIR]
#
#   --dry-run    affiche ce qui serait fait, n'écrit rien
#   --force      écrase aussi les fichiers du thème modifiés à la main dans l'overlay
#   OVERLAY_DIR  dossier de l'overlay (défaut : deploy/docker/overlay du dépôt)
#
# Copie les fichiers listés dans MANIFEST (chemins relatifs à la racine d'osTicket)
# depuis overlay/. Refuse, sans rien copier :
#   - si MANIFEST et overlay/ ne correspondent pas ;
#   - si un autre thème est installé (atelier, poste-de-travail, sommet, ou tout autre
#     dossier de deploy/themes/) : son manifeste include/client/<slug>/installed.manifest,
#     son dossier include/client/<slug>/ ou assets/<slug>/, ou l'un de ses fichiers
#     identique, est présent dans l'overlay ;
#   - si un fichier à écrire existe déjà avec un autre contenu sans appartenir au thème
#     (personnalisation locale), ou s'il appartient au thème mais a été modifié à la
#     main (sauf --force).
# Écrit la liste des fichiers installés et leurs empreintes dans
# OVERLAY_DIR/include/client/parcours/installed.manifest (non servi : include/ est
# refusé par Apache). Ne touche jamais aux autres fichiers de l'overlay (plugins,
# packs de langue). Idempotent ; retire les fichiers d'une ancienne version du thème.
set -eu

THEME=parcours
OTHERS="atelier poste-de-travail sommet"
here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
src="$here/overlay"
list="$here/MANIFEST"
manifest_rel="include/client/$THEME/installed.manifest"

dry=0; force=0; dst=""
for a in "$@"; do
    case "$a" in
        --dry-run|-n) dry=1 ;;
        --force) force=1 ;;
        -h|--help) sed -n '2,25p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "apply.sh: option inconnue : $a" >&2; exit 2 ;;
        *) dst="$a" ;;
    esac
done
dst="${dst:-$repo/deploy/docker/overlay}"
[ -d "$dst" ] || { echo "apply.sh: dossier introuvable : $dst" >&2; exit 2; }
dst="$(cd "$dst" && pwd)"
manifest="$dst/$manifest_rel"
[ -f "$list" ] || { echo "apply.sh: MANIFEST absent" >&2; exit 2; }

sha() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum < "$1" | cut -c1-64
    else shasum -a 256 < "$1" | cut -c1-64; fi
}
recorded() { # empreinte notée dans notre manifeste pour un chemin, ou rien
    [ -f "$manifest" ] && awk -v p="$1" '$2 == p { print $1 }' "$manifest" || true
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT INT TERM

# 0. MANIFEST = contenu de overlay/
grep -v '^#' "$list" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | LC_ALL=C sort > "$tmp/files"
(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort) > "$tmp/actual"
if ! cmp -s "$tmp/files" "$tmp/actual"; then
    echo "apply.sh: MANIFEST ne correspond pas à overlay/ (mettre MANIFEST à jour) :" >&2
    diff "$tmp/files" "$tmp/actual" >&2 || true
    exit 1
fi

# 1. Un autre thème est-il installé ?
others="$OTHERS"
for d in "$repo"/deploy/themes/*/; do
    n="$(basename "$d")"
    case " $others $THEME " in *" $n "*) ;; *) case "$n" in _*) ;; *) others="$others $n" ;; esac ;; esac
done
for name in $others; do
    found=""
    if [ -f "$dst/include/client/$name/installed.manifest" ]; then
        found="include/client/$name/installed.manifest"
    elif [ -d "$dst/include/client/$name" ]; then
        found="include/client/$name/"
    elif [ -d "$dst/assets/$name" ]; then
        found="assets/$name/"
    elif [ -d "$repo/deploy/themes/$name/overlay" ]; then
        found="$(cd "$repo/deploy/themes/$name/overlay" && find . -type f | sed 's|^\./||' | while read -r f; do
            if [ -f "$dst/$f" ] && [ "$(sha "$dst/$f")" = "$(sha "$repo/deploy/themes/$name/overlay/$f")" ]; then echo "$f"; fi
        done | head -n 1)"
    fi
    if [ -n "$found" ]; then
        echo "apply.sh: le thème « $name » est installé dans $dst ($found)." >&2
        echo "Retirez-le d'abord : deploy/themes/$name/remove.sh" >&2
        exit 1
    fi
done

# 2. Fichiers de l'overlay qui seraient écrasés
: > "$tmp/conflicts"
while read -r f; do
    [ -f "$dst/$f" ] || continue
    now="$(sha "$dst/$f")"
    [ "$now" = "$(sha "$src/$f")" ] && continue          # déjà à jour
    rec="$(recorded "$f")"
    if [ -z "$rec" ]; then echo "étranger  $f" >> "$tmp/conflicts"
    elif [ "$rec" != "$now" ] && [ "$force" = 0 ]; then echo "modifié   $f" >> "$tmp/conflicts"
    fi
done < "$tmp/files"
if [ -s "$tmp/conflicts" ]; then
    echo "apply.sh: ces fichiers de l'overlay seraient écrasés :" >&2
    sed 's/^/  /' "$tmp/conflicts" >&2
    echo "« étranger » : fichier qui n'appartient pas au thème (autre personnalisation) ; déplacez-le ou fusionnez-le à la main." >&2
    echo "« modifié » : fichier du thème modifié dans l'overlay ; relancez avec --force pour l'écraser." >&2
    echo "Rien n'a été copié." >&2
    exit 1
fi

# 3. Copie, puis retrait des fichiers d'une version précédente du thème
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
done < "$tmp/files"
if [ -f "$manifest" ]; then
    while read -r h f; do
        case "$h" in ''|'#'*) continue ;; esac
        case "$f" in /*|*..*) continue ;; esac
        grep -qxF "$f" "$tmp/files" && continue
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
    echo "Thème Parcours déjà à jour dans $dst."
else
    echo "Thème Parcours installé dans $dst ($(wc -l < "$manifest" | tr -d ' ') fichiers)."
    echo "Ensuite : git add -A deploy/docker/overlay && git commit, puis deploy/docker/scripts/build.sh (voir README.md)."
fi
