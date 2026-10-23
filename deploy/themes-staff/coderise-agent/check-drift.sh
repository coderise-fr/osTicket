#!/bin/sh
# Thème « coderise-agent » (Coderise) : détecte les fichiers du cœur d'osTicket qui ont
# changé depuis la version sur laquelle le thème a été écrit.
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
# WATCH.sha256 : fichiers du cœur NON remplacés dont le thème dépend (balisage habillé
#                par la feuille de style, variables et contrats utilisés par les
#                gabarits et l'assistant) : à revoir s'ils changent.
# Les fins de ligne CR sont ignorées (copie Windows). Code de sortie : 0 aucune
# dérive, 1 dérive détectée, 2 erreur d'utilisation.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../../.." && pwd)"
overlay="$here/overlay"

# Core files the theme relies on without replacing them (provisional list, finalised with --write-base).
WATCH_FILES="
scp/index.php
scp/tickets.php
scp/login.php
scp/dashboard.php
scp/staff.inc.php
scp/admin.inc.php
scp/js/scp.js
scp/js/jb.overflow.menu.js
scp/js/ticket.js
scp/css/scp.css
scp/css/dropdown.css
css/thread.css
include/class.nav.php
include/class.queue.php
include/class.pagenate.php
include/ajax.tickets.php
include/staff/header.inc.php
include/staff/footer.inc.php
include/staff/login.header.php
include/staff/templates/queue-tickets.tmpl.php
include/staff/templates/queue-navigation.tmpl.php
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

# Prints the hash of a core file, or "absent".
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
        echo "# Thème coderise-agent : fichiers du cœur remplacés par overlay/, empreintes au commit $full"
        ( cd "$overlay" && find . -type f | sed 's|^\./||' | LC_ALL=C sort ) | while read -r f; do
            h="$(hash_of "$f")"
            [ "$h" = absent ] || echo "$h  $f"
        done
    } > "$here/BASE.sha256"
    {
        echo "# Thème coderise-agent : fichiers du cœur dont le thème dépend sans les remplacer, empreintes au commit $full"
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

check() { # $1 = file of hashes, $2 = label, $3 = advice
    if ! grep -qv "^#" "$1" 2>/dev/null; then
        echo "$2 : pas encore de référence (lancer ./check-drift.sh --write-base)."
        return 0
    fi
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
    "vérifier les pages concernées (et l'assistant d'ouverture), ajuster le thème si besoin, puis ./check-drift.sh --write-base"
exit $status
