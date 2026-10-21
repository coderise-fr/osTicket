#!/bin/sh
# Integration test of the Stripe sync plugin against a REAL osTicket + MariaDB.
#
#   contrib/stripe-sync/tests/integration/run.sh
#
# Run it from the repository root, with the plugin committed (the tree is taken
# from `git archive HEAD`, with core.autocrlf=false so that the schema
# signature check of the installer passes, even on Windows).
#
# What it does: starts MariaDB and a PHP/Apache container on a private Docker
# network (no published port), installs osTicket with its web installer,
# installs the plugin as an administrator would, then replays signed Stripe
# webhooks (self-signed with a throw-away secret generated here) and checks the
# result in the database. Everything is removed at the end.
#
# Not covered: real Stripe events, the hardened Docker image, a real SMTP
# server (mails are captured by a fake sendmail).
#
# Variables:
#   OST_PHP_IMAGE  PHP 8.2+ with Apache, mysqli, mbstring, intl, xml, curl
#                  (default: wordpress:php8.2-apache)
#   OST_DB_IMAGE   MariaDB/MySQL image (default: mariadb:11.4)
#   KEEP=1         keep the containers for debugging

set -eu

# Git Bash on Windows rewrites "/srv/ost"-like arguments; keep container paths intact
# for docker only, and convert host paths explicitly.
docker() { MSYS_NO_PATHCONV=1 command docker "$@"; }
if command -v cygpath >/dev/null 2>&1; then lp() { cygpath -w "$1"; }; else lp() { printf %s "$1"; }; fi

HERE=$(cd "$(dirname "$0")" && pwd)
PHP_IMAGE=${OST_PHP_IMAGE:-wordpress:php8.2-apache}
DB_IMAGE=${OST_DB_IMAGE:-mariadb:11.4}
rand() { od -An -N12 -tx1 /dev/urandom | tr -d ' \n'; }
# Unique names (random suffix): several runs, or other Docker users, never clash.
P=ostsync-$$-$(rand | cut -c1-8)
WEB=$P-web
DB=$P-db
NET=$P-net
TMP=$(mktemp -d)
DBPASS=$(rand)
ROOTPASS=$(rand)
WHSEC=whsec_$(rand)

cleanup() {
    if [ "${KEEP:-}" = 1 ]; then
        echo "Containers kept: $WEB $DB (network $NET)"
    else
        docker rm -f "$WEB" "$DB" >/dev/null 2>&1 || true
        docker network rm "$NET" >/dev/null 2>&1 || true
    fi
    rm -rf "$TMP"
}
trap cleanup EXIT

echo "== network and database"
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" \
    -e MARIADB_DATABASE=osticket -e MARIADB_USER=ost \
    -e MARIADB_PASSWORD="$DBPASS" -e MARIADB_ROOT_PASSWORD="$ROOTPASS" \
    "$DB_IMAGE" --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null

echo "== osTicket tree (git archive HEAD)"
git -c core.autocrlf=false archive -o "$(lp "$TMP/ost.tar")" HEAD
docker run -d --name "$WEB" --network "$NET" --entrypoint sleep "$PHP_IMAGE" 3600 >/dev/null
docker exec "$WEB" mkdir -p /srv/ost
docker cp "$(lp "$TMP/ost.tar")" "$WEB:/srv/ost.tar"
docker exec "$WEB" sh -c 'cd /srv/ost && tar xf ../ost.tar && cp include/ost-sampleconfig.php include/ost-config.php && chmod 666 include/ost-config.php'
for f in boot.php setup_plugin.php scenario-flow.php scenario-concurrency.php; do
    docker cp "$(lp "$HERE/$f")" "$WEB:/tmp/$f"
done
printf '#!/bin/sh\ncat > /tmp/mail-$(date +%%s%%N).eml\n' > "$TMP/fakesendmail"
docker cp "$(lp "$TMP/fakesendmail")" "$WEB:/usr/local/bin/fakesendmail"
docker exec "$WEB" chmod +x /usr/local/bin/fakesendmail

