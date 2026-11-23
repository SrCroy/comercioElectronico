#!/usr/bin/env bash
# Instala y configura Mechanical Bull completo en Docker.
# Se puede ejecutar varias veces: lo que ya existe no se duplica.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Se creó el archivo .env."
  echo "Ábrelo, cambia las contraseñas y vuelve a ejecutar: ./scripts/setup.sh"
  exit 1
fi
set -a; . ./.env; set +a

wp() { docker compose exec -T -u www-data wordpress wp "$@"; }

# Comprueba que WordPress puede conectarse a la base de datos (con las credenciales del contenedor).
db_ok() {
  docker compose exec -T wordpress php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = @new mysqli(getenv("WORDPRESS_DB_HOST"), getenv("WORDPRESS_DB_USER"), getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"));
    exit($m->connect_errno ? 1 : 0);' >/dev/null 2>&1
}

echo "==> Construyendo y levantando contenedores (la primera vez tarda unos minutos)"
docker compose up -d --build

echo "==> Esperando a que WordPress esté listo"
for _ in $(seq 1 90); do
  if docker compose exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null && db_ok; then
    break
  fi
  sleep 2
done
db_ok || {
  echo "ERROR: WordPress no se conecta a la base de datos."
  echo "Si antes tenías otra instalación, bórrala con: docker compose down -v   (y vuelve a ejecutar este script)"
  echo "Detalles: docker compose logs db wordpress"
  exit 1
}

echo "==> Instalando WordPress"
if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="$SITE_URL" --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" --skip-email
fi

echo "==> Idioma español"
wp language core install es_ES --activate || echo "AVISO: no se pudo descargar el idioma (revisa la conexión a internet)."

echo "==> Plugins y tema"
wp plugin is-installed woocommerce || wp plugin install woocommerce
# El idioma va antes de activar, para que WooCommerce cree sus páginas en español (Tienda, Carrito, Mi cuenta).
wp language plugin install woocommerce es_ES || true
wp plugin is-active woocommerce || wp plugin activate woocommerce
wp theme is-installed astra || wp theme install astra
wp language theme install astra es_ES >/dev/null 2>&1 || true
wp theme is-active mechanical-bull || wp theme activate mechanical-bull
wp plugin is-active mb-core || wp plugin activate mb-core
wp plugin is-installed elementor || wp plugin install elementor
wp language plugin install elementor es_ES >/dev/null 2>&1 || true
wp plugin is-active elementor || wp plugin activate elementor
wp plugin delete hello akismet >/dev/null 2>&1 || true
wp language core update >/dev/null 2>&1 || true

echo "==> Configurando la tienda (San Miguel, USD, IVA 13 %, envíos, pagos, páginas, menú, catálogo)"
wp mb instalar
if [ "${DEMO:-1}" = "1" ]; then
  wp mb demo
fi

echo "==> Portada en Elementor"
wp mb portada

wp rewrite structure "/%postname%/"
wp rewrite flush
wp cache flush >/dev/null 2>&1 || true

echo
echo "============================================================"
echo " Mechanical Bull instalado"
echo "------------------------------------------------------------"
echo "  Tienda:          $SITE_URL"
echo "  Administración:  $SITE_URL/wp-admin   (usuario: $ADMIN_USER)"
echo "  phpMyAdmin:      http://localhost:${PMA_PORT:-8081}"
echo "  Correos prueba:  http://localhost:${MAILPIT_PORT:-8025}"
echo "============================================================"
