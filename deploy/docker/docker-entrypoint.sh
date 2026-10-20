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

# Platforms without volumes (e.g. DigitalOcean App Platform): build ost-config.php
# from environment variables. Opt-in with OSTICKET_CONFIG_FROM_ENV=1. The schema must
# already be installed (run the install target once against the same database) and
# SECRET_SALT must be the value that install generated, kept as an encrypted secret.
render_config_from_env() {
    for v in DB_HOST DB_NAME DB_USER DB_PASS SECRET_SALT; do
        eval "[ -n \"\${$v:-}\" ]" || fail "OSTICKET_CONFIG_FROM_ENV=1 needs $v."
    done
    [ -d /etc/osticket ] && [ -w /etc/osticket ] || fail "/etc/osticket must be writable to render the configuration."
    rm -f "$CONFIG"; export CONFIG_PATH="$CONFIG"
    php -d open_basedir= -r '
        $e = function ($k, $d = "") { $v = getenv($k); return var_export($v === false || $v === "" ? $d : $v, true); };
        $host = getenv("DB_HOST") . (getenv("DB_PORT") ? ":" . getenv("DB_PORT") : "");
        $c = "<?php\n"
           . "if (!defined(\"INCLUDE_DIR\")) die(\"kwaheri rafiki!\");\n"
           . "define(\"OSTINSTALLED\", TRUE);\n"
           . "define(\"SECRET_SALT\", " . $e("SECRET_SALT") . ");\n"
           . "define(\"ADMIN_EMAIL\", " . $e("ADMIN_EMAIL", "root@localhost") . ");\n"
           . "define(\"DBTYPE\", \"mysql\");\n"
           . "define(\"DBHOST\", " . var_export($host, true) . ");\n"
           . "define(\"DBNAME\", " . $e("DB_NAME") . ");\n"
           . "define(\"DBUSER\", " . $e("DB_USER") . ");\n"
           . "define(\"DBPASS\", " . $e("DB_PASS") . ");\n"
           . "define(\"TABLE_PREFIX\", " . $e("DB_PREFIX", "ost_") . ");\n"
           . "define(\"TRUSTED_PROXIES\", \"\");\n"
           . "define(\"LOCAL_NETWORKS\", \"127.0.0.0/24\");\n"
           . "define(\"SESSION_SESSID\", \"OSTSESSID\");\n";
        // Managed databases usually require TLS: DB_SSL=1 (DB_SSL_CA = CA file, optional).
        if (getenv("DB_SSL") === "1")
            $c .= "define(\"DBSSLCA\", " . $e("DB_SSL_CA") . ");\n"
                . "define(\"DBSSLCERT\", NULL);\n"
                . "define(\"DBSSLKEY\", NULL);\n";
        file_put_contents(getenv("CONFIG_PATH"), $c);
    ' || fail "could not write the configuration."
    chmod 0440 "$CONFIG"
}

check_installed() {
    if [ "${OSTICKET_CONFIG_FROM_ENV:-0}" = 1 ]; then
        render_config_from_env
        [ ! -e "$APP/setup" ] || fail "the setup/ directory must not exist in a runtime image."
        return 0
    fi
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
            # The heartbeat file feeds the container health check (see compose.yaml).
            if php "$APP/api/cron.php"; then
                touch /tmp/osticket-cron.ok
            else
                echo "osticket-entrypoint: cron run failed with status $?" >&2
            fi
            sleep 60 &
            wait $!
        done
        ;;
    *)
        exec "$@"
        ;;
esac
