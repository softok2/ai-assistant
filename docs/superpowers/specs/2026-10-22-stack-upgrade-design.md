# Upgrade del stack: PHP 8.5, Laravel 13, Pest 5, Inertia 3, laravel/ai 1.x, Vite 8

Fecha: 2026-10-22. Repo: softok2/ai-assistant, rama base `stage`. Trabajo en
rama `upgrade/laravel-13-php-8.5` (nunca directo en `stage`: un push a `stage`
despliega solo).

## Objetivo

Poner el proyecto en las versiones estables actuales de todo su stack sin
cambiar comportamiento visible para el usuario. El frontend ya es Vue 3 +
Inertia; no hay migración de frontend, solo subida de versiones.

| Capa | Hoy (lock) | Destino |
|---|---|---|
| PHP (constraint) | `^8.3` | `^8.5` |
| laravel/framework | 12.33 | `^13.0` (13.38 hoy) |
| pestphp/pest + plugin-laravel | 3.8 / 3.2 | `^5.3` / `^5.0` |
| inertiajs/inertia-laravel | 2.0.10 | `^3.5` |
| @inertiajs/vue3 | 2.2.4 | `^3.9` |
| laravel/ai | 0.7.2 | `^1.3` |
| laravel/tinker | 2.10 | `^3.0` |
| laradumps/laradumps | 4.5 | `^5.4` |
| barryvdh/laravel-ide-helper | 3.6 | `^3.7` |
| vite / laravel-vite-plugin / @vitejs/plugin-vue | 6.3 / 1.3 / 5.2 | `^8.3` / `^3.2` / `^6.0` |
| tailwindcss + @tailwindcss/vite | 4.1 | `^4.3` |
| typescript / vue-tsc | 5.8 / 2.2 | `^6.0` / `^3.3` |
| eslint / @antfu/eslint-config / eslint-plugin-format | 9.36 / 4.19 / 1.0 | `^10.13` / `^9.5` / `^2.0` |
| @vueuse/core | 12.8 | `^14` (ver decisiones) |
| lucide-vue-next | 0.515 | `@lucide/vue ^1.61` (renombre) |
| motion-v | 1.7 | eliminado |
| softok2/telegram-notifications + canal telegram | 1.0.1 / 6.0 | eliminados |

El resto de dependencias (Octane, Telescope, Sanctum, spatie/laravel-pdf,
Ziggy, reka-ui, vue-sonner, @laravel/stream-vue, highlight.js…) ya admiten el
destino en su major actual: suben al último minor con el `update`.

## Por qué ahora

El `composer.lock` actual ni siquiera instala en PHP 8.5 (diactoros, nette/schema
y paratest capan a 8.4). Pest 3 no puede convivir con Laravel 13. Y
`laravel/ai` 0.7 es una pre-1.0 cuya API ya se estabilizó en 1.x. Cada mes que
pasa el salto es más grande.

## Decisiones del usuario (Franky)

1. Quitar `softok2/telegram-notifications`. Como el canal
   `laravel-notification-channels/telegram` solo lo usaba ese paquete, también
   se quita, junto con `config/telegram-notification.php`, el `use` y el
   `report()` de `bootstrap/app.php`, y los scripts `use-local`/`use-remote`.
   **Consecuencia que asumimos**: producción deja de avisar excepciones por
   Telegram. Ver "Preguntas abiertas".
2. Sesiones serializadas en `json` (`config/session.php`). Cierra la sesión de
   todos los usuarios activos al desplegar; aceptado.
3. Aceptar los nuevos modelos por defecto de OpenAI en `laravel/ai` 1.x: el
   "cheapest" pasa de `gpt-5.4-nano` a `gpt-6-luna` (afecta a
   `ChatTitleGenerator`, `ChatStarterSuggester`, `ChatFollowUpSuggester`). No se
   fija modelo en `config/ai.php`.
4. Recrear las tablas `agent_conversations*` con el esquema 1.3 (la app no las
   lee: `ClubAssistant` implementa `Conversational` con su propia tabla de
   chats). Una migración nueva hace `drop` + `create` con el esquema que publica
   el paquete 1.3.
5. (Asumido, pendiente de confirmar) El servidor de stage tendrá `php85` (SCL)
   instalado antes de mezclar. El workflow pasa a `scl enable php85`.

## Decisiones del diseñador (reversibles, pero tomadas)

