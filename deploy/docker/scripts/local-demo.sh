#!/bin/sh
# Environnement LOCAL DE DÉMONSTRATION d'osTicket (Coderise) : image durcie de production,
# construite depuis le COMMIT (git archive, comme build.sh), avec un thème du portail,
# le plugin sla_first_response, Mailpit pour capter les e-mails et des données fictives.
# Jamais pour la production : tout est publié sur 127.0.0.1, en HTTP.
#
#   local-demo.sh up [--theme <slug>] [--rebuild]   construit, démarre, installe et peuple
#                                                   (la première fois), affiche les adresses
#   local-demo.sh theme <slug>      change de thème en gardant les données (reconstruit l'image)
#   local-demo.sh status            état des conteneurs, adresses, thème, commit
#   local-demo.sh logs [args]       journaux (arguments passés à `docker compose logs`)
#   local-demo.sh down              arrête la pile (les volumes, donc les données, sont gardés)
#   local-demo.sh reset [--yes]     supprime TOUT : conteneurs, volumes, images, dossier d'état
#
# Thèmes : atelier, poste-de-travail, sommet (défaut), parcours, ownalytics-atelier,
# ownalytics-ticket-de-caisse.
#
# Dossier d'état (OST_DEMO_DIR) : par défaut <dépôt principal>/.local-demo (ignoré par Git),
# même lancé depuis un worktree. Il contient state.env (ports, thème, commit), les fichiers
# Compose copiés, les secrets de la base et credentials.txt (identifiants, droits restreints).
# Projet Compose fixe « ostdemo » : aucun autre conteneur n'est touché.
# Aucun mot de passe n'est jamais affiché : seul le chemin de credentials.txt l'est.
set -eu

here="$(cd "$(dirname "$0")/.." && pwd)"          # deploy/docker
repo="$(cd "$here/../.." && pwd)"
demo_src="$here/local-demo"
PROJECT=ostdemo
IMAGE=ostdemo/osticket
TAG=demo
THEMES="atelier poste-de-travail sommet parcours ownalytics-atelier ownalytics-ticket-de-caisse"
DEFAULT_THEME=sommet
# Ports à ne jamais proposer (ddev, Mailpit de ddev, MySQL, port par défaut de la recette).
RESERVED_PORTS="80 443 3306 8025 8026 8080"
WEB_PORT_FIRST=8480
MAIL_PORT_FIRST=8425
STAFF_DOMAIN=coderise-demo.test

say() { printf 'local-demo: %s\n' "$*"; }
die() { printf 'local-demo: %s\n' "$*" >&2; exit 1; }

usage() { sed -n '2,23p' "$0" | sed 's/^# \{0,1\}//'; }

# Chemin lisible par docker.exe sous Windows (Git Bash), inchangé ailleurs.
winpath() {
    if command -v cygpath >/dev/null 2>&1; then cygpath -m "$1"; else printf '%s\n' "$1"; fi
}

# <dépôt principal>/.local-demo, y compris depuis un worktree (même dossier .git commun).
default_state_dir() {
    common="$(git -C "$repo" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
    if [ -n "$common" ] && [ -d "$common" ]; then
        printf '%s/.local-demo\n' "$(cd "$common/.." && pwd)"
    else
        printf '%s/.local-demo\n' "$repo"
    fi
}