echo "== waiting for the database"
i=0
until docker exec "$DB" mariadb -uost -p"$DBPASS" -e 'select 1' osticket >/dev/null 2>&1; do
    i=$((i + 1)); [ $i -gt 60 ] && { echo "database not ready"; exit 1; }
    sleep 1
done

echo "== installing osTicket with the web installer"
cat > "$TMP/install.sh" <<EOF
set -e
cd /srv/ost
php -S 127.0.0.1:8000 -t /srv/ost >/dev/null 2>&1 &
PID=\$!
sleep 2
J=/tmp/cj; B=http://127.0.0.1:8000/setup/install.php
curl -s -c \$J -b \$J \$B -o /dev/null
curl -s -c \$J -b \$J -d s=prereq \$B -o /dev/null
curl -s -c \$J -b \$J -d s=config \$B -o /dev/null
curl -s -c \$J -b \$J \$B -o /tmp/inst.html \
  -d s=install -d name=Helpdesk -d email=helpdesk@example.org -d lang_id=en_US \
  -d fname=Ad -d lname=Min -d admin_email=admin@example.org -d username=sysadmin \
  -d passwd='Zx9!kQ7vLm2#pT' -d passwd2='Zx9!kQ7vLm2#pT' -d prefix=ost_ \
  -d dbhost=$DB -d dbname=osticket -d dbuser=ost -d dbpass=$DBPASS -d timezone=UTC
kill \$PID
grep -q Congratulations /tmp/inst.html
EOF
docker cp "$(lp "$TMP/install.sh")" "$WEB:/tmp/install.sh"
docker exec "$WEB" sh /tmp/install.sh || { echo "osTicket installation failed"; exit 1; }

echo "== installing the plugin like an administrator would"
docker exec "$WEB" sh -c 'cp -r /srv/ost/contrib/stripe-sync /srv/ost/include/plugins/stripe-sync && rm -rf /srv/ost/include/plugins/stripe-sync/tests && chmod -R a+rX /srv/ost && cd /tmp && php setup_plugin.php'

echo "== starting Apache (port 8001, secret set) and php -S (port 8002, secret NOT set)"
docker cp "$(lp "$HERE/apache-vhost.conf")" "$WEB:/etc/apache2/sites-enabled/ost-int.conf"
docker exec "$WEB" sh -c 'rm -f /etc/apache2/sites-enabled/000-default.conf; a2enmod rewrite >/dev/null 2>&1 || true'
docker exec -d -e STRIPE_SYNC_WEBHOOK_SECRET="$WHSEC" "$WEB" apache2ctl -D FOREGROUND
docker exec -d -w /srv/ost "$WEB" php -S 127.0.0.1:8002 -t /srv/ost
sleep 3

echo "== scenarios"
status=0
docker exec -e STRIPE_SYNC_WEBHOOK_SECRET="$WHSEC" "$WEB" sh -c 'cd /tmp && rm -f /tmp/mail-*.eml /tmp/cj-client && php scenario-flow.php' || status=1
docker exec "$DB" mariadb -uroot -p"$ROOTPASS" osticket -e "DELETE FROM ost_config WHERE namespace IN ('stripesync','pwreset'); DELETE FROM ost_ticket; DELETE FROM ost_user_account; DELETE FROM ost_user WHERE id>0; DELETE FROM ost_user_email; DELETE FROM ost_organization WHERE name IN ('Priority','Acme Corp'); DELETE FROM ost_session;"
docker exec -e STRIPE_SYNC_WEBHOOK_SECRET="$WHSEC" "$WEB" sh -c 'cd /tmp && php scenario-concurrency.php' || status=1

[ $status = 0 ] && echo "ALL GOOD" || echo "FAILURES (see above)"
exit $status
