#!/bin/sh
# End-to-end test of backup.sh + restore.sh on a THROW-AWAY stack (random project name,
# random loopback port, secrets in a temporary folder). It touches nothing else.
#
#   ./scripts/test-restore.sh            (needs Docker, curl, sha256sum; HEAD must be committed)
#
# Steps: build the images from HEAD, install osTicket through the setup wizard, create an API
# key, a user, a ticket with a 300 kB attachment, back up, DESTROY the stack (down -v), restore
# it on a blank stack, then compare: database fingerprint, ost-config.php checksum, attachment
# checksum (downloaded through its signed link), agent login, ticket creation through the API.
# Everything it created (containers, volumes, networks, images) is removed at the end,
# unless KEEP=1.
#
# Not covered: mailbox credentials (see README.md, "Messagerie"), plugins, upgrades.
set -eu

here="$(cd "$(dirname "$0")/.." && pwd)"
rnd() { od -An -N"$1" -tx1 /dev/urandom | tr -d ' \n'; }
suffix="$(rnd 4)"
port="${OST_TEST_PORT:-$((20000 + $(printf '%d' "0x$(rnd 2)") % 9000))}"
work="$(mktemp -d)"
if command -v cygpath >/dev/null 2>&1; then win() { cygpath -m "$1"; }; else win() { printf '%s' "$1"; }; fi

# Isolated Compose project: the shell environment wins over any .env file.
export OST_PROJECT="osttest$suffix" OST_VERSION="test-$suffix" OST_BIND=127.0.0.1 OST_PORT="$port" \
       OSTICKET_COOKIE_SECURE=0 OST_SECRETS_DIR="$(win "$work/secrets")"
base="http://127.0.0.1:$port"
admin_user=sysadmin
admin_pass='Zx9!kQ7vLm2#pT'
jar="$work/jar"

dc() { (cd "$here" && docker compose "$@"); }
say() { printf '\n== %s\n' "$*"; }
fail() { echo "FAIL: $*" >&2; exit 1; }

cleanup() {
    if [ "${KEEP:-}" = 1 ]; then
        echo "kept: project $OST_PROJECT, port $port, files in $work"
        return
    fi
    dc down -v >/dev/null 2>&1 || true
    docker rmi "coderise/osticket:$OST_VERSION" "coderise/osticket:$OST_VERSION-install" >/dev/null 2>&1 || true
    rm -rf "$work"
}
trap cleanup EXIT

token() { grep -o 'name="__CSRFToken__" value="[^"]*"' "$1" | head -n 1 | sed 's/.*value="//;s/"$//'; }
# MSYS_NO_PATHCONV: Git Bash on Windows would rewrite "/etc/osticket" into a Windows path.
cfgsum() { (export MSYS_NO_PATHCONV=1; dc run --rm --no-deps -T --entrypoint sha256sum web /etc/osticket/ost-config.php 2>/dev/null | cut -d' ' -f1); }
sql() { dc exec -T db sh -c 'mariadb -N -B -uroot -p"$(cat /run/secrets/db_root_password)" osticket' ; }

fingerprint() {
    sql <<'SQL'
select count(*), group_concat(number order by ticket_id) from ost_ticket;
select count(*), group_concat(name order by id) from ost_user;
select count(*), md5(group_concat(body order by id)) from ost_thread_entry;
select count(*), sum(size) from ost_file;
select count(*), sum(length(filedata)), md5(group_concat(md5(filedata) order by file_id, chunk_id)) from ost_file_chunk;
select count(*), group_concat(apikey) from ost_api_key;
select value from ost_config where `key`='schema_signature';
select md5(group_concat(concat(namespace,'/',`key`,'=',value) order by namespace,`key`)) from ost_config where `key` <> 'last_cron_run' and namespace <> 'mysqlsearch';
SQL
}

staff_login() {
    rm -f "$jar"
    curl -s -c "$jar" -b "$jar" "$base/scp/login.php" -o "$work/p.html"
    curl -s -c "$jar" -b "$jar" "$base/scp/login.php" -o /dev/null -D "$work/h.txt" \
        --data-urlencode "__CSRFToken__=$(token "$work/p.html")" -d do=scplogin \
        --data-urlencode "userid=$admin_user" --data-urlencode "passwd=$admin_pass"
    grep -qi '^location: index.php' "$work/h.txt" || fail "agent login"
}

wait_http() {
    n=0
    until [ "$(curl -s -o /dev/null -w '%{http_code}' "$1")" = 200 ]; do
        n=$((n + 1)); [ $n -gt 60 ] && fail "no answer from $1"; sleep 2
    done
}

attachment_sha() { # sha256 of the first attachment of ticket 2, downloaded through its signed link
    curl -s -c "$jar" -b "$jar" "$base/scp/tickets.php?id=2" -o "$work/t.html"
    link="$(grep -o -E 'href="[^"]*/file\.php\?[^"]*"' "$work/t.html" | head -n 1 | sed 's/^href="//;s/"$//;s/&amp;/\&/g')"
    [ -n "$link" ] || fail "no attachment link on ticket 2"
    curl -s -c "$jar" -b "$jar" "$base$link" | sha256sum | cut -d' ' -f1
}

api_ticket() { # subject
    b64="$(base64 < "$work/attach.pdf" | tr -d '\n')"
    printf '{"email":"client@example.org","name":"Client Test","subject":"%s","message":"data:text/plain;charset=utf-8,Bonjour, ticket de test.","attachments":[{"proof.pdf":"data:application/pdf;base64,%s"}]}' "$1" "$b64" > "$work/ticket.json"
    curl -s -H "X-API-Key: $apikey" -H 'Content-Type: application/json' --data-binary "@$work/ticket.json" "$base/api/tickets.json"
}

