# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Mechanical Bull: a WooCommerce store for phone/laptop/PC repair parts plus a repair shop, located in San Miguel, El Salvador (USD, 13 % IVA already included in prices). It runs as WordPress + WooCommerce in Docker. All user-facing text, code comments, docs and commit messages are in **Spanish**, so keep that convention.

This repo does **not** contain WordPress core, WooCommerce, Astra or Elementor. They are installed at runtime through WP-CLI. Only two pieces of custom code are versioned:

- `wp-content/plugins/mb-core`: all business logic, plus the installer.
- `wp-content/themes/mechanical-bull`: an Astra child theme (styles, branded footer, floating WhatsApp button, shop search bar).

## Commands

There are no tests, linters or build steps. Development happens against the running containers.

```bash
cp .env.example .env && ./scripts/setup.sh          # full install (idempotent): build, WP install, plugins, wp mb instalar/demo/portada
docker compose up -d                                 # start (store :8080, phpMyAdmin :8081, Mailpit :8025)
docker compose exec -u www-data wordpress wp mb instalar            # re-apply store config (safe to repeat)
docker compose exec -u www-data wordpress wp mb instalar --rehacer  # also rewrite pages/menu/category images from contenido/*.html
docker compose exec -u www-data wordpress wp mb portada [--forzar] [--exportar=archivo.json]  # rebuild the Elementor home page
docker compose exec -u www-data wordpress wp mb demo [--borrar]     # create/remove the 8 demo products
docker compose logs -f wordpress
./scripts/backup.sh                                  # DB + uploads → backups/
```

`docker compose down -v` deletes the database and all products.

During local development the theme and plugin are **bind-mounted** into the container, so PHP/CSS edits show up right away with no rebuild. Content in `mb-core/contenido/*.html` reaches the database only after you run `wp mb instalar --rehacer`. On a server that is already installed, bump that page's number in `MB_CLI::VERSION_PAGINAS`; the next `wp mb instalar` (which runs on every Railway deploy) then rewrites only that page, once. The Elementor home page is generated in PHP (`includes/elementor.php`), and changes to it reach the database only after you run `wp mb portada --forzar`.

## Architecture

**The installer is the source of truth for configuration.** `includes/cli.php` (`MB_CLI`, `wp mb instalar`) sets everything up from code: store options, taxes, shipping zones (San Miguel vs the rest of the country), payment gateways, catalog terms, pages, menu, logos and header. It must stay idempotent, because three entry points call it on every deploy:
- `scripts/setup.sh` for local/VPS installs, run from the host through `docker compose exec`.
- `docker/mb-arranque.sh` for Railway, run inside the container in the background when `MB_AUTO_INSTALL=1`. It mirrors `setup.sh`, so a change to one usually needs the same change in the other.
- `scripts/actualizar.sh` for VPS updates (backup, git pull, rebuild, `wp mb instalar`).

If you add a new setting, put it in the matching `MB_CLI` private step rather than documenting a manual admin step.

**mb-core modules** (`mb-core.php` loads each one from `includes/`):
- `negocio.php`: business contact data (`mb_dato()`, stored as `mb_*` options with code defaults), edited in Appearance → Customize → *Mechanical Bull*. It also holds the WhatsApp helpers (`mb_whatsapp_url()`, `mb_telefono_legible()`) and the shortcodes (`[mb_whatsapp]`, `[mb_telefono]`, `[mb_mapa]`, `[mb_buscador]`, `[mb_marcas]`, `[mb_topbar_whatsapp]`…). Any phone or WhatsApp number shown anywhere comes from `mb_dato('whatsapp')`.
- `taxonomies.php`: `mb_compatible` (brand → model hierarchy, also used by search) and the part-quality taxonomy.
- `product-fields.php`: per-product warranty and "por encargo" (special-order) fields.
- `facturacion.php`: checkout choice between Factura and Crédito Fiscal (CCF needs NIT, NRC, razón social, giro).
- `pagos.php`: Chivo Wallet gateway and the "pay at pickup" option, which is allowed only with local pickup.
- `roles.php`: Secretaria/Vendedor, Técnico, Cliente técnico, Cliente mayorista. Roles are re-applied when `MB_CORE_VERSION` changes, so bump the version after you edit roles.
- `correo.php`: outgoing mail. It uses SMTP through the `MB_SMTP_*` constants (Mailpit locally, real SMTP on a VPS) or the Resend API on Railway.
- `elementor.php`: the `wp mb portada` command, which builds the home page as Elementor JSON.

**Theme** (`functions.php`): it hooks Astra/WooCommerce (`astra_footer_before`, `wp_footer`, `woocommerce_before_shop_loop`) and calls mb-core functions behind `function_exists` guards. Brand images live in `assets/`. `tools/generar-imagenes.py` regenerates the category, demo-product and placeholder images.

## Deployment targets

- **Local:** `docker-compose.yml` (WordPress, MariaDB, phpMyAdmin, Mailpit).
- **VPS:** add `docker-compose.prod.yml` through `COMPOSE_FILE` in `.env`. This adds Caddy HTTPS (`config/Caddyfile`), real SMTP and no Mailpit. See `docs/DESPLIEGUE.md`.
- **Railway:** builds from the `Dockerfile`. There are no bind mounts, so the image copies the theme and plugin to `/opt/mb/`, and `docker/mb-entrypoint.sh` syncs them into the volume when `MB_SYNC_CODE=1`. The entrypoint also rewrites the Apache port to `$PORT` and forces `mpm_prefork`, because loading two MPMs causes error AH00534. See `docs/RAILWAY.md`.

Business rules are documented in `docs/fase-0-reglas-de-negocio.md`. The README's "Fases" table lists what is done and what is still pending (Wompi/PayPal, repair orders, B2B pricing, DTE e-invoicing).
