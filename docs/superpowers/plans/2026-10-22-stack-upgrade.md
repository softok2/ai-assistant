# Upgrade del stack (PHP 8.5, Laravel 13, Pest 5, Inertia 3, laravel/ai 1.x, Vite 8) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar `softok2/ai-assistant` en PHP `^8.5`, Laravel 13, Pest 5, Inertia 3, laravel/ai 1.3 y Vite 8 sin cambiar comportamiento visible, con el gate completo en verde.

**Architecture:** Una sola entrega de 7 tareas sobre la rama `upgrade/laravel-13-php-8.5`. El backend sube en UN `composer update` (framework, Pest, Inertia, ai, tinker, laradumps, ide-helper deben resolverse juntos). El frontend sube en UN `npm install` que regenera `package-lock.json`. Cada tarea deja el repo compilando y con sus tests en verde; la última tarea corre el gate completo.

**Tech Stack:** PHP 8.5.12 (Herd, `php` en PATH), PHP 8.3 en `/opt/homebrew/opt/php@8.3/bin/php` (solo para la línea base), Composer, Node 22.22 + npm, Pest 5 sobre sqlite `:memory:`, Vite 8 (Rolldown), TypeScript 6, ESLint 10 + antfu 9 + oxlint.

**Spec:** `docs/superpowers/specs/2026-10-22-stack-upgrade-design.md`

## Global Constraints

- **No commitear ni pushear.** Regla global de Franky: cada tarea termina con `git add` y un `git status --short`; Franky commitea. Nunca `git push` a `stage` (despliega solo).
- Rama de trabajo: `upgrade/laravel-13-php-8.5`, creada desde `stage`.
- `composer.json`: `php ^8.5`, `laravel/framework ^13.0`, `pestphp/pest ^5.3`, `pestphp/pest-plugin-laravel ^5.0`, `inertiajs/inertia-laravel ^3.5`, `laravel/ai ^1.3`, `laravel/tinker ^3.0`, `laradumps/laradumps ^5.4`, `barryvdh/laravel-ide-helper ^3.7`, `driftingly/rector-laravel ^2.7`. Fuera `softok2/telegram-notifications` y `laravel-notification-channels/telegram`.
- `package.json`: TypeScript `^6.0` (NO 7), `@vueuse/core ^14.1` (NO 15), `vite ^8.3`, `laravel-vite-plugin ^3.2`, `@vitejs/plugin-vue ^6.0`, `@inertiajs/vue3 ^3.9`, `@lucide/vue ^1.61` (reemplaza `lucide-vue-next`), `axios ^1.15`, `eslint ^10.13`, `@antfu/eslint-config ^9.5`, `eslint-plugin-format ^2.0`, `eslint-plugin-oxlint ^1.89` + `oxlint ~1.89.0`, `vue-tsc ^3.3`, `@types/node ^22`. Fuera `motion-v`, `eslint-plugin-vue`, `typescript-eslint`, `@vue/eslint-config-typescript`, `@eslint/js`, `eslint-config-prettier`. `"engines": {"node": ">=22.12"}`.
- Gestor npm; se borra `bun.lock`.
- `PreventRequestForgery` en modo por defecto (nunca `originOnly`).
- SSR sigue apagado; `app.ts` conserva `createApp`.
- Prefijos de cache/redis/cookie no cambian. Modelos de OpenAI no se fijan (se acepta `gpt-6-luna` como cheapest).
- `grep` del shell está aliased a `ugrep`: usar `rg` o `command grep`.

## Review Focus

1. **POST desde el iframe cross-site** (`FRAME_ANCESTORS`, `SameSite=None`) con token válido debe pasar con `PreventRequestForgery` por defecto. El middleware se salta en tests, así que lo pina la comprobación manual de la Tarea 7 (paso 6).
2. **Peticiones a `api/*` sin cabecera `Accept` que fallan** deben devolver JSON, no HTML, tras añadir `shouldRenderJsonWhen`. Test en Tarea 2 (`ApiErrorRenderingTest`).
3. **`migrate` incremental sobre una BD con las tablas 0.7** (lo que hará producción) debe dejar el esquema 1.3. Test en Tarea 3 (`AgentConversationsSchemaTest`, segundo `it`).
4. **Eventos nuevos del stream** de laravel/ai 1.x (`text_start`, `text_end`, `reasoning_*`, `tool_approval_request`) deben ser ignorados por `useAssistantStream.ts` sin romper el render. Lo cubren `ChatStreamControllerTest` (servidor) y el humo manual de la Tarea 7 (paso 5, enviar un mensaje con streaming).
5. **Default-imports CJS bajo Rolldown** (`highlight.js`, `markdown-it` vía `vue-markdown-render`): un mensaje con bloque de código debe resaltarse. Humo manual de la Tarea 7 (paso 5); red: `legacy.inconsistentCjsInterop: true` en `vite.config.ts`.

