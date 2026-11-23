#!/usr/bin/env bash
# Instala Mechanical Bull la primera vez y aplica la configuración en cada despliegue.
# Lo lanza mb-entrypoint.sh en segundo plano cuando MB_AUTO_INSTALL=1 (Railway).
# Es lo mismo que hace scripts/setup.sh, pero desde dentro del contenedor.
set -uo pipefail
cd /var/www/html

log() { echo "[mb-arranque] $*"; }
wp() { su -s /bin/bash www-data -c "cd /var/www/html && wp $(printf '%q ' "$@")"; }

db_ok() {
  php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $h = getenv("WORDPRESS_DB_HOST"); $p = 3306;
    if (preg_match("/^(.*):(\d+)$/", $h, $m)) { $h = $m[1]; $p = (int) $m[2]; }
    $c = @new mysqli($h, getenv("WORDPRESS_DB_USER"), getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"), $p);
    exit($c->connect_errno ? 1 : 0);' >/dev/null 2>&1
}

log "Esperando a WordPress y a la base de datos..."
for _ in $(seq 1 150); do
  [ -f wp-config.php ] && db_ok && break
  sleep 2
done
if ! db_ok; then
  log "ERROR: no hay conexión con la base de datos. Revisa las variables WORDPRESS_DB_* en Railway."
  exit 1
fi

primera=0
if ! wp core is-installed >/dev/null 2>&1; then
  for v in SITE_URL ADMIN_USER ADMIN_PASSWORD ADMIN_EMAIL; do
    if [ -z "${!v:-}" ]; then log "ERROR: falta la variable $v en Railway."; exit 1; fi
  done
  log "Instalando WordPress (primera vez)"
  wp core install --url="$SITE_URL" --title="${SITE_TITLE:-Mechanical Bull}" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" --skip-email || exit 1
  primera=1
fi

# Si cambias de dominio, basta con cambiar SITE_URL en Railway y redesplegar.
case "${SITE_URL:-}" in
  http://?*|https://?*)
    wp option update home "$SITE_URL" >/dev/null
    wp option update siteurl "$SITE_URL" >/dev/null ;;
esac

log "Idioma, plugins y tema"
wp language core is-installed es_ES >/dev/null 2>&1 || wp language core install es_ES --activate || true
wp plugin is-installed woocommerce || wp plugin install woocommerce
wp language plugin is-installed woocommerce es_ES >/dev/null 2>&1 || wp language plugin install woocommerce es_ES || true
wp plugin is-active woocommerce || wp plugin activate woocommerce
wp theme is-installed astra || wp theme install astra
wp language theme is-installed astra es_ES >/dev/null 2>&1 || wp language theme install astra es_ES >/dev/null 2>&1 || true
wp theme is-active mechanical-bull || wp theme activate mechanical-bull
wp plugin is-active mb-core || wp plugin activate mb-core
wp plugin is-installed elementor || wp plugin install elementor
wp language plugin is-installed elementor es_ES >/dev/null 2>&1 || wp language plugin install elementor es_ES >/dev/null 2>&1 || true
wp plugin is-active elementor || wp plugin activate elementor

log "Configuración de Mechanical Bull"
wp mb instalar

if [ "$primera" = "1" ]; then
  wp plugin delete hello akismet >/dev/null 2>&1 || true
  [ "${DEMO:-1}" = "1" ] && wp mb demo
  wp mb portada
  wp rewrite structure "/%postname%/"
fi

wp rewrite flush
wp cache flush >/dev/null 2>&1 || true
log "Listo: ${SITE_URL:-}"