STATE="${OST_DEMO_DIR:-$(default_state_dir)}"
case "$STATE" in /*|[A-Za-z]:*) ;; *) STATE="$(pwd)/$STATE" ;; esac
MARKER="$STATE/.ostdemo-state"
CREDS="$STATE/credentials.txt"
ENVF="$STATE/state.env"

need() { command -v "$1" >/dev/null 2>&1 || die "commande introuvable : $1"; }

# --- Fichier d'état (state.env : NOM=valeur, sans secret) --------------------

state_get() { # clé
    [ -f "$ENVF" ] || return 0
    awk -F= -v k="$1" '$1 == k { sub(/^[^=]*=/, ""); print; exit }' "$ENVF"
}
state_set() { # clé valeur
    tmp="$ENVF.tmp"
    if [ -f "$ENVF" ]; then awk -F= -v k="$1" '$1 != k' "$ENVF" > "$tmp"; else : > "$tmp"; fi
    printf '%s=%s\n' "$1" "$2" >> "$tmp"
    mv "$tmp" "$ENVF"
}

# --- Docker Compose (toujours depuis les copies du dossier d'état) ------------

dc() {
    docker compose --progress quiet -p "$PROJECT" --project-directory "$(winpath "$STATE")" \
        --env-file "$(winpath "$ENVF")" \
        -f "$(winpath "$STATE/compose.yaml")" -f "$(winpath "$STATE/compose.demo.yaml")" "$@"
}
dc_install() {
    docker compose --progress quiet -p "$PROJECT" --project-directory "$(winpath "$STATE")" \
        --env-file "$(winpath "$ENVF")" \
        -f "$(winpath "$STATE/compose.yaml")" -f "$(winpath "$STATE/compose.demo.yaml")" \
        -f "$(winpath "$STATE/compose.install.yaml")" "$@"
}

require_state() {
    [ -f "$MARKER" ] && [ -f "$ENVF" ] && [ -f "$STATE/compose.yaml" ] \
        || die "aucun environnement de démonstration dans $STATE (lancer : $0 up)"
}

# --- Ports -------------------------------------------------------------------

port_busy() { # port
    case " $RESERVED_PORTS " in *" $1 "*) return 0 ;; esac
    if docker ps --format '{{.Ports}}' | tr ',' '\n' | grep -q ":$1->"; then return 0; fi
    rc=0
    # (sous Windows, un refus de connexion sur la boucle locale prend environ 2 s)
    curl -s -o /dev/null --connect-timeout 5 --max-time 6 "http://127.0.0.1:$1/" || rc=$?
    [ "$rc" -ne 7 ]          # 7 = connexion refusée : rien n'écoute, port libre
}
pick_port() { # premier essai, port à éviter
    p="$1"
    while [ "$p" -lt $(($1 + 200)) ]; do
        if [ "$p" != "$2" ] && ! port_busy "$p"; then echo "$p"; return 0; fi
        p=$((p + 1))
    done
    die "aucun port libre trouvé à partir de $1"
}

# --- Initialisation du dossier d'état -------------------------------------------

init_state() {
    umask 077
    mkdir -p "$STATE"
    [ -f "$MARKER" ] || printf 'Dossier d état de deploy/docker/scripts/local-demo.sh (projet %s).\n' "$PROJECT" > "$MARKER"
    # Jamais versionné, quelle que soit la branche extraite (identifiants, secrets).
    [ -f "$STATE/.gitignore" ] || printf '*\n' > "$STATE/.gitignore"
    if [ -z "$(state_get OST_PORT)" ]; then
        web="$(pick_port "$WEB_PORT_FIRST" 0)"
        mail="$(pick_port "$MAIL_PORT_FIRST" "$web")"
        state_set OST_PROJECT "$PROJECT"
        state_set OST_IMAGE "$IMAGE"
        state_set OST_VERSION "$TAG"
        state_set OST_BIND 127.0.0.1
        state_set OST_PORT "$web"
        state_set OST_DEMO_MAIL_PORT "$mail"
        state_set OST_SECRETS_DIR ./secrets
        # Jamais construit par Compose (le script construit l'image lui-même).
        state_set OST_CONTEXT .
        state_set OSTICKET_COOKIE_SECURE 0
        state_set OSTICKET_MAX_WORKERS 8
        say "ports choisis : portail $web, Mailpit $mail (fixés dans $ENVF)"
    fi
    # Copies à jour des fichiers Compose : la pile ne dépend plus de l'arbre de travail.
    cp "$here/compose.yaml" "$here/compose.install.yaml" "$STATE/"
    cp "$demo_src/compose.demo.yaml" "$STATE/"
    sh "$here/scripts/gen-secrets.sh" "$STATE/secrets" >/dev/null
}

# --- Construction de l'image (depuis le commit) ---------------------------------

valid_theme() {
    case " $THEMES " in *" $1 "*) return 0 ;; esac
    die "thème inconnu : $1 (choix : $THEMES)"
}

build_images() { # thème, "install" pour construire aussi la cible d'installation
    theme="$1"
    need git; need tar
    if [ -n "$(git -C "$repo" status --porcelain -- . ':!CLAUDE.md' ':!.local-demo' 2>/dev/null)" ]; then
        say "attention : les modifications non committées ne sont PAS dans l'image (construite depuis HEAD)."
    fi
    rev="$(git -C "$repo" rev-parse HEAD)"
    ctx="$(mktemp -d "${TMPDIR:-/tmp}/ostdemo-build.XXXXXX")"
    trap 'rm -rf "$ctx"' EXIT INT TERM
    git -C "$repo" -c core.autocrlf=false archive --format=tar HEAD | tar -x -C "$ctx"
    ov="$ctx/deploy/docker/overlay"
    # Thème : retrait du thème committé dans l'overlay (copie exportée), puis le thème voulu.
    for m in "$ov"/include/client/*/installed.manifest; do
        [ -f "$m" ] || continue
        cur="$(basename "$(dirname "$m")")"
        [ "$cur" = "$theme" ] && continue
        [ -f "$ctx/deploy/themes/$cur/remove.sh" ] || die "thème installé inconnu dans l'overlay : $cur"
        sh "$ctx/deploy/themes/$cur/remove.sh" >/dev/null || die "retrait du thème $cur impossible"
    done
    [ -f "$ctx/deploy/themes/$theme/apply.sh" ] || die "deploy/themes/$theme/apply.sh absent du commit $rev"
    sh "$ctx/deploy/themes/$theme/apply.sh" >/dev/null || die "installation du thème $theme impossible"
    # Plugin SLA : dans l'overlay de la copie seulement (jamais committé dans deploy/docker/overlay/).
    mkdir -p "$ov/include/plugins"
    cp -R "$ctx/contrib/sla-first-response/plugin/sla_first_response" "$ov/include/plugins/"
    wctx="$(winpath "$ctx")"
    say "construction de l'image $IMAGE:$TAG (commit ${rev%"${rev#????????}"}, thème $theme)..."
    for target in runtime ${2:-}; do
        suffix=""; [ "$target" = install ] && suffix="-install"
        docker build -q -f "$wctx/deploy/docker/Dockerfile" --target "$target" \
            --build-arg OST_VERSION="$TAG" --build-arg OST_REVISION="$rev" \
            --label "fr.coderise.ostdemo.theme=$theme" \
            -t "$IMAGE:$TAG$suffix" "$wctx" >/dev/null || die "échec de la construction ($target)"
    done
    rm -rf "$ctx"; trap - EXIT INT TERM
    state_set OST_REVISION "$rev"
    state_set OST_DEMO_THEME "$theme"
}