mkdir -p "$work"
"$here/scripts/gen-secrets.sh" "$work/secrets" >/dev/null
head -c 300000 /dev/urandom > "$work/attach.pdf"
want_att="$(sha256sum "$work/attach.pdf" | cut -d' ' -f1)"

say "building images from the committed HEAD (tag $OST_VERSION)"
"$here/scripts/build.sh" build >/dev/null
"$here/scripts/build.sh" -f compose.yaml -f compose.install.yaml build web >/dev/null

say "installing osTicket through the setup wizard"
dc -f compose.yaml -f compose.install.yaml up -d --wait db web >/dev/null
wait_http "$base/setup/install.php"
installer="$base/setup/install.php"; rm -f "$jar"
curl -s -c "$jar" -b "$jar" "$installer" -o /dev/null
curl -s -c "$jar" -b "$jar" -d s=prereq "$installer" -o /dev/null
curl -s -c "$jar" -b "$jar" -d s=config "$installer" -o /dev/null
curl -s -c "$jar" -b "$jar" "$installer" -o "$work/inst.html" \
    -d s=install -d name=Helpdesk -d email=helpdesk@example.org -d lang_id=en_US \
    -d fname=Ad -d lname=Min -d admin_email=admin@example.org -d "username=$admin_user" \
    --data-urlencode "passwd=$admin_pass" --data-urlencode "passwd2=$admin_pass" -d prefix=ost_ \
    -d dbhost=db -d dbname=osticket -d dbuser=osticket --data-urlencode "dbpass=$(cat "$work/secrets/db_password.txt")" -d timezone=UTC
grep -q Congratulations "$work/inst.html" || fail "the setup wizard did not complete"
dc -f compose.yaml -f compose.install.yaml rm -sf web >/dev/null 2>&1
dc up -d --wait >/dev/null
wait_http "$base/login.php"

say "creating an API key, a user and a ticket with an attachment"
client_ip="$(docker logs "$(dc ps -q web)" 2>&1 | grep 'GET /login.php' | tail -n 1 | cut -d' ' -f1)"
[ -n "$client_ip" ] || fail "cannot find the client address seen by the web container"
staff_login
curl -s -c "$jar" -b "$jar" "$base/scp/apikeys.php?a=add" -o "$work/p.html"
curl -s -c "$jar" -b "$jar" "$base/scp/apikeys.php?a=add" -o /dev/null \
    --data-urlencode "__CSRFToken__=$(token "$work/p.html")" -d do=add -d a=add -d isactive=1 \
    --data-urlencode "ipaddr=$client_ip" -d can_create_tickets=1 --data-urlencode "notes=restore test"
curl -s -c "$jar" -b "$jar" "$base/scp/apikeys.php?id=1" -o "$work/k.html"
apikey="$(grep -o -E '[A-Z0-9]{48}' "$work/k.html" | head -n 1)"
[ -n "$apikey" ] || fail "no API key"
api_ticket "Restore test ticket" | grep -q '^[0-9]*$' || fail "ticket creation through the API"
[ "$(attachment_sha)" = "$want_att" ] || fail "attachment differs before the backup"
fp_before="$(fingerprint)"
cfg_before="$(cfgsum)"

say "backup"
"$here/scripts/backup.sh" "$work/backups" | tail -n 3

say "destroying the stack (down -v)"
dc down -v >/dev/null 2>&1
[ -z "$(docker volume ls -q --filter "label=com.docker.compose.project=$OST_PROJECT")" ] || fail "volumes still exist"

say "restoring on a blank stack"
"$here/scripts/restore.sh" "$work"/backups/osticket-2*.sql "$work"/backups/osticket-config-*.tar | tail -n 2
wait_http "$base/login.php"

say "checks"
ok=1
check() { if [ "$2" = "$3" ]; then echo "  ok    $1"; else echo "  FAIL  $1 ($2 / $3)"; ok=0; fi; }
fp_after="$(fingerprint)"
if [ "$fp_before" = "$fp_after" ]; then
    echo "  ok    database fingerprint"
else
    echo "  FAIL  database fingerprint, differences:"
    printf '%s\n' "$fp_before" > "$work/fp-before.txt"; printf '%s\n' "$fp_after" > "$work/fp-after.txt"
    diff "$work/fp-before.txt" "$work/fp-after.txt" | sed 's/^/        /'
    ok=0
fi
cfg_after="$(cfgsum)"
check "ost-config.php checksum" "$cfg_before" "$cfg_after"
staff_login; echo "  ok    agent login"
check "attachment checksum" "$want_att" "$(attachment_sha)"
# An API key is bound to the client address; the recreated Docker network may use another subnet
# (hence another gateway address for requests from the host): realign the key, as an admin would.
curl -s -o /dev/null "$base/login.php"
client_ip="$(docker logs "$(dc ps -q web)" 2>&1 | grep 'GET /login.php' | tail -n 1 | cut -d' ' -f1)"
printf "update ost_api_key set ipaddr='%s';\n" "$client_ip" | sql
api_ticket "Ticket created after the restore" | grep -q '^[0-9]*$' && echo "  ok    ticket created through the API" || { echo "  FAIL  ticket creation after the restore"; ok=0; }
check "containers healthy" "3" "$(dc ps --format '{{.Health}}' | grep -c '^healthy$')"

[ "$ok" = 1 ] && say "RESTORE TEST PASSED" || fail "RESTORE TEST FAILED"
