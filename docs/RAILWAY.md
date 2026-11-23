# Publicar Mechanical Bull en Railway

Railway construye la tienda con el `Dockerfile` del proyecto cada vez que haces `git push`.
No hay servidor que mantener: Railway da el HTTPS, el dominio, la base de datos y los reinicios.

```
Tu PC --git push--> GitHub --(automático)--> Railway: WordPress + MySQL + volumen  <-- clientes
```

**Costo:** el plan Hobby cuesta 5 $/mes e incluye 5 $ de consumo. Una tienda pequeña
(WordPress + MySQL) suele quedar entre 5 y 10 $/mes. Revisa el consumo en *Usage*.

---

## Paso 1 — Subir el código a GitHub (desde tu PC)

1. Crea un repositorio **privado** en GitHub llamado `mechanical-bull` (sin README ni .gitignore).
2. En tu PC, dentro de la carpeta del proyecto:

```bash
git config user.name "Tu Nombre"
```

```bash
git config user.email "tu-correo@ejemplo.com"
```

```bash
git remote add origin https://github.com/TU-USUARIO/mechanical-bull.git
```

```bash
git push -u origin main
```

> Tu `.env` no se sube (está en `.gitignore`). En Railway las contraseñas van en *Variables*.

---

## Paso 2 — Crear el proyecto en Railway

1. Entra a https://railway.com e inicia sesión **con tu cuenta de GitHub**.
2. **New Project → Deploy from GitHub repo → mechanical-bull**.
   (Si pide permiso para ver tus repositorios, dáselo a `mechanical-bull`.)
3. Va a empezar a construir. Si el primer despliegue falla, no pasa nada: aún faltan los pasos siguientes.

## Paso 3 — Agregar la base de datos

En el proyecto: **+ Create → Database → Add MySQL**.

> Si Railway le pone otro nombre que no sea `MySQL`, cambia `MySQL` por ese nombre en las variables del Paso 6.

## Paso 4 — Dirección pública

Abre el servicio **mechanical-bull** → **Settings → Networking → Generate Domain**.
Si pregunta el puerto, escribe **80**. Te da algo como `mechanical-bull-production.up.railway.app`.

## Paso 5 — Volumen (para que no se pierdan fotos ni plugins)

Sobre el servicio **mechanical-bull**: clic derecho (o `⌘K` / `Ctrl+K`) → **Attach Volume**.
Ruta de montaje (*Mount path*):

```
/var/www/html/wp-content
```

## Paso 6 — Variables

Servicio **mechanical-bull** → **Variables → Raw Editor**, pega esto y **cambia la contraseña y el correo**:

```
PORT=80
WORDPRESS_DB_HOST=${{MySQL.MYSQLHOST}}:${{MySQL.MYSQLPORT}}
WORDPRESS_DB_NAME=${{MySQL.MYSQLDATABASE}}
WORDPRESS_DB_USER=${{MySQL.MYSQLUSER}}
WORDPRESS_DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
SITE_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
SITE_TITLE=Mechanical Bull
ADMIN_USER=mb_dueno
ADMIN_PASSWORD=CambiaEstaClaveLarga2026
ADMIN_EMAIL=tu-correo@ejemplo.com
DEMO=1
MB_SYNC_CODE=1
MB_AUTO_INSTALL=1
```

| Variable | Para qué sirve |
|---|---|
| `WORDPRESS_DB_*` | Conectan WordPress con la base MySQL de Railway (se llenan solas) |
| `SITE_URL` | Dirección de la tienda. Cámbiala si pones tu propio dominio |
| `ADMIN_*` | Tu usuario de administrador (solo se usa al instalar la primera vez) |
| `DEMO` | `1` crea 8 productos de ejemplo la primera vez; `0` no |
| `MB_SYNC_CODE` | Copia tu tema y plugin de cada versión al volumen |
| `MB_AUTO_INSTALL` | Instala la tienda sola la primera vez y aplica la configuración en cada despliegue |

Pulsa **Deploy** (o *Apply changes*).

## Paso 7 — Ver que se instaló

