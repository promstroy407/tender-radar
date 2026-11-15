# Образ приложения: PHP-FPM с расширениями для PostgreSQL и Redis.
# gd нужен библиотеке Excel (PhpSpreadsheet), poppler-utils (pdftotext) — чтению смет в PDF.
FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip git curl ca-certificates \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev poppler-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql intl zip opcache pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Корневой и промежуточный сертификаты Минцифры: ими подписан сайт ЕИС (zakupki.gov.ru).
# Источник — официальная страница Госуслуг о российских сертификатах (gu-st.ru).
RUN mkdir -p /usr/local/share/ca-certificates/russian \
    && curl -fsSL https://gu-st.ru/content/lending/russian_trusted_root_ca_pem.crt \
        -o /usr/local/share/ca-certificates/russian/russian_trusted_root_ca.crt \
    && curl -fsSL https://gu-st.ru/content/lending/russian_trusted_sub_ca_pem.crt \
        -o /usr/local/share/ca-certificates/russian/russian_trusted_sub_ca.crt \
    && update-ca-certificates

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
ENV COMPOSER_HOME=/tmp/composer

# Приложение работает не от root.
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
USER www-data
