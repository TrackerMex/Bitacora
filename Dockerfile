FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html/bitacora_

COPY . /var/www/html/bitacora_/

RUN chown -R www-data:www-data /var/www/html/bitacora_ \
    && find /var/www/html/bitacora_ -type d -exec chmod 755 {} \; \
    && find /var/www/html/bitacora_ -type f -exec chmod 644 {} \; \
    && mkdir -p /var/www/html/bitacora_/logs /var/www/html/bitacora_/backups \
    && chown -R www-data:www-data /var/www/html/bitacora_/logs /var/www/html/bitacora_/backups

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    ServerAdmin webmaster@localhost' \
    '    DocumentRoot /var/www/html' \
    '' \
    '    RedirectMatch 302 ^/$ /bitacora_/' \
    '' \
    '    <Directory /var/www/html/bitacora_>' \
    '        Options -Indexes +FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '' \
    '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