- **TypeScript 6.0, no 7.** TS 7 no expone API JS: `vue-tsc` falla al arrancar
  y `typescript-eslint` lo excluye como peer (`<6.1`). El `tsconfig.json`
  actual ya cumple todas las deprecaciones de TS 6 (sin `baseUrl`,
  `moduleResolution: bundler`, `esModuleInterop: true`, `types` explícito), así
  que no cambia.
- **@vueuse/core 14.x, no 15.** `reka-ui` 2.11 depende de `^14.1`; con 15 van
  dos copias al bundle. Los cinco composables usados (`reactiveOmit`,
  `useStorage`, `useVModel`, `useMediaQuery`, `useEventListener`) existen en
  ambas sin deprecación.
- **npm como gestor.** Se mantiene `package-lock.json`, se borra `bun.lock`
  (desincronizado) y el workflow pasa de `yarn` a `npm ci && npm run build`.
  Los scripts `lint`/`lint:fix` dejan de usar `bunx oxlint@latest`: `oxlint` se
  instala como devDep fijado al minor que exige `eslint-plugin-oxlint`
  (`~1.89.0`) y se llama directo.
- **`PreventRequestForgery` en modo por defecto** (token como fallback). La app
  vive en un iframe cross-site (`FrameAncestors`, `SESSION_SAME_SITE=none`); el
  modo `originOnly` devolvería 403. Se actualiza la referencia en
  `config/sanctum.php` al nombre nuevo.
- **Prefijos de cache/redis/cookie se dejan como están** (estilo `_`), para no
  invalidar cache ni cookies además de las sesiones. Nuevas claves de config
  del skeleton 13 se añaden solo si son necesarias: `serializable_classes =>
  false` en cache, `serialization => 'json'` en session, `Pdo\Mysql::ATTR_SSL_CA`
  en database (PHP 8.5 deprecó `PDO::MYSQL_ATTR_SSL_CA`).
- **Inertia 3 con el root view mínimo**: `<title inertia>` → `<title
  data-inertia>`; no se publica `config/inertia.php` (los defaults 3.x ya
  apuntan a `resources/js/pages` en minúscula, que es la ruta real). Se añade
  `INERTIA_SSR_ENABLED=false` a `phpunit.xml`. No se adopta `@inertiajs/vite`
  ni Wayfinder en esta entrega.
- **SSR sigue apagado.** Aunque el repo tiene `ssr.ts` y `build:ssr`, el deploy
  de stage nunca compila el bundle SSR ni arranca `inertia:start-ssr`, así que
  hoy SSR no corre en ningún sitio. Se mantiene así: `INERTIA_SSR_ENABLED=false`
  explícito en el `.env` de stage (requisito de servidor), `app.ts` sigue con
  `createApp` (es lo que hace el starter kit oficial para el cliente), y
  `npm run build:ssr` queda en el gate solo como comprobación de que compila.
- **`axios` pasa a dependencia directa** (`^1.15`); lo importan tres
  componentes y Inertia 3 ya no lo arrastra.
- **`@lucide/vue`** reemplaza a `lucide-vue-next` (deprecado en npm). Renombres
  obligatorios: `Loader2` → `LoaderCircle` (6 archivos) y `MoreHorizontal` →
  `Ellipsis` (3 archivos). Los iconos pasan a `aria-hidden` por defecto: los
  botones solo-icono necesitan `aria-label` o texto `sr-only`; el constructor
  revisa los que toque, sin auditoría global.
- **`motion-v` se elimina** (cero componentes lo usan; solo el resolver en
  `vite.config.ts`).
- **Paquetes de lint no referenciados** por `eslint.config.mjs` se quitan de
  `package.json`: `eslint-plugin-vue`, `typescript-eslint`,
  `@vue/eslint-config-typescript`, `@eslint/js`, `eslint-config-prettier`.
  antfu 9 los trae por dentro.
- **`optionalDependencies`**: `@rollup/rollup-linux-x64-gnu` muere con Rolldown;
  lo reemplaza `@rolldown/binding-linux-x64-gnu`. Se mantienen
  `lightningcss-linux-x64-gnu` y `@tailwindcss/oxide-linux-x64-gnu`.
- **Node**: `"engines": {"node": ">=22.12"}` en `package.json`; `.nvmrc` sigue
  en 22. El runner de stage debe cumplirlo (requisito de servidor, ver abajo).