---

### Task 1: Rama y línea base

**Files:**
- Ninguno del repo cambia. Se crea `docs/superpowers/plans/2026-10-22-stack-upgrade-baseline.md` (notas de la línea base).

**Interfaces:**
- Produces: rama `upgrade/laravel-13-php-8.5`; número de tests que pasan hoy y resultado de `npm run build` hoy, para distinguir fallos preexistentes.

- [ ] **Step 1: Crear la rama desde `stage`**

Run: `cd /Volumes/FrankySSD/Projects/ai-assistant && git checkout -b upgrade/laravel-13-php-8.5 stage && git branch --show-current`
Expected: `upgrade/laravel-13-php-8.5`

- [ ] **Step 2: Instalar el lock actual con PHP 8.3** (el lock no instala en 8.5)

Run: `/opt/homebrew/opt/php@8.3/bin/php $(which composer) install --no-interaction 2>&1 | tail -5`
Expected: termina con `Generating optimized autoload files` / `package:discover` sin errores.

- [ ] **Step 3: Correr los tests actuales con PHP 8.3**

Run: `[ -f .env ] || (cp .env.example .env && /opt/homebrew/opt/php@8.3/bin/php artisan key:generate --ansi); /opt/homebrew/opt/php@8.3/bin/php artisan test 2>&1 | tail -8`
(No tocar un `.env` existente ni regenerar `APP_KEY`.)
Expected: línea `Tests: N passed` (anotar N; si hay fallos, anotar cuáles: son preexistentes).

- [ ] **Step 4: Instalar e intentar compilar el frontend actual**

Run: `npm ci 2>&1 | tail -3 && npm run build 2>&1 | tail -5`
Expected: `✓ built in …`. Anotar warnings.

- [ ] **Step 5: Escribir la línea base**

Crear `docs/superpowers/plans/2026-10-22-stack-upgrade-baseline.md` con: N tests en verde (y lista de fallos preexistentes si los hay), resultado del build, versión de PHP/Node usadas. Cinco líneas bastan.

- [ ] **Step 6: Stage**

```bash
git add docs/superpowers/plans/2026-10-22-stack-upgrade-baseline.md && git status --short
```

---

### Task 2: Backend en Laravel 13 + PHP 8.5 + Pest 5 + Inertia 3 + laravel/ai 1.3

**Files:**
- Modify: `composer.json` (require, require-dev, scripts)
- Modify: `config/database.php:60-66,80-86` (`PDO::MYSQL_ATTR_SSL_CA`)
- Modify: `config/cache.php` (antes de `'prefix'`, línea ~108)
- Modify: `config/session.php` (después de `'encrypt'`, línea ~52)
- Modify: `config/sanctum.php:8,86`
- Modify: `bootstrap/app.php:14,46-53`
- Modify: `phpunit.xml` (`<php>`)
- Modify: `tests/Feature/Ai/StreamInsightsTest.php:26,27,50,66`
- Modify: `resources/views/app.blade.php:33`
- Modify: `.env.example` (añadir `INERTIA_SSR_ENABLED=false` junto a las variables de sesión)
- Delete: `config/telegram-notification.php`, `scripts/use-local.php`, `scripts/use-remote.php`
- Create: `tests/Feature/Http/ApiErrorRenderingTest.php`

**Interfaces:**
- Produces: `vendor/` resuelto en PHP 8.5 con Laravel 13.x, Pest 5.x; `composer.lock` nuevo. `php artisan test` en verde.

- [ ] **Step 1: Escribir el test de JSON en `api/*` (falla hoy)**

`tests/Feature/Http/ApiErrorRenderingTest.php`:

```php
<?php

declare(strict_types=1);

it('renders api errors as json even without an accept header', function (): void {
    $this->get('/api/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
```

- [ ] **Step 2: Verificar que falla con el stack actual**

