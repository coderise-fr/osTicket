#!/bin/sh
# Thème « Ticket de caisse » (Ownalytics) : installe le thème dans l'overlay Docker
# (puis committer et reconstruire l'image, voir README.md).
#
#   ./apply.sh [--dry-run] [--force] [OVERLAY_DIR]
#
#   OVERLAY_DIR  par défaut : deploy/docker/overlay
#   --dry-run    (-n) affiche ce qui serait fait, sans rien écrire
#   --force      (-f) écrase aussi les fichiers du thème modifiés à la main dans l'overlay
#
# Copie dans OVERLAY_DIR chaque fichier listé dans MANIFEST (même chemin relatif) et
# note les empreintes dans OVERLAY_DIR/include/client/ownalytics-ticket-de-caisse/
# installed.manifest (sous include/ : jamais servi par Apache). Refuse, sans rien copier :
#   - si MANIFEST ne correspond pas à overlay/ ;
#   - si un AUTRE thème est installé : un fichier installed.manifest (ou *.manifest)
#     présent ailleurs dans OVERLAY_DIR que dans le dossier de ce thème, quel que soit le
#     nom du thème ; ou des fichiers identiques à ceux d'un autre thème de deploy/themes/
#     (thèmes sans manifeste installé, comme « poste-de-travail ») ;
#   - si un fichier à écrire existe déjà sans appartenir au thème (autre personnalisation),
#     ou appartient au thème mais a été modifié à la main (sauf --force).
# Ne touche jamais aux autres fichiers de l'overlay (plugins, packs de langue).
# Idempotent : relancé, il ne copie que ce qui a changé et retire les fichiers d'une
# ancienne version du thème (s'ils n'ont pas été modifiés).
set -eu

THEME=ownalytics-ticket-de-caisse
LABEL="Ticket de caisse"
here="$(cd "$(dirname "$0")" && pwd)"
src="$here/overlay"
list="$here/MANIFEST"

dry=0; force=0; target=""
for a in "$@"; do
    case "$a" in
        -n|--dry-run) dry=1 ;;
        -f|--force) force=1 ;;
        -h|--help) sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "apply.sh: option inconnue : $a" >&2; exit 2 ;;
        *) target="$a" ;;
    esac
done
[ -n "$target" ] || target="$here/../../docker/overlay"
[ -d "$target" ] || { echo "apply.sh: dossier introuvable : $target" >&2; exit 2; }
dst="$(cd "$target" && pwd)"
manifest_rel="include/client/$THEME/installed.manifest"
manifest="$dst/$manifest_rel"

