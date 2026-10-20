#!/bin/sh
# Generate the database secrets once. Existing files are never overwritten.
#   ./scripts/gen-secrets.sh [directory]    (default: ./secrets)
set -eu

dir="${1:-${OST_SECRETS_DIR:-./secrets}}"
umask 077
mkdir -p "$dir"

gen() {
    # 32 URL-safe characters from the kernel random source.
    head -c 48 /dev/urandom | base64 | tr -d '/+=\n' | cut -c1-32
}

for name in db_password db_root_password; do
    file="$dir/$name.txt"
    if [ -e "$file" ]; then
        echo "kept    $file"
    else
        gen > "$file"
        chmod 0600 "$file"
        echo "created $file"
    fi
done