Run: `/opt/homebrew/opt/php@8.3/bin/php artisan test --filter=ApiErrorRenderingTest 2>&1 | tail -6`
Expected: FAIL (`Content-Type` es `text/html; charset=UTF-8`).

- [ ] **Step 3: Editar `composer.json`**

En `require`: `"php": "^8.5"`, `"inertiajs/inertia-laravel": "^3.5"`, `"laravel/ai": "^1.3"`, `"laravel/framework": "^13.0"`, `"laravel/octane": "^2.22"`, `"laravel/sanctum": "^4.3"`, `"laravel/telescope": "^5.26"`, `"laravel/tinker": "^3.0"`. Eliminar `"laravel-notification-channels/telegram"` y `"softok2/telegram-notifications"`.
En `require-dev`: `"barryvdh/laravel-ide-helper": "^3.7"`, `"driftingly/rector-laravel": "^2.7"`, `"laradumps/laradumps": "^5.4"`, `"pestphp/pest": "^5.3"`, `"pestphp/pest-plugin-laravel": "^5.0"`.
En `scripts`: eliminar `use-local` y `use-remote`.

- [ ] **Step 4: Quitar el paquete de Telegram del código**

- `rm config/telegram-notification.php scripts/use-local.php scripts/use-remote.php && rmdir scripts`
- `bootstrap/app.php`: borrar `use Softok2\TelegramNotification\Facades\TelegramNotification;` y el `->report(function (Throwable $e) {...})`, dejando `$exceptions->dontReportDuplicates();`. Añadir `use Illuminate\Http\Request;` y, en el mismo closure de excepciones:

```php
$exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
```

- [ ] **Step 5: Config para Laravel 13 / PHP 8.5**

- `config/database.php`: añadir `use Pdo\Mysql;` junto al `use Illuminate\Support\Str;` y cambiar las dos ocurrencias `PDO::MYSQL_ATTR_SSL_CA` por `Mysql::ATTR_SSL_CA`.
- `config/cache.php`: antes de `'prefix'`, añadir `'serializable_classes' => false,` con un bloque de comentario corto al estilo del archivo.
- `config/session.php`: después de `'encrypt' => env('SESSION_ENCRYPT', false),` añadir `'serialization' => 'json',` con comentario.
- `config/sanctum.php`: `use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;` y `'validate_csrf_token' => PreventRequestForgery::class,`.
- `phpunit.xml`, dentro de `<php>`: `<env name="BROADCAST_CONNECTION" value="null"/>`, `<env name="DB_URL" value=""/>`, `<env name="INERTIA_SSR_ENABLED" value="false"/>`, `<env name="NIGHTWATCH_ENABLED" value="false"/>`.
- `.env.example`: añadir `INERTIA_SSR_ENABLED=false` debajo de `SESSION_SAME_SITE=none`.

- [ ] **Step 6: Resolver dependencias en PHP 8.5**

Run: `rm -rf vendor && composer update --no-interaction 2>&1 | tail -15`
Expected: sin `Your requirements could not be resolved`. Luego `php artisan about | rg -i "laravel version|php version"` → `Laravel Version 13.x`, `PHP Version 8.5.12`. Y `php -r 'require "vendor/autoload.php"; var_dump(class_exists(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class));'` → `bool(true)` (si es `false`, revisar el nombre en `vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/` y corregir sanctum.php).

- [ ] **Step 7: Adaptar código a laravel/ai 1.3 e Inertia 3**

- `tests/Feature/Ai/StreamInsightsTest.php` líneas 26, 27, 50, 66: añadir `'openai'` como séptimo argumento de cada `new ProviderToolEvent(..., time())` → `..., time(), 'openai')`.
- `resources/views/app.blade.php:33`: `<title inertia>` → `<title data-inertia>`.
- `php artisan view:clear`.

- [ ] **Step 8: Correr toda la suite con Pest 5**

Run: `php artisan test 2>&1 | tail -12`
Expected: `Tests: N+1 passed` (N de la línea base más `ApiErrorRenderingTest`). Si falla algo de `ChatHistoryTest:154`, `ChatSourcesPropsTest:44` o `assertInertia`, leer el page object real con `dump()` y adaptar la aserción al shape de Inertia 3 (el spec espera que no haga falta).

- [ ] **Step 9: Stage**

```bash
git add -A && git status --short
```

---

