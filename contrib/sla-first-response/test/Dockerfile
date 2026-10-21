# Image jetable pour exécuter osTicket en CLI (tests d'intégration du plugin).
# Ne sert qu'aux tests : PHP CLI + les extensions requises par l'installeur.
FROM php:8.2-cli
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libfreetype6-dev libicu-dev libjpeg-dev libpng-dev libzip-dev; \
    docker-php-ext-configure gd --with-jpeg --with-freetype; \
    docker-php-ext-install -j"$(nproc)" gd intl mysqli zip; \
    rm -rf /var/lib/apt/lists/*