- **Rector**: `withPhpSets(php85: true)` y `rector-laravel ^2.7`. Se corre en
  `--dry-run` primero; los cambios que proponga se aplican solo si el gate sigue
  verde.

## Cambios de código conocidos (evidencia de la investigación)

Backend:
- `config/database.php:63,83`: `PDO::MYSQL_ATTR_SSL_CA` → `Pdo\Mysql::ATTR_SSL_CA`.
- `config/cache.php`: añadir `'serializable_classes' => false`.
- `config/session.php`: añadir `'serialization' => 'json'`.
- `config/sanctum.php:8,86`: `ValidateCsrfToken` → `PreventRequestForgery`.
- `bootstrap/app.php`: quitar `use Softok2\...` y el bloque `report()`; añadir
  `$exceptions->shouldRenderJsonWhen(fn ($r) => $r->is('api/*') || $r->expectsJson())`
  como en el skeleton 13 (hay rutas `api`).
- `phpunit.xml`: añadir `INERTIA_SSR_ENABLED=false`, `BROADCAST_CONNECTION=null`,
  `DB_URL=""`, `NIGHTWATCH_ENABLED=false`.
- `tests/Feature/Ai/StreamInsightsTest.php:26,27,50,66`: `ProviderToolEvent`
  gana un séptimo argumento `provider` (`'openai'`).
- Migración nueva que recrea `agent_conversations` y su tabla de mensajes con
  el esquema de `laravel/ai` 1.3 (`participant_type/participant_id`, `steps`,
  `status`, índices nuevos). La migración 0.7 publicada se deja tal cual (ya
  corrió en producción).
- Eliminar `config/telegram-notification.php`, `scripts/use-local.php`,
  `scripts/use-remote.php` y sus entradas en `composer.json`.
- Tests: ningún cambio de API de Pest; `uses(RefreshDatabase::class)` a nivel
  de archivo (12 archivos) es redundante con `tests/Pest.php` y se puede quitar,
  pero no es obligatorio.

Frontend:
- `resources/views/app.blade.php:33`: `<title inertia>` → `<title data-inertia>`.
- Renombres de iconos lucide (ver decisiones). Reemplazo del import en los ~45
  archivos que importan `lucide-vue-next`.
- `vite.config.ts`: quitar `MotionResolver` y su import. Nada más cambia (sin
  `build.*`, `rollupOptions`, `esbuild`).
- `eslint.config.mjs`: misma forma; correr `eslint --fix` para las reglas nuevas
  de perfectionist 5 y eslint-plugin-vue 10 y revisar el residuo a mano.
- `components.d.ts` lo genera `unplugin-vue-components`; añadirlo a `.gitignore`.
- Vigilar tras el build de Vite 8 los default-imports CJS (`highlight.js`,
  `vue-markdown-render`/markdown-it): si rompen, `legacy.inconsistentCjsInterop: true`
  como red, no como primera opción.

Deploy (`.github/workflows/deploy_ai_assistant_stage.yml`):
- `scl enable php83` → `scl enable php85` (4 ocurrencias).
- `yarn && yarn build` → `npm ci && npm run build`.
- Tras `optimize`, correr `php artisan view:clear` (la directiva `@inertia`
  cambió su salida) — o confiar en que `optimize` ya limpia vistas; el
  constructor lo verifica en el skeleton 13.

## Verificación (gate de cierre, completo)

Todo debe pasar en local antes de dar la entrega por cerrada:

1. `composer validate && composer install` en PHP 8.5 sin warnings de
   plataforma; `php artisan about` muestra Laravel 13.x y PHP 8.5.x.
2. `php artisan test` (Pest 5, los 45 archivos) en verde. Atención especial a
   `ChatHistoryTest:154` y `ChatSourcesPropsTest:44` (leen `deepMergeProps` /
   `deferredProps` del page object) y a los 27 `assertInertia`.
3. `npm ci && npm run build && npm run build:ssr` sin errores ni warnings de
   interop CJS.
4. `npx vue-tsc --noEmit` en verde con TS 6.
5. `npm run lint` en verde (oxlint + eslint 10).
6. `vendor/bin/pint --test` y `vendor/bin/rector --dry-run` sin cambios
   pendientes.
7. Humo manual con la app levantada (`composer dev`): login externo por enlace
   firmado, abrir un chat, enviar un mensaje con streaming y ver título +
   sugerencias generados, subir un documento a una fuente, descargar un PDF de
   mensaje. Lo hace el constructor con `agent-browser` o a mano y lo documenta.