### Task 3: Recrear `agent_conversations` con el esquema de laravel/ai 1.3

**Files:**
- Create: `database/migrations/2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1.php`
- Create: `tests/Feature/Database/AgentConversationsSchemaTest.php`

**Interfaces:**
- Produces: tablas `agent_conversations` y `agent_conversation_messages` con las columnas de 1.3 (`participant_type`, `participant_id`, `steps`, `status`) y sin `user_id`, `tool_calls`, `tool_results`.

- [ ] **Step 1: Escribir los tests (fallan hoy)**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('has the laravel/ai 1.x conversation schema after a fresh migrate', function (): void {
    expect(Schema::hasColumns('agent_conversations', ['participant_type', 'participant_id', 'title']))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversations', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumns('agent_conversation_messages', ['participant_type', 'participant_id', 'steps', 'status']))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversation_messages', 'tool_calls'))->toBeFalse();
});

it('upgrades tables that still carry the 0.7 schema', function (): void {
    Schema::dropIfExists('agent_conversation_messages');
    Schema::dropIfExists('agent_conversations');
    Schema::create('agent_conversations', function (Blueprint $table): void {
        $table->string('id', 36)->primary();
        $table->foreignId('user_id')->nullable();
        $table->string('title');
        $table->timestamps();
    });
    Schema::create('agent_conversation_messages', function (Blueprint $table): void {
        $table->string('id', 36)->primary();
        $table->string('conversation_id', 36)->index();
        $table->foreignId('user_id')->nullable();
        $table->text('tool_calls');
        $table->text('tool_results');
        $table->timestamps();
    });

    $migration = require database_path('migrations/2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1.php');
    $migration->up();

    expect(Schema::hasColumn('agent_conversation_messages', 'steps'))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversation_messages', 'tool_calls'))->toBeFalse();
});
```

- [ ] **Step 2: Verificar que fallan**

Run: `php artisan test --filter=AgentConversationsSchemaTest 2>&1 | tail -8`
Expected: 2 FAIL (primera por columnas, segunda por archivo inexistente).

- [ ] **Step 3: Escribir la migración**

`database/migrations/2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1.php`: clase anónima que extiende `Illuminate\Database\Migrations\Migration`. `up()`: `Schema::dropIfExists` de mensajes y luego conversaciones (nombres desde `config('ai.conversations.tables.*')` con los mismos defaults que el paquete), y después `Schema::create` de ambas copiando exactamente el esquema de la migración `2026_01_11_000001_create_agent_conversations_table.php` de laravel/ai 1.3 (está en `vendor/laravel/ai/database/migrations/`): conversaciones con `id string(36) primary`, `participant_type nullable`, `participant_id unsignedBigInteger nullable`, `title`, timestamps, índice `participant_updated_at_index`; mensajes con `id`, `conversation_id` indexado, `participant_type`, `participant_id`, `agent`, `role(25)`, `content text`, `attachments text`, `steps longText`, `usage text`, `meta text`, `status(25)`, timestamps, índices `conversation_index` y `participant_index`. `down()`: `dropIfExists` de ambas. Comentario de cabecera: las tablas están dormidas (la app no las usa), por eso se recrean en vez de migrar datos.

- [ ] **Step 4: Verificar que pasan**

Run: `php artisan test --filter=AgentConversationsSchemaTest 2>&1 | tail -6`
Expected: `2 passed`. Luego, sobre una sqlite desechable (NUNCA la BD de desarrollo de `.env`): `touch storage/upgrade-gate.sqlite && DB_CONNECTION=sqlite DB_DATABASE=$PWD/storage/upgrade-gate.sqlite php artisan migrate:fresh --seed --force 2>&1 | tail -3` → sin errores. Borrar el archivo al terminar.

- [ ] **Step 5: Stage**

```bash
git add database/migrations/2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1.php tests/Feature/Database/AgentConversationsSchemaTest.php && git status --short
```

---

### Task 4: Rector php85 y Pint

**Files:**
- Modify: `rector.php` (`withPhpSets(php83: true)` → `php85: true`)
- Modify: lo que Rector/Pint propongan en `app/`, `config/`, `database/`, `tests/`

- [ ] **Step 1: Cambiar el set de PHP**

`rector.php`: `->withPhpSets(php85: true)`.

- [ ] **Step 2: Dry-run**

Run: `vendor/bin/rector --dry-run 2>&1 | tail -30`
Expected: lista de cambios propuestos (o `[OK] Rector is done!`). Leerlos: aplicar solo los que no cambian semántica (tipos, `#[\Override]` sigue excluido, sintaxis 8.4/8.5). Si alguno toca lógica, dejarlo fuera añadiéndolo a `withSkip` con una línea de comentario.

