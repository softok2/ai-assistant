# Vector stores por club y entorno — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** La app crea en OpenAI un vector store por club y entorno la primera vez que sube un archivo, guarda su id en la base y deja de leer `OPENAI_VECTOR_STORE_*` del `.env`.

**Architecture:** Una tabla `vector_stores` (club, entorno, proveedor → store_id) y el servicio existente `App\Ai\Files\ClubVectorStore` como único punto de resolución: `idFor`/`storeFor` crean el store si falta (solo los usa la subida), `existingIdFor`/`existingStoreFor` solo leen (chat, reportes, reconciliación, borrado, pantalla de Fuentes). La migración devuelve a pendiente las filas de `files` para que la siguiente sincronización resuba todo al store nuevo.

**Tech Stack:** Laravel 13, PHP 8.5, laravel/ai ^1.3 (`Laravel\Ai\Stores`), Pest 5, sqlite en tests.

**Spec:** `docs/superpowers/specs/2026-10-22-club-vector-stores-per-environment-design.md`

## Global Constraints

- No ejecutar `git commit` ni `git push`: Franky commitea. Cada tarea termina con `git add` de sus archivos y nada más.
- Nunca `migrate:fresh` ni nada destructivo contra la base de desarrollo; los tests corren sobre la sqlite desechable de `phpunit.xml`.
- Pint (`final_class`), Rector y la suite completa (`php artisan test --compact`) deben quedar en verde al cerrar cada tarea.
- Nombre del store: `{club}-assistant-{environment}` (p. ej. `ccm-assistant-stage`). Descripción: `Fuentes del asistente · club={club} environment={environment} provider={provider}`. Sin `fileIds`, sin caducidad.
- Proveedor de la fila: `ClubAiProvider::nameFor($club) ?? 'openai'`.
- Solo `File::upload()` crea stores. Todo lo demás usa los métodos `existing*`.
- Las pruebas nunca hablan con OpenAI: `Http::preventStrayRequests()` global en Feature.
- Modelo Eloquent `App\Models\VectorStore`, tabla `vector_stores` (el servicio ya se llama `ClubVectorStore`; evitamos dos clases con el mismo nombre). La spec decía `club_vector_stores`/`App\Models\ClubVectorStore`; este plan fija los nombres anteriores.

## Review Focus

1. `Stores::create` falla (sin crédito, 429; clave inválida): no debe quedar fila a medias y la subida debe marcar `failed` como hoy. Test en Task 2.
2. Fila existente cuyo store alguien borró en el panel de OpenAI: la subida recibe 404 y marca `failed`; la recuperación es borrar la fila. No se automatiza; el revisor comprueba que el error llega al log con el id del store.
3. Reconciliación de un club sin store pero con archivos sueltos en la cuenta: debe seguir borrando los sueltos sin crear store. Test en Task 4.
4. Dos workers piden el store a la vez y el candado expira: la violación del índice único se captura, se relee la fila y se borra el store sobrante. Test en Task 2 (simulando la fila insertada por "otro" dentro del candado).
5. Tras la migración, la pantalla de Fuentes muestra todo "Pendiente" y "Sin tamaño" hasta la siguiente sincronización; `health.indexed` debe ser 0 y `vector_store_suffix` `null`. Test en Task 5.

---

### Task 1: Tabla `vector_stores`, modelo y reset de `files`

**Files:**
- Create: `database/migrations/2026_10_22_120000_create_vector_stores_table.php`
- Create: `app/Models/VectorStore.php`
- Create: `database/factories/VectorStoreFactory.php`
- Test: `tests/Feature/Database/VectorStoresMigrationTest.php`