8. `php artisan migrate:fresh --seed` limpio (el deploy de stage lo hace así) y
   además `php artisan migrate` sobre una BD que ya tenga las tablas 0.7 (lo que
   hará producción), para probar la migración de recreado.
9. **CSRF en iframe cross-site**: página anfitriona local en otro host `.test`
   de Herd (HTTPS, como exige `SameSite=None`) que embebe la app en un iframe y
   hace un POST (p. ej. enviar un mensaje). Debe pasar con
   `PreventRequestForgery` en modo por defecto. Franky lo vuelve a comprobar en
   stage desde los paneles reales (CCM, Vallealto) antes de que llegue a `main`.

## No objetivos

- Adoptar `@inertiajs/vite`, Wayfinder, `pages`/`withApp` nuevos de Inertia 3.
- Reescribir los tres `axios` a `useHttp` de Inertia.
- Migrar `Loader2`/iconos a otra librería, o auditar accesibilidad global.
- Cambiar prefijos de cache/cookie al estilo `-` del skeleton 13.
- Añadir tests de navegador (el proyecto no los tiene; no es esta entrega).
- Tocar comportamiento de producto, UI o prompts.

## Anti-objetivos (fallos aunque "compile")

- Que el iframe cross-site deje de poder hacer POST (403 por CSRF).
- Que el stream del asistente cambie de forma o pierda eventos
  (`useAssistantStream.ts` ignora los tipos nuevos; eso es lo esperado).
- Que el deploy de stage se rompa por Node o PHP del runner: los requisitos de
  servidor se comunican ANTES de mezclar.
- Pushear a `stage` desde la rama de trabajo sin que Franky lo pida.

## Requisitos de servidor (fuera del repo, los gestiona Franky)

- `php85` vía SCL en el runner self-hosted de stage, con las mismas extensiones
  que `php83` (pdo_mysql, intl, gd/imagick según `spatie/browsershot`, etc.).
- Node ≥ 22.12 y `npm` en el runner.
- Variables `SOFTOK2_TELEGRAM_*` dejan de leerse; pueden quedarse en el `.env`.
- `INERTIA_SSR_ENABLED=false` en el `.env` de stage (y de producción).
- **Producción**: el repo solo tiene el workflow de stage. Un `composer.lock`
  resuelto en PHP 8.5 no instala en una máquina con 8.3, así que producción
  necesita `php85` y Node ≥ 22.12 ANTES de que esto llegue a `main`. Ver
  "Preguntas abiertas".

## Qué queda al constructor

- Orden interno de los `composer require`/`update` para que el solver resuelva
  de una vez (todo sube junto: framework, pest, plugin-laravel, inertia, ai,
  tinker, laradumps, ide-helper, quitar telegram).
- Cómo partir el trabajo en commits (sugerido: backend+tests, Inertia, ai,
  frontend toolchain, deploy), sin commitear hasta que Franky lo pida.
- Resolver los findings de `eslint --fix` y Rector caso por caso.
- Decidir si quita los 12 `uses(RefreshDatabase::class)` redundantes.
- Confirmar si `php artisan optimize` ya cubre `view:clear` en Laravel 13.

## Preguntas abiertas

1. ¿Se instala `php85` en stage antes de mezclar? (asumido sí).
2. ¿Cómo se despliega producción y cuándo tendrá PHP 8.5 y Node ≥ 22.12? No hay
   workflow en el repo. La respuesta decide si la mezcla a `main` necesita su
   propia ventana y si hay un segundo servidor en la lista de requisitos.
3. Al quitar el paquete de Telegram, producción pierde las alertas de
   excepciones. Si se quieren conservar, son tres clases pequeñas portables
   dentro de la app con el canal `^8.0`; no está en esta entrega salvo que se
   pida.

## Fuentes

Guías oficiales: laravel.com/docs/13.x/upgrade, pestphp.com/docs/upgrade-guide y
v5.pestphp.com, inertiajs.com/upgrade-guide, github.com/laravel/ai/blob/1.x/UPGRADE.md,
vite.dev/guide/migration, typescriptlang.org (TS 6.0), eslint.org/docs/latest/use/migrate-to-10.0.0,
lucide.dev/guide/version-1, github.com/antfu/eslint-config/releases. Informe
largo de Laravel 13 en el scratchpad de la sesión
(`research-laravel13.md`).
