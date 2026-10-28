FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends nodejs npm supervisor gettext-base \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers proxy proxy_http \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
COPY uploads/ /opt/nazarene-uploads-seed/
COPY docker/apache-vhost.conf.template /etc/apache2/sites-available/render.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/nazarene.conf
COPY docker/start.sh /usr/local/bin/nazarene-start
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN npm install --omit=dev --prefix /var/www/html \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && a2dissite 000-default

EXPOSE 10000
CMD ["sh", "/usr/local/bin/nazarene-start"]
