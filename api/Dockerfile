# FrankenPHP + PHP 8.5 (tag à ajuster selon disponibilité de l'image officielle)
FROM dunglas/frankenphp:php8.5

ENV SERVER_NAME=localhost

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip

# Composer (binaire)
COPY --from=composer/composer:2-bin /composer /usr/bin/composer

WORKDIR /app

COPY frankenphp/Caddyfile /etc/frankenphp/Caddyfile

# En dev, le code est monté par volume (voir compose.yaml) ; en prod on COPY + composer install.
COPY . /app

EXPOSE 80 443
