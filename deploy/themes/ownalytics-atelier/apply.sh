#!/bin/sh
# Thème « Ownalytics Atelier » : installe le thème dans deploy/docker/overlay/ (puis committer et
# reconstruire l'image, voir README.md).
#
#   ./apply.sh            installe ou met à jour le thème
#   ./apply.sh --dry-run  affiche ce qui serait fait, sans rien écrire
#   ./apply.sh --force    écrase aussi les fichiers du thème modifiés à la main dans l'overlay
#
# Ne touche qu'aux fichiers de overlay/ du thème. Refuse si un autre thème est installé
# (n'importe quel autre installed.manifest, dossier de thème dans include/client/ ou
# assets/, ou fichier identique à celui d'un autre thème de deploy/themes/ ; aucune liste
# de noms codée en dur) ou si un fichier de l'overlay qui n'appartient pas au thème serait
# écrasé. Le manifeste
# des fichiers installés (avec leurs empreintes) est écrit dans
# deploy/docker/overlay/include/client/ownalytics-atelier/installed.manifest (non servi par Apache).
# Idempotent : relancer le script ne change rien si le thème est déjà à jour.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
src="$here/overlay"
dst="$repo/deploy/docker/overlay"
manifest_rel="include/client/ownalytics-atelier/installed.manifest"
manifest="$dst/$manifest_rel"

dry=0; force=0
for a in "$@"; do
    case "$a" in
        --dry-run) dry=1 ;;
        --force) force=1 ;;
        -h|--help) sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "apply.sh: option inconnue : $a" >&2; exit 2 ;;
    esac
done

[ -d "$dst" ] || { echo "apply.sh: dossier introuvable : $dst" >&2; exit 2; }

sha() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum < "$1" | cut -c1-64
    else shasum -a 256 < "$1" | cut -c1-64; fi
}
recorded() { # hash recorded in our manifest for a path, or nothing
    [ -f "$manifest" ] && awk -v p="$1" '$2 == p { print $1 }' "$manifest" || true
}

# 1. Another theme already installed? Generic checks, no list of theme names: several
#    alternative themes live side by side in deploy/themes/ and only one may be installed.
refuse_other() { # $1 = theme name, $2 = evidence
    echo "apply.sh: un autre thème (« $1 ») est installé dans l'overlay : $2" >&2
    if [ -x "$repo/deploy/themes/$1/remove.sh" ]; then
        echo "Retirez-le d'abord : deploy/themes/$1/remove.sh" >&2
    else
        echo "Retirez-le d'abord (remove.sh de ce thème, ou retrait à la main des fichiers ci-dessus)." >&2
    fi
    exit 1
}
# 1a. Any installation manifest of another theme, wherever it is in the overlay
#     (themes that keep a manifest put it in include/client/<theme>/installed.manifest).
others="$(cd "$dst" && find . -type f \( -name installed.manifest -o -name '*.installed.manifest' \) \
    | sed 's|^\./||' | grep -vxF "$manifest_rel" | LC_ALL=C sort || true)"
if [ -n "$others" ]; then
    first="$(echo "$others" | head -n 1)"
    refuse_other "$(basename "$(dirname "$first")")" "$(echo "$others" | tr '\n' ' ')"
fi
# 1b. A theme folder that is not ours: osTicket's own tree only has include/client/templates/
#     and assets/default/, assets/font/; any other folder there comes from a theme, including
#     themes that keep no manifest (e.g. their PHP helpers or their stylesheet).
for d in "$dst"/include/client/*/ "$dst"/assets/*/; do
    [ -d "$d" ] || continue
    name="$(basename "$d")"
    case "$name" in ownalytics-atelier|templates|default|font) continue ;; esac
    refuse_other "$name" "dossier ${d#"$dst"/}"
done
# 1c. Files identical to those of another theme of deploy/themes/ (whatever its name),
#     except files that our own manifest says we installed.
for other in "$repo"/deploy/themes/*/; do
    name="$(basename "$other")"
    [ "$name" = ownalytics-atelier ] && continue
    [ -d "$other/overlay" ] || continue
    found="$(cd "$other/overlay" && find . -type f | sed 's|^\./||' | while read -r f; do
        [ -f "$dst/$f" ] || continue
        h="$(sha "$dst/$f")"
        [ "$h" = "$(sha "$other/overlay/$f")" ] || continue
        [ "$(recorded "$f")" = "$h" ] && continue
        echo "$f"
    done | head -n 3)"
    [ -z "$found" ] || refuse_other "$name" "$(echo "$found" | tr '\n' ' ')"
done

# 2. Files of the overlay that the theme would overwrite without owning them.
conflicts="$(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort | while read -r f; do
    [ -f "$dst/$f" ] || continue
    now="$(sha "$dst/$f")"
    [ "$now" = "$(sha "$src/$f")" ] && continue          # already up to date
    rec="$(recorded "$f")"
    if [ -z "$rec" ]; then echo "étranger  $f"            # not ours
    elif [ "$rec" != "$now" ] && [ "$force" = 0 ]; then echo "modifié   $f"   # ours, edited by hand
    fi
done)"
if [ -n "$conflicts" ]; then
    echo "apply.sh: ces fichiers de l'overlay seraient écrasés :" >&2
    echo "$conflicts" | sed 's/^/  /' >&2
    echo "« étranger » : fichier qui n'appartient pas au thème (autre personnalisation) ; déplacez-le ou fusionnez-le à la main." >&2
    echo "« modifié » : fichier du thème modifié dans l'overlay ; relancez avec --force pour l'écraser." >&2
    exit 1
fi

# 3. Copy, then remove files of a previous version of the theme that no longer exist.
changed=0
tmpman="$(mktemp)"
trap 'rm -f "$tmpman"' EXIT INT TERM
(cd "$src" && find . -type f | sed 's|^\./||' | LC_ALL=C sort) > "$tmpman.list"
while read -r f; do
    h="$(sha "$src/$f")"
    echo "$h  $f" >> "$tmpman"
    if [ -f "$dst/$f" ] && [ "$(sha "$dst/$f")" = "$h" ]; then continue; fi
    changed=$((changed + 1))
    echo "copie     $f"
    if [ "$dry" = 0 ]; then
        mkdir -p "$(dirname "$dst/$f")"
        cp "$src/$f" "$dst/$f"
    fi
done < "$tmpman.list"
if [ -f "$manifest" ]; then
    while read -r h f; do
        grep -qxF "$f" "$tmpman.list" && continue
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
rm -f "$tmpman.list"

if [ "$dry" = 1 ]; then
    echo "Simulation : $changed fichier(s) à copier ou retirer, rien n'a été écrit."
    exit 0
fi
mkdir -p "$(dirname "$manifest")"
if [ -f "$manifest" ] && cmp -s "$tmpman" "$manifest"; then :; else cp "$tmpman" "$manifest"; changed=$((changed + 1)); fi
if [ "$changed" = 0 ]; then
    echo "Thème Ownalytics Atelier déjà à jour dans $dst."
else
    echo "Thème Ownalytics Atelier installé dans $dst ($(wc -l < "$manifest") fichiers)."
    echo "Ensuite : git add -A deploy/docker/overlay && git commit, puis deploy/docker/scripts/build.sh (voir README.md)."
fi