# --- Identifiants (générés, écrits uniquement dans credentials.txt) ---------------

gen_pw() { # 4 groupes de 5 caractères, avec majuscule, minuscule et chiffre
    while :; do
        s="$(head -c 96 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-20)"
        case "$s" in *[A-Z]*) ;; *) continue ;; esac
        case "$s" in *[a-z]*) ;; *) continue ;; esac
        case "$s" in *[0-9]*) ;; *) continue ;; esac
        [ "${#s}" -eq 20 ] || continue
        printf '%s-%s-%s-%s\n' "$(echo "$s" | cut -c1-5)" "$(echo "$s" | cut -c6-10)" \
            "$(echo "$s" | cut -c11-15)" "$(echo "$s" | cut -c16-20)"
        return 0
    done
}
cred_get() { # section clé
    [ -f "$CREDS" ] || return 0
    awk -v s="[$1]" -v k="$2" '
        /^\[/ { in_s = ($0 == s); next }
        in_s && index($0, k " = ") == 1 { print substr($0, length(k) + 4); exit }' "$CREDS"
}
restrict_file() { # droits restreints (et ACL réservée à l'utilisateur sous Windows)
    chmod 600 "$1" 2>/dev/null || true
    if command -v icacls >/dev/null 2>&1 && command -v cygpath >/dev/null 2>&1 && [ -n "${USERNAME:-}" ]; then
        MSYS2_ARG_CONV_EXCL='*' icacls "$(cygpath -w "$1")" /inheritance:r /grant:r "${USERNAME}:F" >/dev/null 2>&1 || true
    fi
}
write_credentials() {
    web="$(state_get OST_PORT)"; mail="$(state_get OST_DEMO_MAIL_PORT)"
    for k in admin agent lea hugo nina marc; do
        eval "pw_$k=\"\$(cred_get $k mot_de_passe)\""
        eval "[ -n \"\$pw_$k\" ] || pw_$k=\"\$(gen_pw)\""
    done
    guest_ticket="$(state_get OST_DEMO_GUEST_TICKET)"
    umask 077
    {
        echo "# Environnement local de DÉMONSTRATION osTicket : identifiants générés, données fictives."
        echo "# Ne pas réutiliser ailleurs, ne pas committer. Fichier écrit par deploy/docker/scripts/local-demo.sh."
        echo
        echo "[adresses]"
        echo "portail_client = http://127.0.0.1:$web/"
        echo "panneau_agent = http://127.0.0.1:$web/scp/"
        echo "administration = http://127.0.0.1:$web/scp/settings.php"
        echo "mailpit = http://127.0.0.1:$mail/"
        echo
        echo "[admin]"
        echo "role = Administrateur (panneau agent + administration)"
        echo "identifiant = camille.durand"
        echo "e_mail = camille.durand@$STAFF_DOMAIN"
        echo "mot_de_passe = $pw_admin"
        echo
        echo "[agent]"
        echo "role = Agent, département Support (panneau agent)"
        echo "identifiant = theo.martin"
        echo "e_mail = theo.martin@$STAFF_DOMAIN"
        echo "mot_de_passe = $pw_agent"
        echo
        echo "[lea]"
        echo "role = Cliente, organisation Priority (portail client)"
        echo "identifiant = lea.moreau@lumen-studio.test"
        echo "mot_de_passe = $pw_lea"
        echo
        echo "[hugo]"
        echo "role = Client, organisation Acme, contrat Standard (portail client)"
        echo "identifiant = hugo.bernard@acme.test"
        echo "mot_de_passe = $pw_hugo"
        echo
        echo "[nina]"
        echo "role = Cliente sans organisation, contrat Standard (portail client)"
        echo "identifiant = nina.petit@mail-perso.test"
        echo "mot_de_passe = $pw_nina"
        echo
        echo "[marc]"
        echo "role = Client, organisation Acme, collaborateur du ticket partagé de Hugo (portail client)"
        echo "identifiant = marc.lefevre@acme.test"
        echo "mot_de_passe = $pw_marc"
        echo
        echo "[invite]"
        echo "role = Invitée sans compte, organisation Priority : « Vérifier l'état d'un ticket » (e-mail + numéro)"
        echo "e_mail = gina.roux@lumen-studio.test"
        echo "numero_ticket = ${guest_ticket:-(créé au premier peuplement)}"
    } > "$CREDS.tmp"
    mv "$CREDS.tmp" "$CREDS"
    restrict_file "$CREDS"
}

# --- Installation (assistant HTTP) et peuplement ------------------------------------

wait_http() { # url, secondes
    i=0
    while [ "$i" -lt "$2" ]; do
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$1" || true)"
        case "$code" in 2??|3??) return 0 ;; esac
        sleep 2; i=$((i + 2))
    done
    die "pas de réponse de $1 après $2 s"
}