Servicio → **Deployments → View logs**. La primera vez tarda unos minutos: descarga WooCommerce,
Astra, Elementor y el idioma. Cuando termina verás:

```
[mb-arranque] Listo: https://....up.railway.app
```

Entra a `https://TU-DIRECCION.up.railway.app/wp-admin` con `ADMIN_USER` / `ADMIN_PASSWORD`.

La tienda queda en modo **"Próximamente"** (solo tú la ves con sesión iniciada).
Cuando esté lista: *WooCommerce → Ajustes → Visibilidad del sitio → En vivo*.

---

## Correos (pedidos, códigos de verificación, recuperar contraseña)

Railway **bloquea el SMTP** en los planes Free, Trial y Hobby, así que los correos se envían por una
API HTTPS: **Brevo** (sin dominio propio) o **Resend** (con dominio propio). El plugin `mb-core` trae
los dos; si están las dos claves, se usa Resend.

Los códigos para verificar el correo de las cuentas nuevas también salen por aquí: **sin uno de los
dos, los clientes no reciben el código y no pueden terminar su registro.**

### Opción A: Brevo con un Gmail (gratis, sin dominio)

1. Crea una cuenta en https://www.brevo.com (gratis: 300 correos al día).
2. **Remitentes, dominios e IP dedicadas → Remitentes → Añadir un remitente**: pon el Gmail de la
   tienda y confírmalo con el enlace que te llega a ese Gmail.
3. **SMTP y API → Claves API → Generar una nueva clave API** (empieza con `xkeysib-`).
4. En Railway, Variables del servicio, agrega:

```
BREVO_API_KEY=xkeysib-xxxxxxxxxxxx
MAIL_FROM=Mechanical Bull <tucorreo@gmail.com>
```

`MAIL_FROM` tiene que ser **exactamente** el remitente que verificaste en Brevo. Brevo revisa las
cuentas nuevas: si los primeros correos no salen, mira en su panel si la cuenta está activada.
Con un remitente Gmail algunos correos pueden caer en *Spam* al principio.

### Opción B: Resend con dominio propio (lo más confiable)

1. Crea una cuenta en https://resend.com (gratis: 3 000 correos/mes).
2. **Domains → Add Domain** → tu dominio → copia los registros DNS que te da en el panel de tu dominio.
   (Sin dominio propio solo puedes enviarte pruebas a ti mismo.)
3. **API Keys → Create API Key**.
4. En Railway, Variables del servicio, agrega:

```
RESEND_API_KEY=re_xxxxxxxxxxxx
MAIL_FROM=tienda@tudominio.com
```

Si un correo no sale, en los *Deploy Logs* aparece `Mechanical Bull: Brevo no pudo enviar el correo: ...`
(o `Resend ...`) con el motivo.

---

## Pago con tarjeta (Wompi El Salvador)

La tienda trae su propia integración con Wompi (no hace falta instalar ningún plugin). El cliente
elige *Tarjeta de crédito o débito (Wompi)*, paga en la página segura de Wompi y regresa a la tienda.
El pedido pasa a **Procesando** solo cuando Wompi confirma el pago.

1. Crea tu cuenta de negocio en https://wompi.sv y entra al panel: https://panel.wompi.sv
2. **Aplicativos** → crea uno (o abre el que tengas) y copia el **App ID** y el **API Secret**.
3. En Railway, Variables del servicio, agrega:

