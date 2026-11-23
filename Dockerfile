# Mechanical Bull — imagen de WordPress
# WordPress actual (7.x) con PHP 8.3 y Apache. WooCommerce ya exige WordPress 7.
# Sirve igual para tu PC (docker compose) y para Railway.
FROM wordpress:php8.3-apache

# Ajustes de PHP para WooCommerce (fotos de productos y equipos)
COPY config/php.ini /usr/local/etc/php/conf.d/zz-mechanical-bull.ini

# WP-CLI para instalar y administrar desde la terminal
RUN curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp
ENV WP_CLI_CACHE_DIR=/tmp/wp-cli-cache

# Enlaces amigables (/producto/pantalla-iphone-13)
RUN a2enmod rewrite

# Tema y plugin dentro de la imagen (en Railway no hay carpetas montadas como en tu PC)
COPY wp-content/themes/mechanical-bull /opt/mb/themes/mechanical-bull
COPY wp-content/plugins/mb-core /opt/mb/plugins/mb-core

COPY docker/mb-entrypoint.sh /usr/local/bin/mb-entrypoint.sh
COPY docker/mb-arranque.sh /usr/local/bin/mb-arranque.sh
RUN chmod +x /usr/local/bin/mb-entrypoint.sh /usr/local/bin/mb-arranque.sh

ENTRYPOINT ["mb-entrypoint.sh"]
CMD ["apache2-foreground"]
