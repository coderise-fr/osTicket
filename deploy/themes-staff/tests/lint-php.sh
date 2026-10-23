#!/bin/sh
# php -l on every PHP file of the coderise-agent overlay and tests, run in the
# demo image (ostdemo/osticket:demo, built by local-demo.sh). Stdin form: no
# path conversion needed. Usage : sh deploy/themes-staff/tests/lint-php.sh
set -u

here="$(cd "$(dirname "$0")" && pwd)"
pkg="$here/../coderise-agent"
img="${OST_DEMO_IMAGE:-ostdemo/osticket:demo}"
fails=0
count=0

for f in $(find "$pkg/overlay" "$pkg/tests" -name '*.php' | LC_ALL=C sort); do
    count=$((count + 1))
    if out="$(docker run --rm -i --entrypoint php "$img" \
            -d open_basedir= -d display_errors=1 -d error_reporting=-1 -l < "$f" 2>&1)"; then
        echo "ok    ${f#"$pkg"/}"
    else
        echo "FAIL  ${f#"$pkg"/}"
        echo "$out"
        fails=$((fails + 1))
    fi
done

[ "$count" -gt 0 ] || { echo "lint-php.sh: no PHP file found" >&2; exit 1; }
[ "$fails" -eq 0 ] || exit 1
echo "lint-php.sh: $count file(s) OK"
