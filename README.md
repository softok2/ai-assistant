# AI Assistant — Consultor Ejecutivo del Club

Asistente de IA para los clubes de Softok2. Se embebe por **iframe** dentro del
panel de administración de cada club (ccm, vallealto, terralta, herradura) mediante
un enlace firmado, y ofrece:

- **Chat conversacional** estilo ChatGPT sobre los documentos del club (RAG), con
  streaming, adjuntos, dictado por voz, búsqueda web, gráficas, lectura en voz alta,
  editar/regenerar, compartir y biblioteca de documentos.
- **Reporte ejecutivo automático**: la IA genera un PDF profesional con el análisis
  por módulos + gráficas y lo envía por correo al director según la frecuencia
  configurada.

## Stack

- **Backend**: Laravel 12 · PHP 8.3 · [Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk)
  (OpenAI **Responses API**, File Search sobre vector stores)
- **Frontend**: Vue 3 · Inertia.js 2 · TailwindCSS 4 · shadcn-vue
- **PDF**: spatie/laravel-pdf + Browsershot (Chromium) · **Markdown**: league/commonmark
- **Auth**: enlace externo firmado (HMAC) — sin login propio; la sesión la abre el club

## Arquitectura de autenticación (importante)

Esta app **no tiene login**. El panel de cada club genera un enlace firmado
(`club`, `user_id`, `user_name`, `role`, `issued_at`, `sig`) donde `sig` es un
HMAC-SHA256 de todo el payload con el secret compartido del club. El middleware
`auth.external` valida la firma y su vigencia (5 min), identifica al usuario por
`club + user_id`, y abre la sesión. Como se sirve dentro de un iframe cross-site,
**las cookies deben ser `Secure` + `SameSite=None`** y el club debe estar listado en
`FRAME_ANCESTORS` (CSP).

---

## Variables de entorno

Copia el ejemplo y complétalo:

```bash
cp .env.example .env
php artisan key:generate
```

### 🔑 IA (OpenAI vía Laravel AI SDK)

| Variable | Requerida | Default | Descripción |
|----------|:---------:|---------|-------------|
| `OPENAI_API_KEY` | ✅ | — | API key de OpenAI. Necesita crédito activo. |
| `OPENAI_MODEL` | — | `gpt-4.1-mini` | Modelo por defecto para chat y reportes. |
| `OPENAI_VECTOR_STORE_ID` | ✅ | — | Vector store con los documentos del club (RAG). El chat y el reporte hacen File Search aquí. |

> `OPENAI_ASSISTANT_ID` / `OPENAI_BASE_URL` / `OPENAI_ORGANIZATION` son del antiguo
> Assistants API (deprecado) y **ya no se usan** — pueden eliminarse.

### 🔐 Autenticación externa (enlace firmado desde el club)

| Variable | Requerida | Descripción |
|----------|:---------:|-------------|
| `CCM_SIGNATURE_SECRET` | ✅ | Secret HMAC **compartido con el panel del club**. Debe ser idéntico en ambos lados (en ccm es `SOFTOK2_AI_CHAT_SECRET`). Usa 64 caracteres aleatorios: `openssl rand -hex 32`. Rótalo en ambos lados a la vez. |
| `FRAME_ANCESTORS` | ✅ (prod) | Orígenes permitidos a embeber la app, separados por espacio. Ej: `https://ccm-admin-api.test https://admin.campestremty.com`. Vacío = solo `'self'`. |

> Para agregar más clubes, añade su secret en `config/app.php →
> club_signature_secrets` (una entrada por club).

### 🍪 Sesión (obligatorio para el iframe cross-site)

| Variable | Prod | Local (http) | Descripción |
|----------|------|--------------|-------------|
| `SESSION_SECURE_COOKIE` | `true` | `false` | En prod la cookie debe ser Secure (iframe cross-site sobre https). |
| `SESSION_SAME_SITE` | `none` | `lax` | `none` permite la cookie dentro del iframe de otro dominio. Requiere `Secure=true`, por eso en local http se relaja a `lax`. |
| `SESSION_DRIVER` | `database` | | Driver de sesión. |

