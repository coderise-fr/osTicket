#!/bin/sh
# Thème « Sommet » : détecte les fichiers du cœur d'osTicket qui ont changé depuis
# la version sur laquelle le thème a été écrit.
#
#   ./check-drift.sh                  compare au commit HEAD du dépôt (défaut)
#   ./check-drift.sh --rev v1.18.5    compare à un autre commit, tag ou branche
#   ./check-drift.sh /chemin/osticket compare à un arbre osTicket sur disque
#                                     (archive de version extraite, copie d'une image…)
#   ./check-drift.sh --write-base [REV]
#                                     réécrit BASE.sha256 et WATCH.sha256 depuis REV
#                                     (HEAD par défaut), après avoir rebasé les gabarits
#
# BASE.sha256  : fichiers du cœur REMPLACÉS par le thème (à rebaser s'ils changent).
# WATCH.sha256 : fichiers du cœur NON remplacés dont le thème dépend : balisage habillé
#                par la feuille de style, premier <h1> déplacé dans le bandeau, liens de
#                navigation (à revoir s'ils changent).
# Les fins de ligne CR sont ignorées (copie Windows). Code de sortie : 0 aucune
# dérive, 1 dérive détectée, 2 erreur d'utilisation.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
overlay="$here/overlay"

WATCH_FILES="
offline.php
open.php
css/thread.css
css/redactor.css
css/filedrop.css
css/osticket.css
include/class.forms.php
include/class.nav.php
include/client/accesslink.inc.php
include/client/edit.inc.php
include/client/faq-category.inc.php
include/client/faq.inc.php
include/client/kb-categories.inc.php
include/client/kb-search.inc.php
include/client/knowledgebase.inc.php
include/client/login.inc.php
include/client/open.inc.php
include/client/profile.inc.php
include/client/pwreset.login.php
include/client/pwreset.request.php
include/client/pwreset.sent.php
include/client/register.confirm.inc.php
include/client/register.confirmed.inc.php
include/client/register.inc.php
include/client/tickets.inc.php
include/client/templates/dynamic-form.tmpl.php
include/client/templates/thread-entries.tmpl.php
js/osticket.js
"

sha() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum | cut -c1-64
    else shasum -a 256 | cut -c1-64; fi
}

mode=git
rev=HEAD
dir=
write=0
case "${1:-}" in
    -h|--help) sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    --rev) [ $# -ge 2 ] || { echo "check-drift: --rev demande un commit" >&2; exit 2; }; rev="$2" ;;
    --write-base) write=1; rev="${2:-HEAD}" ;;
    "") ;;
    *) mode=dir; dir="$(cd "$1" 2>/dev/null && pwd)" || { echo "check-drift: dossier introuvable : $1" >&2; exit 2; } ;;
esac

# Affiche l'empreinte d'un fichier du cœur, ou « absent ».
hash_of() {
    if [ "$mode" = git ]; then
        if git -C "$repo" cat-file -e "$rev:$1" 2>/dev/null; then
            git -C "$repo" -c core.autocrlf=false show "$rev:$1" | tr -d '\r' | sha
        else
            echo absent
        fi
    else
        if [ -f "$dir/$1" ]; then tr -d '\r' < "$dir/$1" | sha; else echo absent; fi
    fi
}

if [ "$write" = 1 ]; then
    full="$(git -C "$repo" rev-parse "$rev")"
    {
        echo "# Thème Sommet : fichiers du cœur remplacés par overlay/, empreintes au commit $full"
        ( cd "$overlay" && find . -type f | sed 's|^\./||' | LC_ALL=C sort ) | while read -r f; do
            h="$(hash_of "$f")"
            [ "$h" = absent ] || echo "$h  $f"
        done
    } > "$here/BASE.sha256"
    {
        echo "# Thème Sommet : fichiers du cœur dont le thème dépend sans les remplacer, empreintes au commit $full"
        for f in $WATCH_FILES; do
            h="$(hash_of "$f")"
            [ "$h" = absent ] || echo "$h  $f"
        done
    } > "$here/WATCH.sha256"
    echo "BASE.sha256 et WATCH.sha256 écrits depuis $full"
    exit 0
fi

target="$rev"; [ "$mode" = dir ] && target="$dir"
base_rev="$(sed -n '1s/.* au commit \([0-9a-f]*\).*/\1/p' "$here/BASE.sha256")"
status=0

check() { # $1 = fichier d'empreintes, $2 = libellé, $3 = conseil
    changed=""
    while read -r h f; do
        case "$h" in ''|'#'*) continue ;; esac
        now="$(hash_of "$f")"
        if [ "$now" = absent ]; then
            changed="$changed
  SUPPRIMÉ  $f"
        elif [ "$now" != "$h" ]; then
            changed="$changed
  MODIFIÉ   $f"
        fi
    done < "$1"
    if [ -n "$changed" ]; then
        status=1
        echo "$2 :$changed"
        echo "  -> $3"
    else
        echo "$2 : aucun changement."
    fi
}

echo "Référence du thème : ${base_rev:-inconnue} ; comparé à : $target"
if [ "$mode" = git ]; then
    how="git diff $base_rev $rev -- <fichier>"
else
    how="diff entre le fichier de $base_rev et celui de $dir"
fi
check "$here/BASE.sha256" "Fichiers remplacés par le thème" \
    "reporter les changements du cœur dans overlay/ ($how), puis ./check-drift.sh --write-base"
check "$here/WATCH.sha256" "Fichiers dont le thème dépend" \
    "vérifier les pages concernées (titre déplacé dans le bandeau, habillage), ajuster sommet.css si besoin, puis ./check-drift.sh --write-base"
exit $status
