FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && mkdir -p /var/www/html/logs /var/www/html/backups \
    && chown -R www-data:www-data /var/www/html/logs /var/www/html/backups

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    ServerAdmin webmaster@localhost' \
    '    DocumentRoot /var/www/html' \
    '' \
    '    DirectoryIndex index.php index.html' \
    '    RedirectMatch 301 ^/bitacora_$ /bitacora_/' \
    '    Alias /bitacora_/ /var/www/html/' \
    '' \
    '    <Directory /var/www/html>' \
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
