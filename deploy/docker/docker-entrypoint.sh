#!/bin/sh
# osTicket container entrypoint.
#
#   web      serve the helpdesk (default). Refuses to start unless the configuration
#            volume is installed and read-only.
#   install  same server for the setup wizard; creates an empty configuration in
#            the (writable) configuration volume on first start.
#   cron     run osTicket's scheduler (mail fetching, overdue marking, purges)
#            once a minute.
set -eu

CONFIG=/etc/osticket/ost-config.php
SAMPLE=/var/www/html/include/ost-sampleconfig.php
APP=/var/www/html

fail() {
    echo "osticket-entrypoint: $*" >&2
    exit 1
}

check_installed() {
    [ -s "$CONFIG" ] || fail "no configuration in /etc/osticket. Run the install target first (see deploy/docker/README.md)."
    if grep -q '%CONFIG-' "$CONFIG"; then
        fail "the configuration still contains the installer placeholders: finish the setup wizard first."
    fi
    # Fail closed: the configuration holds the database password and the secret salt.
    if [ -w "$CONFIG" ] || [ -w /etc/osticket ]; then
        fail "the configuration volume must be mounted read-only at /etc/osticket in runtime mode."
    fi
    [ ! -e "$APP/setup" ] || fail "the setup/ directory must not exist in a runtime image."
}

mode="${1:-web}"
umask 027

case "$mode" in
    web)
        check_installed
        exec apache2 -DFOREGROUND -f /etc/apache2/apache2.conf
        ;;
    install)
        [ -w /etc/osticket ] || fail "install mode needs /etc/osticket to be writable."
        if [ ! -s "$CONFIG" ]; then
            cp "$SAMPLE" "$CONFIG"
            chmod 0640 "$CONFIG"
        fi
        echo "osticket-entrypoint: setup wizard enabled. Stop this container as soon as the install is done." >&2
        exec apache2 -DFOREGROUND -f /etc/apache2/apache2.conf
        ;;
    cron)
        check_installed
        trap 'exit 0' TERM INT
        while :; do
            php "$APP/api/cron.php" || echo "osticket-entrypoint: cron run failed with status $?" >&2
            sleep 60 &
            wait $!
        done
        ;;
    *)
        exec "$@"
        ;;
esac
