# Imagem do Almanaque: PHP-FPM com a aplicação dentro.
#
# Quatro estágios. O primeiro instala as extensões e vira a base comum. O de
# dependências monta a pasta vendor e fica para trás. O de desenvolvimento
# leva Xdebug e o Composer. O de produção não leva nenhum dos dois, roda como
# usuário sem privilégio e já vem com os caches do framework gerados.

# ------------------------------------------------------------------ base
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        icu-libs \
        libzip \
        libpng \
        oniguruma \
    && apk add --no-cache --virtual .construcao \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_mysql \
        zip \
        gd \
        opcache \
    && apk del .construcao

COPY docker/php/almanaque.ini /usr/local/etc/php/conf.d/almanaque.ini

WORKDIR /app

# --------------------------------------------------------- dependências
FROM base AS dependencias

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock symfony.lock ./

# --no-scripts porque os scripts do Flex precisam do código da aplicação, que
# ainda não está aqui: esta camada existe para ficar em cache enquanto
# nenhuma dependência mudar.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ----------------------------------------------------- desenvolvimento
FROM base AS desenvolvimento

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache --virtual .construcao $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .construcao

ENV APP_ENV=dev

EXPOSE 9000
CMD ["php-fpm"]

# ------------------------------------------------------------- produção
FROM base AS producao

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --chown=www-data:www-data . .
COPY --from=dependencias --chown=www-data:www-data /app/vendor ./vendor

# O cache do framework é gerado na construção da imagem, não na primeira
# requisição: o primeiro visitante depois de uma publicação não deve pagar
# por isso, e a pasta pode ser somente leitura em produção.
RUN php bin/console cache:warmup --env=prod \
    && mkdir -p var/log \
    && chown -R www-data:www-data var

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

CMD ["php-fpm"]