- [ ] **Step 3: Aplicar y formatear**

Run: `vendor/bin/rector 2>&1 | tail -3 && vendor/bin/pint 2>&1 | tail -3`
Expected: ambos terminan sin errores.

- [ ] **Step 4: Tests en verde**

Run: `php artisan test 2>&1 | tail -6`
Expected: mismo número de `passed` que al cerrar la Tarea 3 (N+3).

- [ ] **Step 5: Stage**

```bash
git add -A && git status --short
```

---

### Task 5: Toolchain de frontend en Vite 8 / TS 6 e iconos `@lucide/vue`

**Files:**
- Modify: `package.json`
- Modify: `vite.config.ts:5,30-35` (quitar `MotionResolver`)
- Modify: `.gitignore` (añadir `/components.d.ts`)
- Delete: `bun.lock`
- Regenerate: `package-lock.json`
- Modify: los 49 archivos bajo `resources/js/` que importan `lucide-vue-next` (`rg -l "lucide-vue-next" resources/js`)
- Modify (renombres): `resources/js/pages/settings/Report.vue`, `resources/js/components/assistant/{MessageActions,ChatInput,SearchChatsDialogV2,ActivityTrail,ChatHistorySidebar,ChatSidebar}.vue`, `resources/js/components/ui/breadcrumb/BreadcrumbEllipsis.vue`
- Modify: `resources/js/types/index.d.ts:2` (`LucideIcon` desde `@lucide/vue`)

**Interfaces:**
- Produces: `node_modules` con la matriz de Global Constraints; `npm run build` y `npm run build:ssr` en verde; `npm run typecheck` en verde.

- [ ] **Step 1: Reescribir `package.json`**

`dependencies`: `@iconify/vue ^5.0.4`, `@inertiajs/vue3 ^3.9.1`, `@laravel/stream-vue ^0.3.19`, `@lucide/vue ^1.61.0`, `@tailwindcss/typography ^0.5.20`, `@tailwindcss/vite ^4.3.3`, `@types/markdown-it ^14.1.2`, `@vitejs/plugin-vue ^6.0.10`, `@vueuse/core ^14.1.0`, `axios ^1.15.2`, `class-variance-authority ^0.7.1`, `clsx ^2.1.1`, `concurrently ^9.1.2`, `highlight.js ^11.12.0`, `laravel-vite-plugin ^3.2.0`, `reka-ui ^2.11.0`, `tailwind-merge ^3.3.1`, `tailwindcss ^4.3.3`, `tw-animate-css ^1.4.0`, `typescript ^6.0.3`, `unplugin-vue-components ^32.1.0`, `vite ^8.3.5`, `vue ^3.5.44`, `vue-markdown-render ^2.3.1`, `vue-sonner ^2.0.9`, `ziggy-js ^2.6.5`. Quitar `lucide-vue-next` y `motion-v`.
`devDependencies`: `@antfu/eslint-config ^9.5.1`, `@types/node ^22.20.5`, `eslint ^10.13.0`, `eslint-plugin-format ^2.0.1`, `eslint-plugin-oxlint ^1.89.0`, `oxlint ~1.89.0`, `vue-tsc ^3.3.13`. Quitar `@eslint/js`, `@vue/eslint-config-typescript`, `eslint-config-prettier`, `eslint-plugin-vue`, `typescript-eslint`.
`optionalDependencies`: quitar `@rollup/rollup-linux-x64-gnu`; mantener `@tailwindcss/oxide-linux-x64-gnu ^4.3.3` y `lightningcss-linux-x64-gnu ^1.30.1`; el binding de Rolldown se añade en el paso 3.
`scripts`: `"lint": "oxlint && eslint ."`, `"lint:fix": "oxlint --fix && eslint --fix ."`, añadir `"typecheck": "vue-tsc --noEmit"`. Añadir `"engines": { "node": ">=22.12" }`.

- [ ] **Step 2: Regenerar el lock**