```
WOMPI_APP_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
WOMPI_API_SECRET=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

   (También se pueden escribir en *WooCommerce → Ajustes → Pagos → Wompi (tarjeta)*; las variables
   tienen prioridad.) Sin credenciales, Wompi no aparece en el pago.

No hay que configurar el webhook en Wompi: cada enlace de pago lleva la dirección de la tienda.

**Pruebas:** pon el negocio en **modo desarrollo** en el panel de Wompi. Los pagos se aprueban sin
cobrar (con CVV `111` se simula un rechazo) y el pedido queda **En espera** con la nota
"pago de PRUEBA". Cuando todo funcione, pasa el negocio a producción.

Si algo falla, en los *Deploy Logs* aparecen líneas que empiezan con `[mb-wompi]`, y cada pedido
tiene notas con lo que respondió Wompi. Los reembolsos se hacen desde el panel de Wompi.

---

## Verificar el teléfono de los clientes (SMS o WhatsApp, opcional)

Al registrarse, el cliente siempre verifica su **correo** con un código de 6 dígitos. Para que también
verifique su **teléfono**, el código se envía con **Twilio Verify** (cobra por verificación; revisa
el precio para El Salvador en twilio.com/verify/pricing).

1. Crea una cuenta en https://www.twilio.com y agrega saldo.
2. **Verify → Services → Create new** (nombre: Mechanical Bull). Activa el canal **SMS**
   (o **WhatsApp**, que necesita un remitente de WhatsApp aprobado en Twilio).
3. Copia el **Account SID** y el **Auth Token** (página principal de la consola) y el **Service SID**
   del servicio de Verify (empieza con `VA`).
4. En Railway, Variables del servicio, agrega:

```
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxx
TWILIO_VERIFY_SID=VAxxxxxxxxxxxxxxxx
TWILIO_CANAL=sms
```

`TWILIO_CANAL` puede ser `sms` o `whatsapp`. Sin estas variables solo se verifica el correo y el
teléfono queda guardado como "sin verificar". En **Usuarios** del panel, la columna *Verificación*
muestra qué tiene verificado cada cliente.

---

## Tu propio dominio (ej. mechanicalbull.com)

1. Servicio → **Settings → Networking → + Custom Domain** → escribe tu dominio.
2. Railway te muestra un registro **CNAME** (y a veces un TXT): créalo en el DNS de tu dominio.
   Para el dominio raíz (`@`) usa Cloudflare como DNS, que acepta CNAME en la raíz.
3. Cambia la variable `SITE_URL=https://mechanicalbull.com` y redespliega.
   El arranque actualiza la dirección de WordPress solo.

---

## Día a día: publicar mejoras

Trabajas y pruebas en tu PC como siempre (`./scripts/setup.sh`, `http://localhost:8080`). Para publicar:

```bash
git add -A && git commit -m "Describe el cambio"
```

```bash
git push
```

Railway construye y publica solo. En cada despliegue se copia el tema y el plugin nuevos y se ejecuta
`wp mb instalar` (sin `--rehacer`, así no pisa las páginas que editaste en el panel).

**Ejecutar comandos de WP-CLI en Railway** (por ejemplo `--rehacer` o borrar los productos demo):
instala la CLI de Railway (https://docs.railway.com/guides/cli), y en la carpeta del proyecto:

```bash
railway link
```

```bash
railway ssh
```

Ya dentro:

```bash
su -s /bin/bash www-data -c "wp mb demo --borrar"
```

---

## Respaldos

- **Base de datos y volumen:** en Railway, abre el servicio MySQL y el volumen → **Backups**
  y activa los respaldos automáticos (diarios/semanales).
- **Copia en tu PC:** desde la consola de Railway (`railway ssh` en el servicio mechanical-bull):

```bash
su -s /bin/bash www-data -c "wp db export /tmp/respaldo.sql" && ls -lh /tmp/respaldo.sql
```

---

## Si algo falla

| Problema | Qué revisar |
|---|---|
| `Application failed to respond` | Que `PORT=80` esté en Variables y el dominio apunte al puerto 80 |
| Log: `no hay conexión con la base de datos` | Que el servicio MySQL se llame `MySQL` (o ajusta las variables) |
| Log: `falta la variable ...` | Agrega esa variable en el Paso 6 |
| Se pierden fotos/plugins al redesplegar | Falta el volumen en `/var/www/html/wp-content` |
| Wompi no aparece en el pago | `WOMPI_APP_ID` y `WOMPI_API_SECRET`, y la moneda de la tienda en USD |
| No llegan correos | `BREVO_API_KEY` o `RESEND_API_KEY`, y que `MAIL_FROM` esté verificado en ese proveedor; busca `no pudo enviar el correo` en los logs |
| La página carga sin estilos o redirige mal | `SITE_URL` debe ser exactamente tu dirección con `https://` |