> **Local por http**: si pruebas sin TLS, usa `SESSION_SECURE_COOKIE=false` +
> `SESSION_SAME_SITE=lax`. Lo ideal es asegurar el sitio con TLS
> (`herd secure`) y dejar los valores de producción.

### 📄 Reporte ejecutivo en PDF (Browsershot)

| Variable | Requerida | Descripción |
|----------|:---------:|-------------|
| `BROWSERSHOT_CHROME_PATH` | ✅ | Ruta al binario de Chrome/Chromium que Browsershot usa para renderizar el PDF. Además, **Node debe estar en el PATH**. Local (puppeteer cache): `~/.cache/puppeteer/chrome/<ver>/chrome-mac-*/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`. Prod: instala Chromium y apunta a su binario. |

### 📥 Ingesta de documentos (Pentaho / Softok2 MDS)

El comando `assistant-files:sync` descarga los `.md` del club y los indexa en el
vector store. Corre cada 2 horas (ver `bootstrap/app.php`).

| Variable | Descripción |
|----------|-------------|
| `SOFTOK2MDS_BASE_URL` | Endpoint del servicio de datos. |
| `SOFTOK2MDS_USERNAME` / `SOFTOK2MDS_PASSWORD` | Credenciales. |
| `SOFTOK2MDS_PROJECTS` | Proyectos/clubes a sincronizar, separados por coma. Ej: `ccm`. |
| `SOFTOK2MDS_FILES` | Archivos a traer, separados por coma. Ej: `golf-output`. |

### ✉️ Correo (envío del reporte)

Usa las variables estándar de Laravel: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. En local
`MAIL_MAILER=log` escribe los correos al log en vez de enviarlos.

### ⚙️ App / base de datos

`APP_URL` debe usar **https** cuando el sitio esté asegurado (necesario para que el
micrófono y las cookies Secure funcionen dentro del iframe). Base de datos y demás:
variables estándar de Laravel (`DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, etc.).

---

## Instalación

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate

npm run build          # o: composer run dev (dev con hot reload)
```

Requisitos: **PHP 8.3+**, **Composer 2**, **Node 18+**, **Chromium** (para el PDF),
y una cola corriendo (`php artisan queue:work`) para los jobs de ingesta y títulos.

## Reporte ejecutivo automático

- Se configura por club desde la pantalla **Reportes** del sidebar (visible solo para
  usuarios con rol `admin`): destinatarios, frecuencia (semanal/quincenal/mensual),
  día/hora, activar/desactivar y **enviar prueba ahora**.
- El scheduler evalúa cada hora qué clubes tienen envío pendiente. Para forzar un
  envío manual:

```bash
php artisan reports:send-due --club=ccm
```

## Despliegue (checklist)

1. `php artisan migrate`
2. Configurar en `.env`: `OPENAI_*`, `CCM_SIGNATURE_SECRET` (rotado y **sincronizado
   con el club**), `FRAME_ANCESTORS`, `SESSION_SECURE_COOKIE=true`,
   `SESSION_SAME_SITE=none`, `BROWSERSHOT_CHROME_PATH`, mailer real.
3. Instalar Chromium + Node en el servidor (para Browsershot).
4. `npm run build`
5. Correr `php artisan schedule:work` (o cron) y `php artisan queue:work`.
6. Sembrar/activar el `ReportSetting` del club (o dejar que el director lo configure
   desde la pantalla Reportes).

## Seguridad

El secret de firma (`CCM_SIGNATURE_SECRET`) es la única barrera de acceso: mantenlo
fuera del control de versiones, usa 64+ caracteres aleatorios y rótalo periódicamente
(en ambos lados a la vez). Nunca sirvas la app fuera de un iframe de un dominio
listado en `FRAME_ANCESTORS`.

## Licencia

MIT.
