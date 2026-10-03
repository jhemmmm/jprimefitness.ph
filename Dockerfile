FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    bcmath \
    intl \
    gd \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid ${GID} appgroup \
    && useradd --uid ${UID} --gid appgroup --create-home --shell /bin/bash appuser

WORKDIR /var/www/html

COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

USER appuser