# Requête de l'assistant : options curl lues sur l'entrée standard (aucun secret dans
# la ligne de commande ni sur le disque, hors credentials.txt et secrets/).
setup_post() { # fichier de sortie ; les lignes data-urlencode sont lues sur stdin
    { printf 'url = "%s"\ncookie = "%s"\ncookie-jar = "%s"\n' "$SETUP_URL" "$JAR" "$JAR"; cat; } \
        | curl -s -o "$1" -K -
}

install_osticket() {
    web="$(state_get OST_PORT)"
    SETUP_URL="http://127.0.0.1:$web/setup/install.php"
    tmpd="$(mktemp -d "${TMPDIR:-/tmp}/ostdemo-install.XXXXXX")"
    JAR="$(winpath "$tmpd/cookies")"
    say "installation d'osTicket par l'assistant (HTTP, image install)..."
    dc_install up -d --wait db >/dev/null
    dc_install up -d --no-deps web >/dev/null
    wait_http "$SETUP_URL" 120
    curl -s -o /dev/null -c "$JAR" -b "$JAR" "$SETUP_URL"
    printf 'data-urlencode = "s=prereq"\n' | setup_post "$tmpd/1.html"
    printf 'data-urlencode = "s=config"\n' | setup_post "$tmpd/2.html"
    dbpw="$(cat "$STATE/secrets/db_password.txt")"
    pw="$(cred_get admin mot_de_passe)"
    {
        echo 'data-urlencode = "s=install"'
        echo 'data-urlencode = "name=Support Coderise (démonstration)"'
        echo "data-urlencode = \"email=support@$STAFF_DOMAIN\""
        echo 'data-urlencode = "lang_id=en_US"'
        echo 'data-urlencode = "fname=Camille"'
        echo 'data-urlencode = "lname=Durand"'
        echo "data-urlencode = \"admin_email=camille.durand@$STAFF_DOMAIN\""
        echo 'data-urlencode = "username=camille.durand"'
        echo "data-urlencode = \"passwd=$pw\""
        echo "data-urlencode = \"passwd2=$pw\""
        echo 'data-urlencode = "prefix=ost_"'
        echo 'data-urlencode = "dbhost=db"'
        echo 'data-urlencode = "dbname=osticket"'
        echo 'data-urlencode = "dbuser=osticket"'
        echo "data-urlencode = \"dbpass=$dbpw\""
        echo 'data-urlencode = "timezone=Europe/Paris"'
    } | setup_post "$tmpd/3.html"
    unset pw dbpw
    if ! grep -q 'Congratulations' "$tmpd/3.html"; then
        grep -o 'class="error"[^<]*<[^>]*>[^<]*' "$tmpd/3.html" | head -5 >&2 || true
        rm -rf "$tmpd"
        die "l'assistant d'installation a échoué (voir : $0 logs web)"
    fi
    rm -rf "$tmpd"
    # Fin de l'assistant : retour à l'image de production (configuration en lecture seule).
    dc_install stop web >/dev/null 2>&1
    dc_install rm -f web >/dev/null 2>&1
    state_set OST_DEMO_INSTALLED 1
    say "osTicket installé."
}