die() { echo "apply.sh: $*" >&2; exit 1; }
sha() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum < "$1" | cut -c1-64
    else shasum -a 256 < "$1" | cut -c1-64; fi
}
recorded() { # empreinte notée dans notre manifeste pour un chemin, ou rien
    [ -f "$manifest" ] && awk -v p="$1" '$2 == p { print $1 }' "$manifest" || true
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT INT TERM

# 0. MANIFEST et overlay/ doivent correspondre.
[ -f "$list" ] || die "MANIFEST absent"
grep -v '^#' "$list" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' | LC_ALL=C sort > "$tmp/files"
(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort) > "$tmp/actual"
if ! cmp -s "$tmp/files" "$tmp/actual"; then
    echo "apply.sh: MANIFEST ne correspond pas à overlay/ (mettez MANIFEST à jour) :" >&2
    diff "$tmp/files" "$tmp/actual" >&2 || true
    exit 1
fi

# 1. Un autre thème est-il installé ?
#  a) n'importe quel manifeste d'installation hors du dossier de ce thème (générique :
#     include/client/<thème>/installed.manifest, chemin utilisé par « atelier » et les
#     thèmes suivants, ou tout autre *.manifest posé dans l'overlay) ;
others="$(cd "$dst" && find . -type f \( -name 'installed.manifest' -o -name '*.manifest' \) \
    | sed 's|^\./||' | grep -v -x -F "$manifest_rel" || true)"
if [ -n "$others" ]; then
    echo "apply.sh: un autre thème est installé dans $dst (manifeste d'installation trouvé) :" >&2
    echo "$others" | sed 's/^/  /' >&2
    echo "Retirez-le d'abord avec son remove.sh (deploy/themes/<thème>/remove.sh)." >&2
    exit 1
fi
#  b) fichiers identiques à ceux d'un autre thème de deploy/themes/ (thèmes qui n'écrivent
#     pas de manifeste dans l'overlay, comme « poste-de-travail »).
for other in "$here"/../*/; do
    other="$(cd "$other" && pwd)"
    name="$(basename "$other")"
    [ "$name" = "$THEME" ] && continue
    [ -d "$other/overlay" ] || continue
    found="$(cd "$other/overlay" && find . -type f | sed 's|^\./||' | while IFS= read -r f; do
        if [ -f "$dst/$f" ] && cmp -s "$other/overlay/$f" "$dst/$f"; then
            # un fichier commun aux deux thèmes (police identique…) ne prouve rien
            if [ -f "$src/$f" ] && cmp -s "$src/$f" "$dst/$f"; then continue; fi
            echo "$f"
        fi
    done | head -n 3)"
    if [ -n "$found" ]; then
        echo "apply.sh: des fichiers du thème « $name » sont présents dans $dst :" >&2
        echo "$found" | sed 's/^/  /' >&2
        echo "Retirez-le d'abord : deploy/themes/$name/remove.sh" >&2
        exit 1
    fi
done

# 2. Fichiers de l'overlay que le thème écraserait sans les posséder.
while IFS= read -r f; do
    [ -f "$dst/$f" ] || continue
    now="$(sha "$dst/$f")"
    [ "$now" = "$(sha "$src/$f")" ] && continue              # déjà à jour
    rec="$(recorded "$f")"
    if [ -z "$rec" ]; then echo "étranger  $f"                # pas à nous
    elif [ "$rec" != "$now" ] && [ "$force" = 0 ]; then echo "modifié   $f"   # à nous, retouché
    fi
done < "$tmp/files" > "$tmp/conflicts"
if [ -s "$tmp/conflicts" ]; then
    echo "apply.sh: ces fichiers de l'overlay seraient écrasés :" >&2
    sed 's/^/  /' "$tmp/conflicts" >&2
    echo "« étranger » : fichier qui n'appartient pas au thème (autre thème ou personnalisation) ; retirez-le ou fusionnez-le à la main." >&2
    echo "« modifié » : fichier du thème modifié dans l'overlay ; relancez avec --force pour l'écraser." >&2
    echo "Rien n'a été copié." >&2
    exit 1
fi

# 3. Copie, puis retrait des fichiers d'une version précédente du thème.
changed=0
: > "$tmp/newman"
while IFS= read -r f; do
    h="$(sha "$src/$f")"
    echo "$h  $f" >> "$tmp/newman"
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
        case "$f" in /*|*..*) die "chemin refusé dans le manifeste : $f" ;; esac
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
{
    echo "# Thème $THEME installé par deploy/themes/$THEME/apply.sh (empreinte  chemin)"
    cat "$tmp/newman"
} > "$tmp/manifest"
if [ -f "$manifest" ] && cmp -s "$tmp/manifest" "$manifest"; then :; else
    cp "$tmp/manifest" "$manifest"; changed=$((changed + 1))
fi
n="$(wc -l < "$tmp/files" | tr -d ' ')"
if [ "$changed" = 0 ]; then
    echo "Thème $LABEL déjà à jour dans $dst ($n fichiers)."
else
    echo "Thème $LABEL installé dans $dst ($n fichiers + manifeste)."
    echo "Ensuite : git add -A deploy/docker/overlay && git commit, puis deploy/docker/scripts/build.sh (voir README.md)."
fi