**Interfaces:**
- Produces: `App\Models\VectorStore` (final, `HasFactory`), `$fillable = ['club', 'environment', 'provider', 'store_id', 'name']`; sin casts especiales. Tabla `vector_stores`: `id`, `club` string(20), `environment` string(20), `provider` string(40), `store_id` string(80), `name` string(120), timestamps; único `(club, environment, provider)` con nombre `vector_stores_club_environment_provider_unique`.
- Produces: factory con `club => 'ccm'`, `environment => app()->environment()`, `provider => 'openai'`, `store_id => 'vs_'.fake()->unique()->lexify('????????????????')`, `name => 'ccm-assistant-'.app()->environment()`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\VectorStore;
use Illuminate\Support\Facades\Schema;

it('creates the vector_stores table with a unique key per club, environment and provider', function (): void {
    expect(Schema::hasColumns('vector_stores', ['club', 'environment', 'provider', 'store_id', 'name']))->toBeTrue();

    VectorStore::factory()->create(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai']);
    VectorStore::factory()->create(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai']);
})->throws(Illuminate\Database\UniqueConstraintViolationException::class);

it('sends indexed files back to pending so they are re-uploaded to the new store, leaving expired ones alone', function (): void {
    $indexed = File::factory()->completed()->create();
    $failed = File::factory()->failed()->create(['assistant_media_id' => 'file-old']);
    $expired = File::factory()->completed()->expired()->create();

    Schema::drop('vector_stores');
    $migration = include base_path('database/migrations/2026_10_22_120000_create_vector_stores_table.php');
    $migration->up();

    expect($indexed->fresh())->status->toBe(App\Enums\MediaStatus::PENDING)
        ->assistant_media_id->toBeNull()->synced_at->toBeNull()->bytes->toBeNull()
        ->and($failed->fresh())->status->toBe(App\Enums\MediaStatus::PENDING)->assistant_media_id->toBeNull()
        ->and($expired->fresh())->status->toBe(App\Enums\MediaStatus::COMPLETED)->assistant_media_id->not->toBeNull();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Database/VectorStoresMigrationTest.php --compact`
Expected: FAIL — `Class "App\Models\VectorStore" not found` / table missing.

- [ ] **Step 3: Write the migration, model and factory**

La migración crea la tabla y, en el mismo `up()`, ejecuta `DB::table('files')->whereNull('expired_at')->update(['status' => 'pending', 'assistant_media_id' => null, 'synced_at' => null, 'bytes' => null])`, con un comentario que explique por qué (los ids apuntan al store viejo). `down()` solo borra la tabla. Modelo y factory según Interfaces.

- [ ] **Step 4: Run to verify they pass**

Run: `php artisan test tests/Feature/Database/VectorStoresMigrationTest.php --compact`
Expected: PASS (2).

- [ ] **Step 5: Stage**

```bash
git add database/migrations/2026_10_22_120000_create_vector_stores_table.php app/Models/VectorStore.php database/factories/VectorStoreFactory.php tests/Feature/Database/VectorStoresMigrationTest.php
```

---

### Task 2: `ClubVectorStore` resuelve desde la base y crea el store si falta

**Files:**
- Modify: `app/Ai/Files/ClubVectorStore.php` (reescribir)
- Delete: `app/Ai/Files/MissingClubVectorStore.php` (solo si ya nadie la usa al cerrar la tarea; si Task 3/4 aún la necesitan, borrarla en Task 4)
- Modify: `tests/Pest.php` (helper + guardia)
- Modify: `tests/Feature/Files/ClubVectorStoreTest.php` (reescribir)
- Modify (cambiar `config(['services.openai.vector_stores…'])` por el helper): `tests/Feature/Ai/ClubAssistantContextTest.php`, `tests/Feature/Http/Controllers/ChatStreamControllerTest.php`, `tests/Feature/Files/FileUploadClubKeyTest.php`, `tests/Feature/Files/FileClubScopeTest.php`, `tests/Feature/Files/ReconcileAssistantFilesTest.php`, `tests/Feature/Sources/SourcesFileDestroyTest.php`, `tests/Feature/Files/RemoveExpiredDocsTest.php`, `tests/Feature/Files/OpenAiFileInventoryTest.php`, `tests/Feature/Files/UploadAssistantDocTest.php`, `tests/Feature/Reports/WeeklyReportTest.php`, `tests/Feature/Sources/SourcesPageTest.php`, `tests/Feature/Sources/SourcesFileReindexTest.php`, `tests/Feature/Sources/SourcesReconcileTest.php`

**Interfaces:**
- Consumes: `App\Models\VectorStore` (Task 1).
- Produces en `App\Ai\Files\ClubVectorStore` (final readonly, ctor `ClubAiProvider $providers`):
  - `idFor(ClubName $club): string` — fila o creación.
  - `storeFor(ClubName $club): Store` — `Stores::get($this->idFor($club), $this->providers->nameFor($club))`.
  - `existingIdFor(ClubName $club): ?string` — solo lectura.
  - `existingStoreFor(ClubName $club): ?Store` — `null` si no hay fila.
  - Se elimina `configured()`.
- Produces en `tests/Pest.php`:
  - `clubVectorStore(ClubName $club = ClubName::CCM, string $storeId = 'vs_test'): VectorStore` — inserta la fila con `environment => app()->environment()` y `provider => resolve(ClubAiProvider::class)->nameFor($club) ?? 'openai'`. Llamarla **después** de cualquier `config(['ai.providers.openai_x.key' => …])` del test, porque el proveedor forma parte de la clave.
  - `beforeEach(fn () => Http::preventStrayRequests())` en el bloque `->in('Feature')`.

- [ ] **Step 1: Write the failing tests** (reemplaza el contenido de `ClubVectorStoreTest.php`)

```php
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Models\VectorStore;
use App\Ai\Files\ClubVectorStore;

it('creates the store on first use and remembers it for the club, environment and provider', function (): void {
    Stores::fake();

    $id = resolve(ClubVectorStore::class)->idFor(ClubName::CCM);

    Stores::assertCreated('ccm-assistant-testing');
    expect($id)->toBe(Stores::fakeId('ccm-assistant-testing'))
        ->and(VectorStore::query()->where(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai'])->value('store_id'))->toBe($id);
});

it('reuses the stored id without creating anything', function (): void {
    Stores::fake();
    clubVectorStore(ClubName::CCM, 'vs_known');

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toBe('vs_known');
    Stores::assertNothingCreated();
});

it('ignores a row from another environment', function (): void {
    Stores::fake();
    VectorStore::factory()->create(['club' => 'ccm', 'environment' => 'production', 'store_id' => 'vs_prod']);

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->not->toBe('vs_prod');
    Stores::assertCreated('ccm-assistant-testing');
});

it('creates a new store when the club moves to its own OpenAI project', function (): void {
    Stores::fake();
    VectorStore::factory()->create(['club' => 'ccm', 'provider' => 'openai', 'store_id' => 'vs_shared']);
    config(['ai.providers.openai_ccm.key' => 'sk-ccm']);

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->not->toBe('vs_shared')
        ->and(VectorStore::query()->where(['club' => 'ccm', 'provider' => 'openai_ccm'])->exists())->toBeTrue();
});

it('only reads when asked for an existing store', function (): void {
    Stores::fake();

    expect(resolve(ClubVectorStore::class)->existingIdFor(ClubName::VALLEALTO))->toBeNull()
        ->and(resolve(ClubVectorStore::class)->existingStoreFor(ClubName::VALLEALTO))->toBeNull();
    Stores::assertNothingCreated();

    clubVectorStore(ClubName::VALLEALTO, 'vs_va');

    expect(resolve(ClubVectorStore::class)->existingIdFor(ClubName::VALLEALTO))->toBe('vs_va')
        ->and(resolve(ClubVectorStore::class)->existingStoreFor(ClubName::VALLEALTO)?->id)->toBe('vs_va');
});

it('leaves no row behind when OpenAI refuses to create the store', function (): void {
    // Sin Stores::fake(): la guardia global convierte la petición real en excepción.
    config(['ai.providers.openai.key' => 'sk-test']);

    expect(fn () => resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toThrow(Throwable::class)
        ->and(VectorStore::query()->count())->toBe(0);
});

it('keeps the row another worker inserted meanwhile and drops the extra store', function (): void {
    Stores::fake();
    // Simula la carrera: la fila aparece después de que este proceso decidió crear.
    Event::listen(Laravel\Ai\Events\StoreCreated::class, fn () => VectorStore::factory()->create(['club' => 'ccm', 'store_id' => 'vs_winner']));

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toBe('vs_winner')
        ->and(VectorStore::query()->where('club', 'ccm')->count())->toBe(1);
});
```

`Laravel\Ai\Events\StoreCreated` existe en laravel/ai 1.3 (`vendor/laravel/ai/src/Events/StoreCreated.php`); el listener inserta la fila "ganadora" justo después de que este proceso creó su store y antes de que intente guardar la suya.

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Files/ClubVectorStoreTest.php --compact`
Expected: FAIL — `clubVectorStore()` undefined / `existingIdFor` undefined.

- [ ] **Step 3: Add the Pest helper and the stray-request guard** in `tests/Pest.php` (Interfaces).

- [ ] **Step 4: Rewrite `ClubVectorStore`**

Algoritmo de `idFor` (lo único que las firmas no determinan):

```
provider = providers->nameFor(club) ?? 'openai'; env = app()->environment()
row = VectorStore where (club, env, provider) → return store_id si existe
Cache::lock("club-vector-store:{club}:{env}:{provider}", 60)->block(10, function:
    row otra vez → return si existe
    store = Stores::create(name, description, [], null, provider)
    try VectorStore::create([...]) catch UniqueConstraintViolationException:
        rescue(fn => Stores::delete(store->id, provider))   // mejor esfuerzo
        return fila existente ->store_id
    return store->id
)
```

`storeFor`, `existingIdFor`, `existingStoreFor` según Interfaces. Borrar `configured()`.

- [ ] **Step 5: Run to verify they pass**

Run: `php artisan test tests/Feature/Files/ClubVectorStoreTest.php --compact`
Expected: PASS (7).

- [ ] **Step 6: Replace `config(['services.openai.vector_stores…'])` with `clubVectorStore(...)` in the 13 test files listed**

Regla por archivo: el id que antes iba en config pasa como segundo argumento (`vs_test`, `vs_va`, `vs_ccm`, `vs_abcdefgh12345678`…); el club es la clave del array. Donde el test fijaba `'ccm' => null` o `[]` para "sin store", simplemente no se llama al helper. En `WeeklyReportTest` el `config(['ai.providers.openai_ccm.key' => 'sk-ccm'])` va antes del helper.

- [ ] **Step 7: Run the whole suite**

Run: `php artisan test --compact`
Expected: fallan solo los tests que dependen del comportamiento aún no implementado de Tasks 3–4 (chat/reportes "sin store", `MissingClubVectorStore`), más cualquier test que la guardia destape haciendo red sin fake. Anotar la lista exacta en el ledger; los de red se arreglan aquí añadiendo su `Http::fake`/`Stores::fake`; los de Tasks 3–4 se dejan.

- [ ] **Step 8: Stage**

```bash
git add app/Ai/Files/ClubVectorStore.php tests/Pest.php tests/Feature
```

---

### Task 3: Chat, reporte semanal y pantalla de Fuentes solo leen

**Files:**
- Modify: `app/Ai/Agents/ClubAssistant.php:110-128`
- Modify: `app/Ai/Agents/ModuleReportAnalyst.php:68-76`
- Modify: `app/Queries/AssistantSourcesHealthQuery.php:86-93`
- Test: `tests/Feature/Ai/ClubAssistantContextTest.php`, `tests/Feature/Reports/WeeklyReportTest.php`, `tests/Feature/Sources/SourcesPageTest.php`

**Interfaces:**
- Consumes: `ClubVectorStore::existingIdFor(ClubName): ?string` (Task 2); `clubVectorStore()` helper.

- [ ] **Step 1: Write/adjust the failing tests**

En `ClubAssistantContextTest`, el test «degrades to web search only…» pasa a:

```php
it('degrades to web search only when the club has no store yet, and never creates one', function (): void {
    Stores::fake();

    $tools = iterator_to_array(ClubAssistant::make(club: ClubName::VALLEALTO, role: RoleName::ADMIN)->tools());

    expect(fileSearchOf($tools))->toBeNull()->and($tools)->toHaveCount(1);
    Stores::assertNothingCreated();
});
```

En `WeeklyReportTest` añadir:

```php
it('runs the module analyst without file search when the club has no store yet', function (): void {
    Stores::fake();

    expect(iterator_to_array(new ModuleReportAnalyst(ClubName::VALLEALTO, 'golf')->tools()))->toBe([]);
    Stores::assertNothingCreated();
});
```

En `SourcesPageTest`, «reports no store when the club has none configured» pasa a no llamar al helper y a esperar `health.vector_store_suffix` `null` (Review Focus 5: añadir `->where('health.indexed', 0)` con un `File::factory()->create()` pendiente).

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Reports/WeeklyReportTest.php tests/Feature/Sources/SourcesPageTest.php --compact`
Expected: FAIL — el chat/analista intentan `idFor` (crean store o lanzan), la query atrapa `MissingClubVectorStore`.

- [ ] **Step 3: Switch the three consumers to `existingIdFor`**

`ClubAssistant::tools()`: `$store = ...->existingIdFor($this->club); if ($store === null) return [new WebSearch];`. `ModuleReportAnalyst::tools()`: devolver `[]` si `null`. `AssistantSourcesHealthQuery::vectorStoreSuffix()`: `$id = existingIdFor($club); return $id === null ? null : mb_substr($id, -8);` y quitar el `use` de `MissingClubVectorStore`.

- [ ] **Step 4: Run to verify they pass**

Run: mismo comando. Expected: PASS.

- [ ] **Step 5: Stage**

```bash
git add app/Ai/Agents/ClubAssistant.php app/Ai/Agents/ModuleReportAnalyst.php app/Queries/AssistantSourcesHealthQuery.php tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Reports/WeeklyReportTest.php tests/Feature/Sources/SourcesPageTest.php
```

---

### Task 4: Inventario, reconciliación y borrado sin crear stores

**Files:**
- Modify: `app/Ai/Files/OpenAiFileInventory.php:50-62`
- Modify: `app/Actions/Files/ReconcileAssistantFilesAction.php:98-112`
- Modify: `app/Models/File.php:144-155` (`removeFromProvider`)
- Delete: `app/Ai/Files/MissingClubVectorStore.php`
- Test: `tests/Feature/Files/ReconcileAssistantFilesTest.php`, `tests/Feature/Files/RemoveExpiredDocsTest.php`

**Interfaces:**
- Consumes: `existingIdFor`, `existingStoreFor` (Task 2); `ClubAiProvider::nameFor`.

- [ ] **Step 1: Write the failing tests**

En `ReconcileAssistantFilesTest`:

```php
it('reconciles a club without a store: no store is created and loose account files are still removed', function (): void {
    Stores::fake();
    Http::fake([
        'https://api.openai.com/v1/files?*' => Http::response(['data' => [['id' => 'file-loose', 'filename' => 'x.md', 'bytes' => 10, 'created_at' => 1_780_000_000]], 'has_more' => false]),
        'https://api.openai.com/v1/files/file-loose' => Http::response(['deleted' => true]),
    ]);

    $action = resolve(ReconcileAssistantFilesAction::class);
    $report = $action->report(ClubName::CCM);

    expect($report->orphans)->toBeEmpty()->and($report->loose->pluck('id')->all())->toBe(['file-loose']);
    expect($action->apply(ClubName::CCM, $report))->toBe(1);
    Stores::assertNothingCreated();
});
```

En `RemoveExpiredDocsTest`:

```php
it('removes an expired file whose club has no store yet by deleting only the account file and the row', function (): void {
    // Sin Stores::fake(): así la petición DELETE sale por HTTP y se puede inspeccionar.
    Storage::fake();
    Http::fake(['*/files/file-abc' => Http::response(['deleted' => true])]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-abc']);

    new RemoveExpiredDocs->handle();

    expect(File::query()->whereKey($file->id)->exists())->toBeFalse()
        ->and(App\Models\VectorStore::query()->count())->toBe(0);
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/files/file-abc'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/vector_stores'));
});
```

(El `beforeEach` de esos archivos ya no debe llamar a `clubVectorStore()` para estos dos tests; moverlo a los tests que sí lo necesitan o usar `describe` separados.)

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Files/ReconcileAssistantFilesTest.php tests/Feature/Files/RemoveExpiredDocsTest.php --compact`
Expected: FAIL — se crea un store (`assertNothingCreated`) o salta petición a `vector_stores/fake_store_…`.

- [ ] **Step 3: Implement**

- `OpenAiFileInventory::storeFiles()`: `$storeId = existingIdFor($club); if ($storeId === null) return collect();`.
- `ReconcileAssistantFilesAction::apply()`: `$store = existingStoreFor($club);` y saltar el bucle de huérfanos/duplicados cuando sea `null` (con store `null` el reporte ya no puede traer huérfanos ni duplicados).
- `File::removeFromProvider()`: `$store = existingStoreFor($club)`; con store, igual que hoy; sin store, `Files::delete($this->assistant_media_id, resolve(ClubAiProvider::class)->nameFor($this->club()))` con el mismo tratamiento del 404.
- Borrar `MissingClubVectorStore.php` y cualquier `use` restante (`grep -rn MissingClubVectorStore app tests` debe quedar vacío).

- [ ] **Step 4: Run to verify they pass, then the whole suite**

Run: `php artisan test --compact`
Expected: PASS completo.

- [ ] **Step 5: Stage**

```bash
git add app/Ai/Files app/Actions/Files/ReconcileAssistantFilesAction.php app/Models/File.php tests/Feature/Files
```

---

### Task 5: Configuración que se va, docs y gate

**Files:**
- Modify: `config/services.php:45-48` (quitar `vector_stores`)
- Modify: `.env.example` (quitar `OPENAI_VECTOR_STORE_CCM`, `OPENAI_VECTOR_STORE_VALLEALTO`; añadir un comentario de dos líneas: el store lo crea la app por club y entorno y vive en `vector_stores`)
- Modify: `docs/superpowers/specs/2026-10-22-club-vector-stores-per-environment-design.md` (nombres `vector_stores`/`App\Models\VectorStore`, ver Global Constraints)
- Create: `docs/superpowers/plans/2026-10-22-club-vector-stores-per-environment-gate.md`

- [ ] **Step 1: Prove nothing reads the old config**

Run: `grep -rn "vector_stores" app config tests resources/js | grep -v "vector_stores/" | grep -v "VectorStore"`
Expected: solo la línea de `config/services.php` que se va a borrar; tras borrarla, vacío.

- [ ] **Step 2: Remove the config and env entries; fix the spec names**

- [ ] **Step 3: Gate**

Run, en este orden, redirigiendo a `.superpowers/sdd/<plan>/gate-*.log`:

```bash
vendor/bin/pint --test
vendor/bin/rector --dry-run
php artisan test --compact
npm run lint && npm run typecheck && npm run build
```

Expected: todo en verde. Escribir el gate doc con: comandos y resultados, pasos de despliegue de la spec (migrar, quitar variables de `.env` en cada entorno, "Sincronizar ahora", borrar stores viejos en el panel), y la nota de recuperación manual (borrar la fila de `vector_stores` si el store se borró en OpenAI).

- [ ] **Step 4: Stage**

```bash
git add config/services.php .env.example docs/superpowers
```