# Exécute deploy/docker/local-demo/seed.php dans un conteneur ponctuel de l'image (php lit
# le script sur son entrée standard, précédé d'un prélude qui porte les mots de passe).
run_seed() { # phase
    web="$(state_get OST_PORT)"
    out="$STATE/seed-$1.log"
    {
        printf '<?php\n$DEMO = array(\n'
        printf "    'phase' => '%s',\n" "$1"
        printf "    'base_url' => 'http://127.0.0.1:%s/',\n" "$web"
        printf "    'pw' => array(\n"
        for k in agent lea hugo nina marc; do
            printf "        '%s' => '%s',\n" "$k" "$(cred_get "$k" mot_de_passe)"
        done
        printf '    ),\n);\n?>\n'
        cat "$demo_src/seed.php"
    } | dc run --rm --no-deps -T --entrypoint php web -d memory_limit=512M > "$out" 2>&1 || {
        sed -e 's/^/  /' "$out" >&2
        die "peuplement ($1) en échec : voir $out"
    }
    grep -v '^OUT ' "$out" | sed -e 's/^/  /'
    grep '^OUT ' "$out" | sed 's/^OUT //' > "$STATE/seed-$1.out" || true
}

seed_demo() {
    say "peuplement : configuration (réglages, Mailpit, SLA, filtre, plugin, comptes, contenus)..."
    run_seed config
    say "peuplement : tickets (le plugin bascule le plan à la première réponse)..."
    run_seed tickets
    g="$(awk -F= '$1 == "guest_ticket" { print $2 }' "$STATE/seed-tickets.out")"
    [ -z "$g" ] || state_set OST_DEMO_GUEST_TICKET "$g"
    p="$(awk -F= '$1 == "lea_priority_ticket" { print $2 }' "$STATE/seed-tickets.out")"
    [ -z "$p" ] || state_set OST_DEMO_LEA_PRIORITY_TICKET "$p"
    state_set OST_DEMO_SEEDED 1
    write_credentials
}

# --- Affichage -------------------------------------------------------------------

show_urls() {
    web="$(state_get OST_PORT)"; mail="$(state_get OST_DEMO_MAIL_PORT)"
    echo
    echo "  Portail client       http://127.0.0.1:$web/"
    echo "  Panneau agent        http://127.0.0.1:$web/scp/"
    echo "  Administration       http://127.0.0.1:$web/scp/settings.php"
    echo "  Mailpit (e-mails)    http://127.0.0.1:$mail/"
    echo "  Thème                $(state_get OST_DEMO_THEME)   (commit $(state_get OST_REVISION | cut -c1-8))"
    echo "  Identifiants         $CREDS"
    echo
}

image_id() { docker image inspect --format '{{.Id}}' "$IMAGE:$TAG" 2>/dev/null || true; }
drop_old_image() { # identifiant de l'ancienne image : supprimée si plus aucun tag ni conteneur
    [ -n "$1" ] && [ "$1" != "$(image_id)" ] || return 0
    docker image rm "$1" >/dev/null 2>&1 || true
}

