FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql opcache curl gd \
    && a2enmod rewrite headers expires \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY docker/apache.conf /etc/apache2/conf-available/mjdev-security.conf
RUN a2enconf mjdev-security

WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/logs storage/uploads storage/cache \
    && chown -R www-data:www-data storage \
    && chmod -R 750 storage \
    && chmod 755 docker/entrypoint.sh

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD curl -fsS http://127.0.0.1/health || exit 1
CMD ["docker/entrypoint.sh"]
