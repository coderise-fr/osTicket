#!/bin/sh
# HTTP method and path matrix of the hardened Apache (deploy/docker/conf/apache2.conf).
#
#   deploy/docker/tests/test-methods.sh http://127.0.0.1:8080
#   OST_SESSID=<OSTSESSID cookie of a logged-in agent> deploy/docker/tests/test-methods.sh <base>
#
# Read-only for the application: the DELETE requests target ids that do not exist
# (999999999) and run without a CSRF token, so osTicket refuses them before acting.
# Each line: expected, method, path, HTTP status, who answered ("apache" = Apache error
# page, the request never reached PHP; "php" = osTicket answered).
#   deny  Apache must refuse it (403, 404 or 405 error page, PHP never runs)
#   php   Apache must let it through to osTicket (any status, not an Apache error page)
# With OST_SESSID, also checks that osTicket rejects an agent DELETE without a CSRF token
# (400 "Valid CSRF Token Required"). Exit status: number of mismatches.
set -u

base="${1:?usage: $0 <base URL, e.g. http://127.0.0.1:8080>}"
base="${base%/}"
body="$(mktemp)"
trap 'rm -f "$body"' EXIT INT TERM
fail=0
n=0

# check <expected> <method> <path> [extra curl args...]
check() {
    exp="$1"; m="$2"; p="$3"; shift 3
    if [ "$m" = HEAD ]; then
        code="$(curl -s -o "$body" -w '%{http_code}' -I --path-as-is "$@" "$base$p")"
    else
        code="$(curl -s -o "$body" -w '%{http_code}' -X "$m" --path-as-is "$@" "$base$p")"
    fi
    if grep -q '<h1>\(Forbidden\|Not Found\|Method Not Allowed\|Bad Request\)</h1>' "$body" \
            || { [ "$m" = HEAD ] && [ "$code" = 403 ]; }; then
        who=apache
    else
        who=php
    fi
    case "$exp:$who:$code" in
        deny:apache:403|deny:apache:404|deny:apache:405|deny:apache:400) ok=ok ;;
        php:php:*) ok=ok ;;
        csrf:php:400) ok=ok ;;
        *) ok=FAIL; fail=$((fail + 1)) ;;
    esac
    n=$((n + 1))
    printf '%-4s %-5s %-7s %-60s %s %s\n' "$ok" "$exp" "$m" "$p" "$code" "$who"
}

id=999999999

# DELETE reaches osTicket on the two AJAX routers only (osTicket then checks login and CSRF).
check php  DELETE "/scp/ajax.php/draft/$id"
check php  DELETE "/scp/ajax.php/note/$id"
check php  DELETE "/scp/ajax.php/form/answer/$id/$id"
check php  DELETE "/scp/ajax.php/orgs/$id/delete"
check php  DELETE "/ajax.php/draft/$id"
check php  DELETE "//scp//ajax.php/draft/$id"

# DELETE everywhere else: refused by Apache.
for p in / /index.php /open.php /view.php /tickets.php /scp/ /scp/index.php /scp/tickets.php \
         /scp/orgs.php /scp/login.php /css/osticket.css /js/osticket.js /scp/js/scp.js \
         /api/http.php/tickets.json /api/tickets.json /apps/dispatcher.php/x /pages/x \
         /include/ost-config.php /include/ajax.draft.php /include/class.dispatcher.php \
         /setup/ /setup/install.php /manage.php /bootstrap.php /main.inc.php /.htaccess /.git/config \
         /SCP/ajax.php/draft/$id /scp/ajax.php.bak /scp/Ajax.php/draft/$id; do
    check deny DELETE "$p"
done

# Path tricks around the router name: the rule tests the file that runs, not the URL.
check deny DELETE "/scp/tickets.php/ajax.php/draft/$id"
check deny DELETE "/index.php/ajax.php/draft/$id"
check deny DELETE "/scp/ajax.php/../tickets.php"
check deny DELETE "/ajax.php/../include/ost-config.php"
check deny DELETE "/scp/ajax.php/../../include/ost-config.php"
check deny DELETE "/scp/ajax.php/%2e%2e/%2e%2e/include/ost-config.php"
check deny DELETE "/scp/ajax.php/..%2f..%2finclude/ost-config.php"
check deny DELETE "/include/../scp/tickets.php"
check deny DELETE "/scp/ajax.php%00/draft/$id"
check deny GET    "/ajax.php/../include/ost-config.php"
check deny GET    "/scp/ajax.php/%2e%2e/%2e%2e/include/ost-config.php"

# Other methods: refused everywhere, the AJAX routers included (TRACE: 405, TraceEnable Off).
for m in PUT PATCH OPTIONS TRACE PROPFIND MKCOL COPY MOVE CONNECT; do
    for p in "/scp/ajax.php/draft/$id" "/ajax.php/draft/$id" /scp/tickets.php /index.php /api/tickets.json; do
        check deny "$m" "$p"
    done
done

# Sensitive paths stay refused for GET.
for p in /include/ /include/ost-config.php /include/ost-sampleconfig.php /include/class.osticket.php \
         /include/staff/header.inc.php /setup/ /setup/install.php /manage.php /bootstrap.php \
         /main.inc.php /scp/staff.inc.php /client.inc.php /.htaccess /api/.htaccess /.git/config \
         /README.md /setup/inc/streams/core/install-mysql.sql; do
    check deny GET "$p"
done

# GET, HEAD and POST still reach osTicket.
check php GET  /scp/login.php
check php HEAD /scp/login.php
check php GET  /index.php
check php POST "/scp/ajax.php/draft/$id"

if [ -n "${OST_SESSID:-}" ]; then
    # Logged-in agent, no CSRF token: osTicket must refuse the DELETE (400).
    check csrf DELETE "/scp/ajax.php/draft/$id" -H "Cookie: OSTSESSID=$OST_SESSID"
    check csrf DELETE "/scp/ajax.php/note/$id" -H "Cookie: OSTSESSID=$OST_SESSID"
    check csrf DELETE "/scp/ajax.php/draft/$id" -H "Cookie: OSTSESSID=$OST_SESSID" -H "X-CSRFToken: forged"
fi

echo "$n checks, $fail mismatch(es)"
exit "$fail"
