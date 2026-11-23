# Publicar Mechanical Bull en internet (GitHub + VPS)

Resumen: el código vive en **GitHub**, la tienda corre en un **VPS** con Docker,
y **Caddy** pone el candado HTTPS automáticamente.

```
Tu PC  --git push-->  GitHub  --git pull-->  VPS (Docker: Caddy + WordPress + MariaDB)  <-- clientes
```

---

## Paso 1 — Subir el código a GitHub (desde tu PC)

1. Crea una cuenta en https://github.com (si no tienes).
2. Crea un repositorio nuevo: botón **New** → nombre `mechanical-bull` → **Private** →
   **no** marques README ni .gitignore → **Create repository**.
3. En tu PC, dentro de la carpeta `mechanical-bull`, pon tu nombre y correo en git (una sola vez):

```bash
git config user.name "Tu Nombre"
```

```bash
git config user.email "tu-correo@ejemplo.com"
```

4. Conecta y sube:

```bash
git remote add origin https://github.com/TU-USUARIO/mechanical-bull.git
```

```bash
git push -u origin main
```

> El archivo `.env` (contraseñas) **no se sube**: está en `.gitignore`. Compruébalo con `git status`.

---

## Paso 2 — Contratar el VPS

Recomendado: **Hetzner Cloud** (https://www.hetzner.com/cloud) → plan **CX22** (2 vCPU, 4 GB RAM, ~5 $/mes).
Alternativa: DigitalOcean (Droplet 2 GB).

- Sistema: **Ubuntu 24.04**
- Ubicación: la más cercana a El Salvador (Hetzner: Ashburn o Hillsboro, EE. UU.)
- Acceso: agrega tu llave SSH (o usa la contraseña de root que te envían por correo)

Anota la **IP pública** del servidor (ej. `203.0.113.10`).

---

## Paso 3 — Apuntar tu dominio al VPS

En el panel donde compraste el dominio (Namecheap, GoDaddy, Cloudflare...), en **DNS** crea:

| Tipo | Nombre | Valor |
|---|---|---|
| A | `@` | IP del VPS |
| A | `www` | IP del VPS |

Tarda de 5 minutos a unas horas. Compruébalo con `ping tudominio.com` (debe responder la IP del VPS).

> ¿Aún no tienes dominio? Cómpralo en Namecheap o Cloudflare (~10 $/año).
> Sin dominio no hay HTTPS, y sin HTTPS no conviene abrir una tienda.

---

## Paso 4 — Preparar el servidor (una sola vez)

Conéctate desde la terminal de tu PC (en Windows: PowerShell):

```bash
ssh root@IP-DEL-VPS
```

Instala Docker:

```bash
curl -fsSL https://get.docker.com | sh
```

Firewall: solo SSH, HTTP y HTTPS:

```bash
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable
```

---

## Paso 5 — Descargar el proyecto en el servidor

Como el repositorio es privado, el servidor necesita permiso de lectura (una "deploy key"):

```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519
```

```bash
cat ~/.ssh/id_ed25519.pub
```

Copia esa línea y pégala en GitHub: tu repositorio → **Settings → Deploy keys → Add deploy key**
(no marques "Allow write access"). Después:

```bash
git clone git@github.com:TU-USUARIO/mechanical-bull.git /opt/mechanical-bull
```

```bash
cd /opt/mechanical-bull
```

---

## Paso 6 — Configurar el `.env` del servidor

```bash
cp .env.example .env
```

```bash
nano .env
```

Cambia:

- **Todas las contraseñas** (solo letras y números, distintas a las de tu PC).
- `SITE_URL=https://tudominio.com`
- `DEMO=0` (sin productos de ejemplo), o `1` si quieres verlos al principio.
- `ADMIN_EMAIL=` tu correo real.
- Quita el `#` de estas líneas y complétalas:
  - `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml`
  - `DOMAIN=tudominio.com`
  - Las de `SMTP_...` (ver Paso 8; puedes dejarlo para después).

Guarda con `Ctrl+O`, `Enter`, y sal con `Ctrl+X`.

---

## Paso 7 — Instalar

```bash
chmod +x scripts/*.sh
```

```bash
./scripts/setup.sh
```

Al terminar entra a **https://tudominio.com/wp-admin**. La tienda sigue en modo "Próximamente":
cuando esté lista, quítalo en *WooCommerce → Ajustes → Visibilidad del sitio*.

---

## Paso 8 — Correos reales (pedidos, contraseñas)

Opción sencilla y gratis: **Brevo** (https://www.brevo.com, 300 correos/día).

1. Crea la cuenta y verifica tu dominio (*Senders, Domains → Add domain*; te da registros DNS para pegar).
2. En *SMTP & API* copia servidor, puerto, usuario y clave.
3. Ponlos en el `.env` del servidor (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM`).
4. Aplica:

```bash
docker compose up -d
```

---

## Día a día: publicar mejoras

En tu PC (trabajas y pruebas en local como siempre, en `http://localhost:8080`):

```bash
git add -A && git commit -m "Describe el cambio"
```

```bash
git push
```

En el servidor:

```bash
cd /opt/mechanical-bull && ./scripts/actualizar.sh
```

(Hace respaldo, descarga los cambios, reconstruye y vuelve a aplicar la configuración.)

> `actualizar.sh` usa `wp mb instalar` **sin** `--rehacer`, así no pisa las páginas que hayas editado
> en el panel. Si un cambio toca las páginas de Mechanical Bull, ejecuta a mano
> `docker compose exec -u www-data wordpress wp mb instalar --rehacer`.

---

## phpMyAdmin en el servidor

Por seguridad no está abierto a internet. Desde tu PC abre un túnel:

```bash
ssh -L 8081:localhost:8081 root@IP-DEL-VPS
```

y entra a http://localhost:8081 mientras esa ventana siga abierta.

---

## Respaldos

```bash
cd /opt/mechanical-bull && ./scripts/backup.sh
```

Automático cada noche a las 3 a. m. (ejecuta `crontab -e` y agrega esta línea):

```
0 3 * * * cd /opt/mechanical-bull && ./scripts/backup.sh >/dev/null 2>&1
```

Copia de vez en cuando la carpeta `backups/` a tu PC (un respaldo dentro del mismo servidor no sirve si el servidor se pierde):

```bash
scp -r root@IP-DEL-VPS:/opt/mechanical-bull/backups ./backups-servidor
```

---

## Si algo falla

| Problema | Qué revisar |
|---|---|
| No carga el sitio / sin candado | `docker compose logs caddy`: normalmente el DNS aún no apunta a la IP |
| Error de base de datos | `docker compose logs db wordpress` |
| No llegan correos | Datos SMTP en `.env` y dominio verificado en Brevo |
| Ver todo | `docker compose ps` |