Run: `rm -f bun.lock && rm -rf node_modules package-lock.json && npm install 2>&1 | tail -8`
Expected: sin `ERESOLVE`. Si aparece un conflicto de peers, anotar qué paquete y resolver bajando SOLO ese paquete al mayor rango compatible (nunca `--legacy-peer-deps`).

- [ ] **Step 3: Pinear el binding de Rolldown para Linux**

Run: `npm ls rolldown --depth=10 2>/dev/null | rg -o "rolldown@[0-9.]+" | head -1`
Expected: `rolldown@1.2.x`. Añadir a `optionalDependencies`: `"@rolldown/binding-linux-x64-gnu": "<esa versión exacta>"` y `npm install` de nuevo.

- [ ] **Step 4: Quitar motion-v de Vite y versionar bien**

- `vite.config.ts`: borrar `import MotionResolver from 'motion-v/resolver'` y el array `resolvers: [MotionResolver()]` (dejar `Components({ dts: true })`).
- `.gitignore`: añadir `/components.d.ts`.

- [ ] **Step 5: Renombrar el paquete en los imports**

Run: `rg -l "lucide-vue-next" resources/js | xargs sed -i '' "s#'lucide-vue-next'#'@lucide/vue'#g" && rg -c "lucide-vue-next" resources/js | wc -l`
Expected: `0`.

- [ ] **Step 6: Renombrar los iconos desaparecidos**

Run: `rg -l "\bLoader2\b" resources/js | xargs sed -i '' 's/\bLoader2\b/LoaderCircle/g'; rg -l "\bMoreHorizontal\b" resources/js | xargs sed -i '' 's/\bMoreHorizontal\b/Ellipsis/g'; rg -n "\bLoader2\b|\bMoreHorizontal\b" resources/js | wc -l`
Expected: `0`. Revisar a mano que ningún identificador local se llamara `Ellipsis` ya (p. ej. en `BreadcrumbEllipsis.vue`): si choca, importar con alias `Ellipsis as EllipsisIcon`.

- [ ] **Step 7: Botones solo-icono en los archivos tocados**

En los 8 archivos del renombre, buscar botones cuyo único hijo es un icono (`<Button ...><LoaderCircle|Ellipsis .../></Button>`) y asegurarse de que tienen `aria-label` o un `<span class="sr-only">`. Los iconos ahora llevan `aria-hidden="true"` por defecto. Añadir solo lo que falte; no auditar el resto del proyecto.

- [ ] **Step 8: Typecheck**

Run: `npm run typecheck 2>&1 | tail -20`
Expected: sin errores. Si TS 6 reporta algo nuevo (p. ej. `noUncheckedSideEffectImports`), corregir en el archivo señalado, no en `tsconfig.json`, salvo que sea un `lib` renombrado.

- [ ] **Step 9: Compilar**

Run: `npm run build 2>&1 | tail -15 && npm run build:ssr 2>&1 | tail -8`
Expected: ambos `✓ built in …`. Anotar cualquier warning de interop CJS (ver Review Focus 5).

- [ ] **Step 10: Stage**

```bash
git add -A package.json package-lock.json vite.config.ts .gitignore resources/js && git rm -q --cached bun.lock 2>/dev/null; git status --short
```

---

### Task 6: Lint con ESLint 10 + antfu 9 + oxlint

**Files:**
- Modify: `eslint.config.mjs` (solo si antfu 9 rechaza una opción)
- Modify: `.oxlintrc.json` (solo si oxlint 1.89 rechaza una clave)
- Modify: archivos bajo `resources/js/` que `eslint --fix` reescriba

- [ ] **Step 1: Correr oxlint solo**

Run: `npx oxlint 2>&1 | tail -10`
Expected: `Found 0 warnings and 0 errors` o una lista. Si falla al cargar `.oxlintrc.json` (1.89 ya no silencia errores de config), corregir la clave que indique.

- [ ] **Step 2: Autofix de ESLint**

Run: `npx eslint --fix . 2>&1 | tail -30`
Expected: termina; quedan solo errores que no admiten autofix. Revisar el diff (`git diff --stat`): perfectionist 5 reordena imports y eslint-plugin-vue 10 añade `vue/block-order`; son cambios de forma. Si un cambio altera lógica, revertir ese archivo y desactivar esa regla en `eslint.config.mjs` con comentario.

- [ ] **Step 3: Resolver el residuo a mano**

