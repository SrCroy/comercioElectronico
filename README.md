# Mechanical Bull — Tienda de repuestos y taller

Tienda en línea de repuestos (celulares, laptops, PC/Gamer) con la marca Mechanical Bull.
WordPress + WooCommerce en Docker, configurado para El Salvador (San Miguel, USD, IVA 13 %).

> **Publicar en internet:** en Railway ver [docs/RAILWAY.md](docs/RAILWAY.md) · en un VPS propio ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

## Instalación

Requisitos: Docker con Docker Compose y conexión a internet.

**1. Borra la instalación anterior** (si hiciste una a mano). Entra a la carpeta donde estaba tu `docker-compose.yml` viejo y ejecuta:

```bash
docker compose down -v
```

**2. Copia esta carpeta `mechanical-bull` a tu servidor y entra en ella.**

**3. Crea tu archivo de configuración:**

```bash
cp .env.example .env
```

Abre `.env` y cambia las contraseñas (solo letras y números).

**4. Instala todo:**

```bash
chmod +x scripts/*.sh
```

```bash
./scripts/setup.sh
```

Tarda de 3 a 6 minutos la primera vez.

| Servicio | Dirección |
|---|---|
| Tienda | http://localhost:8080 |
| Administración | http://localhost:8080/wp-admin |
| phpMyAdmin | http://localhost:8081 |
| Correos de prueba | http://localhost:8025 |

## Actualizar una instalación existente

Si ya tienes la tienda instalada y recibes una versión nueva de esta carpeta:

1. Copia los archivos nuevos encima de los tuyos (**conserva tu `.env`**).
2. Aplica los cambios:

```bash
docker compose up -d
```

```bash
docker compose exec -u www-data wordpress wp mb instalar --rehacer
```

```bash
docker compose exec -u www-data wordpress wp mb demo
```

`--rehacer` vuelve a escribir las páginas de Mechanical Bull (Inicio, Reparaciones, Garantías, Envíos, Contacto), el menú y las imágenes de categorías. Si editaste esas páginas a mano, esos cambios se reemplazan. Tus productos, pedidos y ajustes no se tocan.

## Qué deja configurado

| Área | Configuración |
|---|---|
| Tienda | Km 144, Carretera al Cuco, Cantón El Jute, San Miguel · Dólar · IVA 13 % incluido en precios · Departamento y municipio en direcciones |
| Marca | Logo, ícono, colores negro/rojo, franja superior, íconos de buscar/cuenta/carrito, pie de página con contacto, botón flotante de WhatsApp |
| Portada | Buscador por modelo, beneficios, categorías con ícono, marcas, novedades, sección del taller, llamado a WhatsApp |
| Páginas | Inicio, Tienda, Reparaciones, Garantías, Envíos y devoluciones, Contacto (con mapa), Carrito, Pago, Mi cuenta |
| Catálogo | Categorías (Celulares, Laptops, PC y Gamer, Herramientas) con imagen · Marcas y modelos compatibles · Calidad del repuesto · Garantía y "por encargo" en cada producto |
| Envíos | San Miguel: retiro gratis o domicilio $2.50 · Resto del país: retiro gratis o courier $5.00 |
| Pagos | Transferencia bancaria · Chivo Wallet · Pago al retirar en tienda (solo con retiro) |
| Facturación | En el pago se elige Factura o Crédito Fiscal (pide NIT, NRC, razón social y giro) |
| Roles | Secretaria / Vendedor · Técnico · Cliente técnico · Cliente mayorista |
| Correos | En tu PC se capturan en Mailpit (http://localhost:8025). En internet salen por Brevo o Resend (Railway) o SMTP (VPS) |

La tienda queda en modo **"Próximamente"**: solo la ves tú con sesión iniciada. Se quita en *WooCommerce → Ajustes → Visibilidad del sitio* cuando salga a internet.

## Lo que tienes que completar en el panel

1. **WhatsApp, horario y correo:** Apariencia → Personalizar → **Mechanical Bull**.
2. **Cuenta bancaria:** WooCommerce → Ajustes → Pagos → Transferencia bancaria (está como "CAMBIAR").
3. **Productos reales:** Productos → Añadir nuevo. Marca categoría, marcas/modelos compatibles, calidad, garantía y si es por encargo.
4. **Borra los productos de ejemplo** cuando ya no los necesites:

```bash
docker compose exec -u www-data wordpress wp mb demo --borrar
```

5. **Usuarios del equipo:** Usuarios → Añadir nuevo → rol *Secretaria / Vendedor* o *Técnico*.

## Comandos útiles

Volver a aplicar la configuración (no duplica nada):

```bash
docker compose exec -u www-data wordpress wp mb instalar
```

Respaldo de base de datos y fotos (queda en `backups/`):

```bash
./scripts/backup.sh
```

Ver errores:

```bash
docker compose logs -f wordpress
```

Apagar (los datos se conservan):

```bash
docker compose down
```

> ⚠️ `docker compose down -v` **borra la base de datos y todos los productos**.

## Estructura

| Carpeta | Contenido |
|---|---|
| `Dockerfile` | Imagen de WordPress 7 + PHP 8.3 + WP-CLI |
| `docker-compose.yml` | WordPress, MariaDB, phpMyAdmin, Mailpit |
| `docker-compose.prod.yml` | Ajustes del servidor público: HTTPS con Caddy, SMTP real, sin Mailpit |
| `config/Caddyfile` | Dominio y HTTPS |
| `scripts/actualizar.sh` | Actualiza el servidor desde GitHub |
| `docs/DESPLIEGUE.md` | Guía para publicar en un VPS |
| `railway.toml`, `docker/` | Configuración y arranque automático en Railway |
| `docs/RAILWAY.md` | Guía para publicar en Railway |
| `scripts/setup.sh` | Instalador completo |
| `wp-content/themes/mechanical-bull` | Tema hijo de Astra con la marca |
| `wp-content/plugins/mb-core` | Lógica de negocio (roles, compatibilidad, garantía, encargo, factura/CCF, Chivo, instalador) |
| `docs/fase-0-reglas-de-negocio.md` | Reglas de negocio |
| `tools/generar-imagenes.py` | Genera las imágenes de marca (categorías, productos de ejemplo, placeholder) |

## Fases

| Fase | Estado |
|---|---|
| 0 — Reglas de negocio | ✅ |
| 1 — Tienda: catálogo, pagos manuales, envíos, factura/CCF | ✅ |
| 1b — Wompi y PayPal (requiere credenciales) | ⏳ |
| 2 — Taller: órdenes de reparación y seguimiento | ⏳ |
| 3 — Precios B2B para técnicos y mayoristas | ⏳ |
| 4 — Taller ↔ inventario, garantías en "Mi cuenta", avisos WhatsApp | ⏳ |
| 5 — Facturación electrónica DTE, reportes, SEO | ⏳ |
