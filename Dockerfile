FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
        git unzip libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql intl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sans cette variable, Composer lancé en root désactive le plugin symfony/runtime
# (vendor/autoload_runtime.php manquant → erreur 500).
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
COPY . /app

RUN composer install --no-interaction --no-progress --prefer-dist

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