Para cada error restante: arreglarlo en el archivo. Reglas nuevas esperables: `vue/no-required-prop-with-default`, `vue/valid-define-options`, `no-useless-assignment`, `preserve-caught-error`.

- [ ] **Step 4: Lint completo en verde**

Run: `npm run lint 2>&1 | tail -5 && npm run typecheck 2>&1 | tail -3 && npm run build 2>&1 | tail -3`
Expected: los tres sin errores.

- [ ] **Step 5: Stage**

```bash
git add -A && git status --short
```

---

### Task 7: Deploy de stage y gate completo

**Files:**
- Modify: `.github/workflows/deploy_ai_assistant_stage.yml`
- Create: `docs/superpowers/plans/2026-10-22-stack-upgrade-gate.md` (resultado del gate)

- [ ] **Step 1: Workflow**

En `.github/workflows/deploy_ai_assistant_stage.yml`: las 4 ocurrencias de `scl enable php83` → `scl enable php85`; el paso `Setup Node dependencies` pasa de `yarn` / `yarn build` a `npm ci` / `npm run build`; en `Setup Laravel mandatory commands` añadir `scl enable php85 "php artisan view:clear"` antes de `optimize`.

- [ ] **Step 2: Gate backend**

Run: `composer validate 2>&1 | tail -2 && php artisan about | rg -i "laravel version|php version" && php artisan test 2>&1 | tail -4 && vendor/bin/pint --test 2>&1 | tail -2 && vendor/bin/rector --dry-run 2>&1 | tail -2`
Expected: `is valid`, `13.x` / `8.5.12`, `Tests: … passed` (N+3), Pint `PASS`, Rector `[OK]`.

- [ ] **Step 3: Gate frontend**

Run: `npm ci 2>&1 | tail -2 && npm run lint 2>&1 | tail -2 && npm run typecheck 2>&1 | tail -2 && npm run build 2>&1 | tail -3 && npm run build:ssr 2>&1 | tail -3`
Expected: todo sin errores.

- [ ] **Step 4: Migraciones como en stage**

Run: `touch storage/upgrade-gate.sqlite && DB_CONNECTION=sqlite DB_DATABASE=$PWD/storage/upgrade-gate.sqlite php artisan migrate:fresh --seed --force 2>&1 | tail -3; rm storage/upgrade-gate.sqlite`
(sqlite desechable; nunca la BD de desarrollo.)
Expected: sin errores; incluye `2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1`.

- [ ] **Step 5: Humo manual con la app levantada**

Levantar con `composer dev` (o Herd). Con `agent-browser` o a mano: (a) entrar por el enlace externo firmado de un usuario de prueba del seed; (b) abrir un chat y enviar un mensaje; ver el stream, el título generado y las sugerencias (laravel/ai 1.3 + `gpt-6-luna`); (c) pedir algo que devuelva un bloque de código y comprobar el resaltado (highlight.js bajo Rolldown); (d) subir un documento a una fuente; (e) descargar el PDF de un mensaje. Anotar resultado de cada punto.

- [ ] **Step 6: CSRF desde un iframe cross-site**

Crear en Herd un sitio estático `csrf-host.test` (HTTPS) con un `index.html` que embeba `https://ai-assistant.test` en un `<iframe>` y, dentro de la app embebida, enviar un mensaje de chat (POST vía Inertia/axios). Con `FRAME_ANCESTORS` incluyendo `https://csrf-host.test` en `.env` local. Esperado: el POST responde 200/302, no 403 ni 419. Documentar cómo se montó para que Franky lo repita en stage desde CCM y Vallealto.

- [ ] **Step 7: Escribir el resultado del gate**

`docs/superpowers/plans/2026-10-22-stack-upgrade-gate.md`: tabla con cada comprobación (pasos 2–6) y su resultado, versiones finales (`php artisan about`, `npm ls vite typescript @inertiajs/vue3 --depth=0`), y la lista de requisitos de servidor pendientes de Franky: `php85` en stage y producción, Node ≥ 22.12 y npm en el runner, `INERTIA_SSR_ENABLED=false` en los `.env`, y que producción pierde las alertas de Telegram.

- [ ] **Step 8: Stage y reporte**

```bash
git add -A && git status --short && git diff --cached --stat | tail -3
```

Reportar a Franky: todo staged en `upgrade/laravel-13-php-8.5`, sin commits; lista de requisitos de servidor; nada se ha pusheado.
