#!/usr/bin/env bash
# Arranque del contenedor de WordPress de Mechanical Bull.
# En tu PC (docker compose) no hace nada extra. En Railway:
#   - escucha en el puerto que da Railway ($PORT)
#   - copia el tema y el plugin de esta versión al volumen (MB_SYNC_CODE=1)
#   - instala/actualiza la tienda sola en segundo plano (MB_AUTO_INSTALL=1)
set -euo pipefail

if [ -n "${PORT:-}" ] && [ "$PORT" != "80" ]; then
  sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# ¡Solo en Railway! En tu PC el tema y el plugin están montados desde tu carpeta y no se deben tocar.
if [ "${MB_SYNC_CODE:-0}" = "1" ]; then
  dest=/var/www/html/wp-content
  mkdir -p "$dest/themes" "$dest/plugins"
  rm -rf "$dest/themes/mechanical-bull" "$dest/plugins/mb-core"
  cp -a /opt/mb/themes/mechanical-bull "$dest/themes/"
  cp -a /opt/mb/plugins/mb-core "$dest/plugins/"
  chown -R www-data:www-data "$dest"
fi

if [ "${MB_AUTO_INSTALL:-0}" = "1" ]; then
  /usr/local/bin/mb-arranque.sh &
fi

exec docker-entrypoint.sh "$@"
