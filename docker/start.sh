#!/bin/sh
set -eu

port="${PORT:-10000}"
export PORT="$port"

envsubst '${PORT}' < /etc/apache2/sites-available/render.conf.template > /etc/apache2/sites-available/render.conf
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
a2ensite render

if [ -d /opt/nazarene-uploads-seed ] && [ -z "$(ls -A /var/www/html/uploads)" ]; then
    cp -a /opt/nazarene-uploads-seed/. /var/www/html/uploads/
fi

chown -R www-data:www-data /var/www/html/uploads
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
