# syntax=docker/dockerfile:1.7
#
# osTicket production image (Coderise).
#
# Built from THIS repository checkout (no release archive is downloaded), so the
# image content is exactly the commit that is checked out. Targets:
#
#   runtime  (default)  the hardened image that serves the helpdesk
#   install             runtime + the setup/ wizard, for first install and upgrades only
#
# Build from the repository root:
#   docker build -f deploy/docker/Dockerfile --target runtime -t coderise/osticket:1.18.4 .
#
# Hardening summary (see deploy/docker/README.md for what was verified):
#   - base image pinned by digest, OS packages upgraded at build time
#   - runs as www-data (uid 33), listens on 8080, no setuid binaries
#   - application tree owned by root and read-only for the runtime user
#   - explicit Apache module allowlist, no .htaccess outside four routing folders
#   - production php.ini, dangerous functions disabled, open_basedir
#   - setup/ never present in the runtime image (not even in a lower layer)
#   - ost-config.php lives in a dedicated volume that must be mounted read-only
#
# To refresh the base image: pick the new digest of php:8.3-apache-bookworm
#   (docker buildx imagetools inspect php:8.3-apache-bookworm) and update PHP_IMAGE.
ARG PHP_IMAGE=php:8.3-apache-bookworm@sha256:cbd3b3d9011668443d54ab40ec31db56c73d91b4c8198935d639aa80d2d815c1
ARG APCU_VERSION=5.1.28


# ------------------------------------------------------------------ base ----
# PHP extensions needed by osTicket, build tools removed afterwards.
FROM ${PHP_IMAGE} AS base
ARG APCU_VERSION

RUN set -eux; \
    apt-get update; \
    apt-get -y --no-install-recommends upgrade; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get install -y --no-install-recommends \
        libc-client-dev libkrb5-dev libfreetype6-dev libicu-dev \
        libjpeg-dev libpng-dev libzip-dev; \
    docker-php-ext-configure gd --with-jpeg --with-freetype; \
    docker-php-ext-configure imap --with-kerberos --with-imap-ssl; \
    docker-php-ext-install -j"$(nproc)" gd imap intl mysqli opcache zip; \
    pecl install "apcu-${APCU_VERSION}"; \
    docker-php-ext-enable apcu; \
    # keep only the runtime libraries the compiled extensions link against
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); print so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc /var/www/html/*; \
    # no setuid/setgid binaries: the container never needs to change identity
    find / -xdev -perm /6000 -type f -exec chmod a-s '{}' +; \
    php -m | grep -E '^(apcu|gd|imap|intl|mysqli|zip|Zend OPcache)$' | sort | tr '\n' ' '

# Web server: our own minimal configuration replaces the Debian one entirely.
RUN rm -rf /etc/apache2/apache2.conf /etc/apache2/mods-enabled /etc/apache2/conf-enabled \
           /etc/apache2/sites-enabled /etc/apache2/ports.conf
COPY --chown=root:root --chmod=0644 deploy/docker/conf/apache2.conf /etc/apache2/apache2.conf

# PHP: production defaults plus our overrides (conf.d is read after php.ini).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --chown=root:root --chmod=0644 deploy/docker/conf/osticket.ini "$PHP_INI_DIR/conf.d/zz-osticket.ini"

ENV OSTICKET_COOKIE_SECURE=1 \
    OSTICKET_MAX_WORKERS=40 \
    OSTICKET_TRUSTED_PROXIES="10.0.0.0/8 172.16.0.0/12 192.168.0.0/16 127.0.0.1"


# ------------------------------------------------------------------- src ----
# The repository checkout (see Dockerfile.dockerignore for what is left out).
FROM base AS src
COPY . /src
# Fail closed on a corrupted checkout. A Windows checkout with core.autocrlf turns
# LF into CRLF, which changes the schema signature osTicket verifies at install and
# upgrade time. Build with deploy/docker/scripts/build.sh (it builds from `git archive`).
RUN set -eu; cd /src; \
    expected="$(cat include/upgrader/streams/core.sig)"; \
    actual="$(md5sum setup/inc/streams/core/install-mysql.sql | cut -d' ' -f1)"; \
    [ "$expected" = "$actual" ] || { \
        echo "ERROR: install-mysql.sql signature $actual does not match core.sig $expected (CRLF line endings?)." >&2; \
        echo "       Build with deploy/docker/scripts/build.sh instead of building the working tree." >&2; \
        exit 1; }


# ---------------------------------------------------------------- pruned ----
# Source tree without the installer and without repository-only files, plus the
# optional overlay (theme, language packs, plugins): see deploy/docker/overlay/.
FROM src AS pruned
COPY deploy/docker/overlay/ /overlay/
RUN set -eux; \
    cd /src; \
    rm -rf setup deploy web.config README.md UPGRADING.txt WHATSNEW.md SECURITY.md ISSUE_TEMPLATE.md; \
    rm -f /overlay/.gitkeep /overlay/README.md; \
    cp -a /overlay/. /src/; \
    rm -rf /overlay


# --------------------------------------------------------------- runtime ----
FROM base AS runtime
ARG OST_VERSION=1.18.4
ARG OST_REVISION=unknown
LABEL org.opencontainers.image.title="osTicket (Coderise production build)" \
      org.opencontainers.image.version="${OST_VERSION}" \
      org.opencontainers.image.revision="${OST_REVISION}" \
      org.opencontainers.image.source="https://github.com/coderise-fr/osTicket" \
      org.opencontainers.image.licenses="GPL-2.0-only"

COPY --from=pruned --chown=root:root /src/ /var/www/html/

RUN set -eux; \
    # the document root itself belongs to www-data in the base image: take it back
    chown root:root /var/www /var/www/html; \
    chmod 0755 /var/www /var/www/html; \
    find /var/www/html -type d -exec chmod 0755 '{}' +; \
    find /var/www/html -type f -exec chmod 0644 '{}' +; \
    # the configuration (database password, secret salt) is kept out of the image
    install -d -o www-data -g www-data -m 0750 /etc/osticket; \
    ln -s /etc/osticket/ost-config.php /var/www/html/include/ost-config.php; \
    install -d -o www-data -g www-data -m 0755 /run/apache2

COPY --chown=root:root --chmod=0755 deploy/docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY --chown=root:root --chmod=0644 deploy/docker/healthcheck.php /usr/local/bin/healthcheck.php

USER 33:33
WORKDIR /var/www/html
EXPOSE 8080
STOPSIGNAL SIGWINCH
HEALTHCHECK --interval=30s --timeout=8s --start-period=40s --retries=3 \
    CMD ["php", "-d", "open_basedir=", "/usr/local/bin/healthcheck.php"]
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["web"]


# --------------------------------------------------------------- install ----
# Adds the setup wizard. Use it only for the first install and for upgrades,
# never expose it publicly, and stop it as soon as the wizard is done.
FROM runtime AS install
USER root
COPY --from=src --chown=root:root /src/setup /var/www/html/setup
RUN set -eux; \
    find /var/www/html/setup -type d -exec chmod 0755 '{}' +; \
    find /var/www/html/setup -type f -exec chmod 0644 '{}' +
USER 33:33
CMD ["install"]


# ------------------------------------------------------------ production ----
# Must stay the LAST stage: platforms that cannot pick a build target (DigitalOcean
# App Platform) build the final stage, and that must never be the setup wizard.
FROM runtime AS production