# --- Commandes -------------------------------------------------------------------

cmd_up() {
    theme=""; rebuild=0
    while [ $# -gt 0 ]; do
        case "$1" in
            --theme) [ $# -ge 2 ] || die "--theme demande un nom"; theme="$2"; shift 2 ;;
            --theme=*) theme="${1#--theme=}"; shift ;;
            --rebuild) rebuild=1; shift ;;
            *) die "option inconnue pour up : $1" ;;
        esac
    done
    need docker; need curl; need git
    init_state
    [ -n "$theme" ] || theme="$(state_get OST_DEMO_THEME)"
    [ -n "$theme" ] || theme="$DEFAULT_THEME"
    valid_theme "$theme"
    [ -f "$CREDS" ] || write_credentials
    old="$(image_id)"
    installed="$(state_get OST_DEMO_INSTALLED)"
    if [ "$rebuild" = 1 ] || [ -z "$old" ] || [ "$theme" != "$(state_get OST_DEMO_THEME)" ] \
            || [ "$(git -C "$repo" rev-parse HEAD)" != "$(state_get OST_REVISION)" ] \
            || { [ -z "$installed" ] && ! docker image inspect "$IMAGE:$TAG-install" >/dev/null 2>&1; }; then
        if [ -z "$installed" ]; then build_images "$theme" install; else build_images "$theme"; fi
    fi
    if [ -z "$installed" ]; then
        install_osticket
    fi
    say "démarrage de la pile (web, cron, base, Mailpit)..."
    dc up -d --wait --wait-timeout 300 --remove-orphans >/dev/null
    drop_old_image "$old"
    if [ -z "$(state_get OST_DEMO_SEEDED)" ]; then
        seed_demo
    fi
    say "environnement prêt."
    show_urls
}

cmd_theme() {
    [ $# -eq 1 ] || die "usage : $0 theme <slug>   ($THEMES)"
    valid_theme "$1"
    require_state
    [ -n "$(state_get OST_DEMO_INSTALLED)" ] || die "pas encore installé : lancer d'abord $0 up"
    init_state
    old="$(image_id)"
    build_images "$1"
    say "redémarrage de web et cron avec le thème $1 (données conservées)..."
    dc up -d --wait --wait-timeout 300 web cron >/dev/null
    drop_old_image "$old"
    show_urls
}

cmd_status() {
    if [ ! -f "$MARKER" ]; then say "aucun environnement de démonstration dans $STATE"; return 0; fi
    require_state
    dc ps -a
    show_urls
}

cmd_logs() { require_state; dc logs "$@"; }

cmd_down() {
    require_state
    dc down
    say "pile arrêtée ; données conservées (volumes ${PROJECT}_ost-db, ${PROJECT}_ost-config, ${PROJECT}_mail-data). Relancer : $0 up"
}

cmd_reset() {
    yes=0
    [ "${1:-}" = "--yes" ] && yes=1
    if [ ! -f "$MARKER" ]; then
        say "aucun environnement dans $STATE (marqueur absent) : rien à supprimer."
        return 0
    fi
    if [ "$yes" != 1 ]; then
        [ -t 0 ] || die "confirmation requise : relancer avec --yes"
        printf 'Supprimer DÉFINITIVEMENT la démo (conteneurs, volumes, images %s, dossier %s) ? Taper « oui » : ' "$IMAGE" "$STATE"
        read -r answer
        [ "$answer" = oui ] || die "annulé."
    fi
    if [ -f "$STATE/compose.yaml" ] && [ -f "$ENVF" ]; then
        dc_install down -v --remove-orphans || true
    fi
    docker image rm "$IMAGE:$TAG" "$IMAGE:$TAG-install" >/dev/null 2>&1 || true
    case "$(basename "$STATE")" in
        ''|/|.|..) die "dossier d'état suspect : $STATE" ;;
    esac
    rm -rf "$STATE"
    say "environnement supprimé."
}

[ $# -gt 0 ] || { usage; exit 2; }
cmd="$1"; shift
case "$cmd" in
    up) cmd_up "$@" ;;
    theme) cmd_theme "$@" ;;
    status) cmd_status "$@" ;;
    logs) cmd_logs "$@" ;;
    down) cmd_down "$@" ;;
    reset) cmd_reset "$@" ;;
    -h|--help|help) usage ;;
    *) usage >&2; exit 2 ;;
esac
