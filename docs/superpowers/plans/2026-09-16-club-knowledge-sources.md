# Fuentes de conocimiento por club — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el asistente indexe el conocimiento de cada club en su propio vector store, lo traiga de la fuente de ese club (Pentaho para ccm, manifiesto `bi:knowledge` para vallealto) sin re-subir lo que no cambió, y recorte la búsqueda al área del rol cuando el rol es de una sola área.

**Architecture:** `ClubVectorStore` resuelve el store por `ClubName` y es el único lector del mapa de stores. Un contrato `KnowledgeSource` con dos drivers entrega `RemoteDocument`s con checksum; `ImportKnowledgeDocuments` recorre los clubes configurados y solo crea filas pendientes cuando el checksum cambió. `File` conoce su club por `project` y sube/borra en el store correcto; los agentes filtran `FileSearch` por store del club y grupos del rol.

**Tech Stack:** Laravel 12, `laravel/ai` ^0.7 (`Stores`, `FileSearch`, `FileSearchQuery`), Pest 3, Inertia + Vue 3 + shadcn-vue, MariaDB.

**Spec:** `docs/superpowers/specs/2026-09-16-club-knowledge-sources-design.md`

## Global Constraints

- **No commits, no push.** El usuario commitea él mismo. Cada tarea termina con `git add` de sus archivos y nada más.
- Rama de trabajo: `stage` del repo `~/Projects/ai-assistant`.
- Todo archivo PHP nuevo lleva `declare(strict_types=1);`, clases `final`, PHPDoc en español como los vecinos. Correr `vendor/bin/pint --dirty` antes de dar una tarea por cerrada.
- Tests con Pest (`it(...)`), en `tests/Feature/...`, con `RefreshDatabase` ya aplicado por `tests/Pest.php`. Correr solo los archivos tocados: `vendor/bin/pest tests/Feature/<ruta>`.
- Los nombres de archivo en `files.name` llevan el club como carpeta (`ccm/golf-output-123.md`, `vallealto/golf-live.md`); en disco viven en `docs/{name}`. Es la convención actual y NO cambia.
- Nunca leer `services.openai.vector_store_id`: al terminar la tarea 6 esa clave ya no existe.
- Todo string visible al usuario en español.

---

### Task 1: `ClubVectorStore` y el mapa de stores

**Files:**
- Modify: `config/services.php:39-42`
- Create: `app/Ai/Files/ClubVectorStore.php`
- Create: `app/Ai/Files/MissingClubVectorStore.php`
- Modify: `.env.example:75-78`
- Test: `tests/Feature/Files/ClubVectorStoreTest.php`

**Interfaces:**
- Produces: `ClubVectorStore::idFor(ClubName $club): string`, `ClubVectorStore::storeFor(ClubName $club): \Laravel\Ai\Store`, `ClubVectorStore::configured(): array<int, ClubName>`; `MissingClubVectorStore::forClub(ClubName $club): self`.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\Files\ClubVectorStore;
use App\Ai\Files\MissingClubVectorStore;

beforeEach(function () {
    config([
        'services.openai.vector_stores' => [
            'ccm' => 'vs_ccm12345678',
            'vallealto' => 'vs_va123456789',
            'terralta' => null,
        ],
    ]);
});

it('resolves the vector store of a club', function () {
    expect(app(ClubVectorStore::class)->idFor(ClubName::VALLEALTO))->toBe('vs_va123456789');
});

it('lists only the clubs that have a store configured', function () {
    expect(app(ClubVectorStore::class)->configured())->toBe([ClubName::CCM, ClubName::VALLEALTO]);
});

it('fails loudly when a club has no store', function () {
    app(ClubVectorStore::class)->idFor(ClubName::TERRALTA);
})->throws(MissingClubVectorStore::class, 'terralta');
```

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Files/ClubVectorStoreTest.php`
Expected: FAIL, `Class "App\Ai\Files\ClubVectorStore" not found`.

- [ ] **Step 3: Config y clases**

`config/services.php`, sustituir el bloque `openai`:

```php
    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        // Un vector store por club: la separación entre clubes es una frontera
        // dura, no un filtro. Lo lee solo App\Ai\Files\ClubVectorStore.
        'vector_stores' => [
            'ccm' => env('OPENAI_VECTOR_STORE_CCM'),
            'vallealto' => env('OPENAI_VECTOR_STORE_VALLEALTO'),
        ],
    ],
```

Deja `'vector_store_id' => env('OPENAI_VECTOR_STORE_ID'),` DENTRO del bloque por ahora, debajo de `vector_stores`; se quita en la tarea 6.

`app/Ai/Files/MissingClubVectorStore.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Files;

use RuntimeException;
use App\Enums\ClubName;

final class MissingClubVectorStore extends RuntimeException
{
    public static function forClub(ClubName $club): self
    {
        return new self("No hay vector store configurado para el club [{$club->value}] (services.openai.vector_stores).");
    }
}
```

`app/Ai/Files/ClubVectorStore.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Files;

use Laravel\Ai\Store;
use Laravel\Ai\Stores;
use App\Enums\ClubName;

/**
 * Único punto que sabe qué vector store de OpenAI pertenece a cada club. Nadie
 * más lee `services.openai.vector_stores`: así ningún camino de código puede
 * tocar un store sin decir de qué club es.
 */
final class ClubVectorStore
{
    public function idFor(ClubName $club): string
    {
        $id = config("services.openai.vector_stores.{$club->value}");

        if (! is_string($id) || trim($id) === '') {
            throw MissingClubVectorStore::forClub($club);
        }

        return $id;
    }

    public function storeFor(ClubName $club): Store
    {
        return Stores::get($this->idFor($club));
    }

    /**
     * Clubes con store configurado, en el orden del mapa.
     *
     * @return array<int, ClubName>
     */
    public function configured(): array
    {
        return collect((array) config('services.openai.vector_stores'))
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->keys()
            ->map(fn (string $club) => ClubName::tryFrom($club))
            ->filter()
            ->values()
            ->all();
    }
}
```

`.env.example`, sustituir las dos líneas del vector store:

```
# Un vector store por club (RAG · File Search). El store actual es el de ccm.
OPENAI_VECTOR_STORE_CCM="<openai-vector-store-id>"
OPENAI_VECTOR_STORE_VALLEALTO="<openai-vector-store-id>"
```

- [ ] **Step 4: Correr el test y ver que pasa**

Run: `vendor/bin/pest tests/Feature/Files/ClubVectorStoreTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add config/services.php app/Ai/Files/ClubVectorStore.php app/Ai/Files/MissingClubVectorStore.php .env.example tests/Feature/Files/ClubVectorStoreTest.php
```

---

### Task 2: `File` sabe su club; columnas `checksum` y `origin`

**Files:**
- Create: `database/migrations/2026_09_16_100000_add_checksum_and_origin_to_files_table.php`
- Create: `app/Enums/SourceOrigin.php`
- Modify: `app/Models/File.php`
- Modify: `database/factories/FileFactory.php`
- Modify: `tests/Feature/Files/UploadAssistantDocTest.php:13-17`
- Modify: `tests/Feature/Files/RemoveExpiredDocsTest.php:10-13`
- Test: `tests/Feature/Files/FileClubScopeTest.php`

**Interfaces:**
- Consumes: `ClubVectorStore::storeFor(ClubName)`.
- Produces: `SourceOrigin` enum (`Pentaho='pentaho'`, `BiKnowledge='bi_knowledge'`, `Manual='manual'`); `File::club(): ClubName`; `File::expireSiblings()` acotado; columnas `files.checksum` (string 64 nullable) y `files.origin` (string 20, default `pentaho`); `FileFactory::forClub(ClubName)`, `FileFactory::fromManifest(string $name)`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Files/FileClubScopeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Enums\SourceOrigin;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Stores::fake();
    config(['services.openai.vector_stores' => ['ccm' => 'vs_ccm', 'vallealto' => 'vs_va']]);
});

it('knows its club from the project column', function () {
    expect(File::factory()->forClub(ClubName::VALLEALTO)->make()->club())->toBe(ClubName::VALLEALTO);
});

it('uploads to the store of its own club', function () {
    $file = File::factory()->forClub(ClubName::VALLEALTO)->create();
    Storage::put('docs/'.$file->name, '# va');

    $file->upload();

    Files::assertStored(fn ($stored) => true);
    expect($file->fresh()->status->value)->toBe('completed');
});

it('expires only older siblings of the same club, group and name', function () {
    $otherClub = File::factory()->forClub(ClubName::CCM)->fromManifest('golf-live.md')->completed()->create();
    $otherName = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-annual.md')->completed()->create();
    $older = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create();
    $newer = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create();

    $newer->expireSiblings();

    expect($older->fresh()->expired_at)->not->toBeNull()
        ->and($otherName->fresh()->expired_at)->toBeNull()
        ->and($otherClub->fresh()->expired_at)->toBeNull();
});

it('expires pentaho siblings by club and group because their name changes every run', function () {
    $older = File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf', 'name' => 'ccm/golf-output-100.md']);
    $otherClub = File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'golf', 'name' => 'vallealto/golf-output-100.md']);
    $newer = File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf', 'name' => 'ccm/golf-output-200.md']);

    $newer->expireSiblings();

    expect($older->fresh()->expired_at)->not->toBeNull()
        ->and($otherClub->fresh()->expired_at)->toBeNull()
        ->and($newer->origin)->toBe(SourceOrigin::Pentaho);
});
```

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Files/FileClubScopeTest.php`
Expected: FAIL, `Call to undefined method ... forClub()` / `club()`.

- [ ] **Step 3: Enum, migración, modelo y factory**

`app/Enums/SourceOrigin.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde salió un documento de `files`. Decide cómo se caducan los hermanos
 * y qué etiqueta lleva en Fuentes.
 */
enum SourceOrigin: string
{
    case Pentaho = 'pentaho';
    case BiKnowledge = 'bi_knowledge';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Pentaho => 'Pentaho',
            self::BiKnowledge => 'BI del club',
            self::Manual => 'Manual',
        };
    }
}
```

Migración `database/migrations/2026_09_16_100000_add_checksum_and_origin_to_files_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('bytes')->comment('sha256 del contenido');
            $table->string('origin', 20)->default('pentaho')->after('group');
            $table->index(['project', 'group', 'expired_at'], 'files_project_group_expired_index');
        });

        // Las subidas manuales guardaban project = 'manual'; el club es ccm,
        // el único que existía. Y el origen se deduce del nombre como hacía
        // AssistantSourcesQuery hasta ahora.
        DB::table('files')->where('project', 'manual')->update(['project' => 'ccm']);
        DB::table('files')->where('name', 'like', '%-manual-%')->update(['origin' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_project_group_expired_index');
            $table->dropColumn(['checksum', 'origin']);
        });
    }
};
```

`app/Models/File.php`, cambios:

1. Imports nuevos: `use App\Enums\ClubName;`, `use App\Enums\SourceOrigin;`, `use App\Ai\Files\ClubVectorStore;`.
2. Añadir al `casts()`: `'origin' => SourceOrigin::class,`.
3. Añadir método:

```php
    public function club(): ClubName
    {
        return ClubName::from((string) $this->project);
    }
```

4. Sustituir `expireSiblings()`:

```php
    /**
     * Caduca los documentos MÁS VIEJOS que este una vez que quedó indexado, y
     * solo dentro de su club. Para el manifiesto del BI el nombre es estable
     * (`vallealto/golf-live.md`) así que se acota también por nombre: golf-live
     * y golf-annual del mismo grupo conviven. Pentaho cambia el nombre en cada
     * corrida, ahí basta club + grupo. Solo los más viejos: si dos pendientes
     * se suben a la vez no se caducan mutuamente.
     */
    public function expireSiblings(): void
    {
        $query = self::query()
            ->where('id', '<', $this->id)
            ->where('project', $this->project)
            ->where('group', $this->group)
            ->whereNull('expired_at');

        if ($this->origin !== SourceOrigin::Pentaho) {
            $query->where('name', $this->name);
        }

        $query->update(['expired_at' => now()]);
    }
```

5. En `upload()`, sustituir `$store = Stores::get(config('services.openai.vector_store_id'));` por `$store = app(ClubVectorStore::class)->storeFor($this->club());`.
6. En `removeFromProvider()`, sustituir `Stores::get(config('services.openai.vector_store_id'))` por `app(ClubVectorStore::class)->storeFor($this->club())`.
7. Quitar `use Laravel\Ai\Stores;` si ya no se usa.
8. `fromPentaho()` se borra en la tarea 9; no tocarlo aquí.

`database/factories/FileFactory.php`: añadir `'origin' => SourceOrigin::Pentaho, 'checksum' => null,` a `definition()` (import `App\Enums\SourceOrigin` y `App\Enums\ClubName`) y dos estados:

```php
    public function forClub(ClubName $club): self
    {
        return $this->state(fn (array $attributes) => [
            'project' => $club->value,
            'name' => $club->value.'/'.basename((string) $attributes['name']),
        ]);
    }

    /**
     * Documento del manifiesto `bi:knowledge`: nombre estable con el club como
     * carpeta y checksum del contenido.
     */
    public function fromManifest(string $name): self
    {
        return $this->state(fn (array $attributes) => [
            'origin' => SourceOrigin::BiKnowledge,
            'group' => Str::before(basename($name), '-'),
            'name' => $attributes['project'].'/'.$name,
            'checksum' => hash('sha256', $name.fake()->unique()->numberBetween(1, 1_000_000)),
        ]);
    }
```

(import `Illuminate\Support\Str`). Ojo: en `fromManifest` el `project` viene del estado anterior, así que `forClub()` va SIEMPRE antes de `fromManifest()` en los tests.

En `tests/Feature/Files/UploadAssistantDocTest.php` y `tests/Feature/Files/RemoveExpiredDocsTest.php` sustituir `'services.openai.vector_store_id' => 'vs_test'` por `'services.openai.vector_stores.ccm' => 'vs_test'`.

- [ ] **Step 4: Migrar y correr los tests**

Run: `php artisan migrate --no-interaction && vendor/bin/pest tests/Feature/Files/FileClubScopeTest.php tests/Feature/Files/UploadAssistantDocTest.php tests/Feature/Files/RemoveExpiredDocsTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_09_16_100000_add_checksum_and_origin_to_files_table.php app/Enums/SourceOrigin.php app/Models/File.php database/factories/FileFactory.php tests/Feature/Files/FileClubScopeTest.php tests/Feature/Files/UploadAssistantDocTest.php tests/Feature/Files/RemoveExpiredDocsTest.php
```

---

### Task 3: Inventario y reconciliación por club

**Files:**
- Modify: `app/Ai/Files/OpenAiFileInventory.php:42-56`
- Modify: `app/Actions/Files/ReconcileAssistantFilesAction.php`
- Modify: `app/Console/Commands/ReconcileAssistantFiles.php`
- Modify: `tests/Feature/Files/ReconcileAssistantFilesTest.php`
- Modify: `tests/Feature/Files/OpenAiFileInventoryTest.php`

**Interfaces:**
- Consumes: `ClubVectorStore::idFor/storeFor`, `File::club()`.
- Produces: `OpenAiFileInventory::storeFiles(ClubName $club)`, `ReconcileAssistantFilesAction::report(ClubName $club, bool $includeUntagged = false)`, `ReconcileAssistantFilesAction::apply(ClubName $club, ReconciliationReport $report): int`, opción `--club=` del comando (default `ccm`).

- [ ] **Step 1: Añadir el test que falla**

Al final de `tests/Feature/Files/ReconcileAssistantFilesTest.php`:

```php
it('ignores the rows and files of another club', function () {
    fakeOpenAiInventory();
    File::factory()->completed()->create(['name' => 'ccm/golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);
    // Fila de vallealto que apunta al id que en el store de ccm sería huérfano:
    // no debe contar como referenciado desde el reporte de ccm.
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['assistant_media_id' => 'file-orphan']);

    $report = app(ReconcileAssistantFilesAction::class)->report(ClubName::CCM);

    expect($report->referenced)->toBe(1)
        ->and($report->orphans->pluck('id')->all())->toContain('file-orphan');
});
```

(añadir `use App\Enums\ClubName;` arriba). En el `beforeEach` sustituir `'services.openai.vector_store_id' => 'vs_test'` por `'services.openai.vector_stores.ccm' => 'vs_test'`. En `tests/Feature/Files/OpenAiFileInventoryTest.php` hacer el mismo cambio de clave, pasar `ClubName::CCM` a cada llamada `storeFiles()` y, si el test hace `new OpenAiFileInventory`, cambiarlo por `app(OpenAiFileInventory::class)` (ahora recibe `ClubVectorStore` por constructor).

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Files/ReconcileAssistantFilesTest.php`
Expected: FAIL, `report(): Argument #1 must be of type bool`.

- [ ] **Step 3: Implementar**

`OpenAiFileInventory::storeFiles`:

```php
    public function storeFiles(ClubName $club): Collection
    {
        $storeId = $this->stores->idFor($club);
        // resto igual
```

con constructor `public function __construct(private readonly ClubVectorStore $stores) {}` e imports `App\Enums\ClubName`, `App\Ai\Files\ClubVectorStore` (misma carpeta: solo `ClubName`).

`ReconcileAssistantFilesAction`:

- Constructor: `public function __construct(private readonly OpenAiFileInventory $inventory, private readonly ClubVectorStore $stores) {}`.
- `report(ClubName $club, bool $includeUntagged = false)`: `$referenced = File::query()->where('project', $club->value)->whereNotNull('assistant_media_id')->pluck('assistant_media_id')->flip();` y `$allStoreFiles = $this->inventory->storeFiles($club)->keyBy('id');`. Resto igual.
- `apply(ClubName $club, ReconciliationReport $report)`: `$store = $this->stores->storeFor($club);`. Resto igual.
- Actualizar el PHPDoc de clase: "cada club tiene su store; el reporte y el borrado se acotan al club recibido".

`ReconcileAssistantFiles` (comando): añadir `{--club=ccm : Club cuyo store se reconcilia}` a la firma, resolver `$club = ClubName::from((string) $this->option('club'));` y pasarlo a `report()` y `apply()`. Imprimir `"Club: {$club->value}"` en la primera línea.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Files/ReconcileAssistantFilesTest.php tests/Feature/Files/OpenAiFileInventoryTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Ai/Files/OpenAiFileInventory.php app/Actions/Files/ReconcileAssistantFilesAction.php app/Console/Commands/ReconcileAssistantFiles.php tests/Feature/Files/ReconcileAssistantFilesTest.php tests/Feature/Files/OpenAiFileInventoryTest.php
```

---

### Task 4: Roles con las claves del BI y grupo Bienestar

**Files:**
- Modify: `app/Enums/RoleName.php`
- Modify: `app/Enums/SourceGroup.php`
- Modify: `tests/Feature/Ai/ClubAssistantContextTest.php`
- Modify: `tests/Feature/Auth/ExternalAuthenticationTest.php`

**Interfaces:**
- Produces: casos `RoleName::RESTAURANT_MANAGER`, `FUTBOL_MANAGER`, `INCIDENCES_MANAGER`, `GUESTS_MANAGER`, `WELLNESS_MANAGER`; `RoleName::sourceGroups(): array<int, string>`; casos `SourceGroup::Wellness`, `Futbol`, `Incidences`, `Guests`.

- [ ] **Step 1: Escribir los tests que fallan**

Añadir a `tests/Feature/Ai/ClubAssistantContextTest.php`:

```php
it('names the roles after the BI report keys and keeps the old ones as aliases', function (): void {
    expect(RoleName::RESTAURANT_MANAGER->value)->toBe('restaurant_manager')
        ->and(RoleName::FUTBOL_MANAGER->label())->toBe('Gerencia de fútbol')
        ->and(RoleName::INCIDENCES_MANAGER->label())->toBe('Gerencia de incidencias')
        ->and(RoleName::GUESTS_MANAGER->label())->toBe('Gerencia de invitados')
        ->and(RoleName::WELLNESS_MANAGER->label())->toBe('Gerencia de bienestar')
        ->and(RoleName::RESTAURANT_CAPTAIN->value)->toBe('restaurant_captain');
});

it('narrows single-area roles to their source groups and leaves the rest open', function (): void {
    expect(RoleName::ADMIN->sourceGroups())->toBe([])
        ->and(RoleName::GENERAL_SERVICE_MANAGER->sourceGroups())->toBe([])
        ->and(RoleName::GOLF_MANAGER->sourceGroups())->toBe(['golf'])
        ->and(RoleName::RESTAURANT_MANAGER->sourceGroups())->toBe(['restaurant'])
        ->and(RoleName::RESTAURANT_CAPTAIN->sourceGroups())->toBe(['restaurant'])
        ->and(RoleName::FUTBOL_MANAGER->sourceGroups())->toBe(['futbol'])
        ->and(RoleName::INCIDENCES_MANAGER->sourceGroups())->toBe(['incidences'])
        ->and(RoleName::GUESTS_MANAGER->sourceGroups())->toBe(['guests'])
        ->and(RoleName::WELLNESS_MANAGER->sourceGroups())->toBe(['wellness'])
        ->and(RoleName::AESTHETICS_MANAGER->sourceGroups())->toBe(['wellness', 'aesthetic'])
        ->and(RoleName::MASSAGE_MANAGER->sourceGroups())->toBe(['wellness', 'massage'])
        ->and(RoleName::PODIATRY_MANAGER->sourceGroups())->toBe(['wellness']);
});

it('labels the wellness and BI groups in Spanish', function (): void {
    expect(SourceGroup::labelFor('wellness'))->toBe('Bienestar')
        ->and(SourceGroup::labelFor('futbol'))->toBe('Fútbol')
        ->and(SourceGroup::labelFor('incidences'))->toBe('Incidencias')
        ->and(SourceGroup::labelFor('guests'))->toBe('Invitados');
});
```

(import `App\Enums\SourceGroup`). En `tests/Feature/Auth/ExternalAuthenticationTest.php` añadir:

```php
it('accepts every role the clubs derive from their BI report keys', function (string $role): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->get('/?'.http_build_query(signedLinkParams(['role' => $role])))
        ->assertRedirect(route('chats.index'));

    expect(User::sole()->roles()->sole()->name)->toBe($role);
})->with(['restaurant_manager', 'futbol_manager', 'incidences_manager', 'guests_manager', 'wellness_manager']);
```

- [ ] **Step 2: Correrlos y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Auth/ExternalAuthenticationTest.php`
Expected: FAIL, `Undefined constant RoleName::RESTAURANT_MANAGER`.

- [ ] **Step 3: Implementar**

`app/Enums/RoleName.php`: añadir los casos después de `RESTAURANT_CAPTAIN`:

```php
    case RESTAURANT_MANAGER = 'restaurant_manager';

    case FUTBOL_MANAGER = 'futbol_manager';

    case INCIDENCES_MANAGER = 'incidences_manager';

    case GUESTS_MANAGER = 'guests_manager';

    case WELLNESS_MANAGER = 'wellness_manager';
```

En `label()`: `RESTAURANT_MANAGER => 'Gerencia de restaurante'`, `FUTBOL_MANAGER => 'Gerencia de fútbol'`, `INCIDENCES_MANAGER => 'Gerencia de incidencias'`, `GUESTS_MANAGER => 'Gerencia de invitados'`, `WELLNESS_MANAGER => 'Gerencia de bienestar'`.

En `areas()`: `RESTAURANT_MANAGER => ['Restaurantes']`, `FUTBOL_MANAGER => ['Fútbol']`, `INCIDENCES_MANAGER => ['Incidencias']`, `GUESTS_MANAGER => ['Invitados']`, `WELLNESS_MANAGER => ['Bienestar']`; y a `ADMIN` sumarle `'Fútbol', 'Incidencias', 'Invitados', 'Bienestar'`.

En `getName()`: `RESTAURANT_MANAGER => 'Gerente de Restaurante'`, `FUTBOL_MANAGER => 'Gerente de Fútbol'`, `INCIDENCES_MANAGER => 'Gerente de Incidencias'`, `GUESTS_MANAGER => 'Gerente de Invitados'`, `WELLNESS_MANAGER => 'Gerente de Bienestar'`.

Método nuevo:

```php
    /**
     * Grupos de fuentes a los que se recorta la búsqueda del asistente. Vacío
     * significa "todo el store del club": la dirección general y el rol
     * multi-área que manda vallealto (`general_service_manager`) no se
     * recortan. Estética, masaje y podología viven hoy dentro del documento de
     * bienestar; llevan además su grupo viejo mientras ccm siga en Pentaho.
     *
     * @return array<int, string>
     */
    public function sourceGroups(): array
    {
        return match ($this) {
            self::ADMIN, self::GENERAL_SERVICE_MANAGER => [],
            self::GOLF_MANAGER => ['golf'],
            self::TENNIS_MANAGER => ['tennis'],
            self::PADDLE_MANAGER => ['paddle'],
            self::RESTAURANT_MANAGER, self::RESTAURANT_CAPTAIN => ['restaurant'],
            self::FUTBOL_MANAGER => ['futbol'],
            self::INCIDENCES_MANAGER => ['incidences'],
            self::GUESTS_MANAGER => ['guests'],
            self::WELLNESS_MANAGER, self::PODIATRY_MANAGER => ['wellness'],
            self::AESTHETICS_MANAGER => ['wellness', 'aesthetic'],
            self::MASSAGE_MANAGER => ['wellness', 'massage'],
        };
    }
```

`app/Enums/SourceGroup.php`: añadir casos `Wellness = 'wellness'`, `Futbol = 'futbol'`, `Incidences = 'incidences'`, `Guests = 'guests'` y sus etiquetas `'Bienestar'`, `'Fútbol'`, `'Incidencias'`, `'Invitados'`. Actualizar el PHPDoc de `Services`, `Massage`, `Aesthetic`, `Experience`: "solo etiquetan filas viejas de Pentaho; hoy son categorías de bienestar". `RoleSeeder` no cambia: itera `RoleName::cases()`.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Auth/ExternalAuthenticationTest.php tests/Feature/Models/UserRolesTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Enums/RoleName.php app/Enums/SourceGroup.php tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Auth/ExternalAuthenticationTest.php
```

---

### Task 5: Los agentes buscan en el store del club y recortan por rol

**Files:**
- Modify: `app/Ai/Agents/ClubAssistant.php:88-97`
- Modify: `app/Ai/Agents/ModuleReportAnalyst.php:20-26,64-72`
- Modify: `app/Actions/Reports/GenerateWeeklyReportAction.php:37,58-68,74-78`
- Modify: `tests/Feature/Ai/ClubAssistantContextTest.php`
- Modify: `tests/Feature/Reports/WeeklyReportTest.php`

**Interfaces:**
- Consumes: `ClubVectorStore::idFor`, `RoleName::sourceGroups()`.
- Produces: `new ModuleReportAnalyst(ClubName $club, string $group)`.

- [ ] **Step 1: Escribir los tests que fallan**

Añadir a `tests/Feature/Ai/ClubAssistantContextTest.php` (imports: `Laravel\Ai\Providers\Tools\FileSearch`, `Laravel\Ai\Providers\Tools\WebSearch`):

```php
function fileSearchOf(iterable $tools): ?FileSearch
{
    foreach ($tools as $tool) {
        if ($tool instanceof FileSearch) {
            return $tool;
        }
    }

    return null;
}

it('searches the store of the user club and narrows a single-area role to its groups', function (): void {
    config(['services.openai.vector_stores' => ['ccm' => 'vs_ccm', 'vallealto' => 'vs_va']]);

    $search = fileSearchOf(ClubAssistant::make(club: ClubName::VALLEALTO, role: RoleName::MASSAGE_MANAGER)->tools());

    expect($search->ids())->toBe(['vs_va'])
        ->and($search->filters)->toBe([['type' => 'in', 'key' => 'group', 'value' => ['wellness', 'massage']]]);
});

it('does not filter by group for the admin', function (): void {
    config(['services.openai.vector_stores' => ['ccm' => 'vs_ccm']]);

    $search = fileSearchOf(ClubAssistant::make(club: ClubName::CCM, role: RoleName::ADMIN)->tools());

    expect($search->ids())->toBe(['vs_ccm'])
        ->and($search->filters)->toBe([]);
});

it('offers no file search when the user has no club', function (): void {
    $tools = iterator_to_array(ClubAssistant::make()->tools());

    expect(fileSearchOf($tools))->toBeNull()
        ->and($tools[0])->toBeInstanceOf(WebSearch::class);
});
```

En `tests/Feature/Reports/WeeklyReportTest.php`, en el test `generates report data from indexed modules, skipping empty ones`, añadir antes del `ModuleReportAnalyst::fake` una fila de otro club que NO debe generar sección:

```php
    File::create(['name' => 'vallealto/golf-live.md', 'group' => 'golf', 'status' => 'completed', 'project' => 'vallealto']);
```

y un test nuevo:

```php
it('grounds each module on the store of the report club', function (): void {
    config(['services.openai.vector_stores' => ['vallealto' => 'vs_va']]);

    $search = collect((new ModuleReportAnalyst(ClubName::VALLEALTO, 'golf'))->tools())->first();

    expect($search->ids())->toBe(['vs_va'])
        ->and($search->filters)->toBe([['type' => 'eq', 'key' => 'group', 'value' => 'golf']]);
});
```

- [ ] **Step 2: Correrlos y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Reports/WeeklyReportTest.php`
Expected: FAIL (`ids()` sobre `null`, y `ModuleReportAnalyst::__construct` con un argumento de más).

- [ ] **Step 3: Implementar**

`ClubAssistant::tools()`:

```php
    /**
     * Herramientas del agente. La búsqueda de documentos va SIEMPRE al store
     * del club del usuario (frontera dura entre clubes) y, si el rol es de una
     * sola área, se recorta a sus grupos. Sin club no hay documentos que
     * buscar: solo queda la web.
     */
    public function tools(): iterable
    {
        if ($this->club === null) {
            return [new WebSearch];
        }

        $store = app(ClubVectorStore::class)->idFor($this->club);
        $groups = $this->role?->sourceGroups() ?? [];

        return [
            $groups === []
                ? new FileSearch(stores: [$store])
                : new FileSearch(stores: [$store], where: fn (FileSearchQuery $query) => $query->whereIn('group', $groups)),
            new WebSearch,
        ];
    }
```

Imports: `App\Ai\Files\ClubVectorStore`, `Laravel\Ai\Providers\Tools\FileSearchQuery`.

`ModuleReportAnalyst`: constructor `public function __construct(protected ClubName $club, protected string $group) {}` (import `App\Enums\ClubName`, `App\Ai\Files\ClubVectorStore`) y `tools()`:

```php
    public function tools(): iterable
    {
        return [
            new FileSearch(
                stores: [app(ClubVectorStore::class)->idFor($this->club)],
                where: ['group' => $this->group],
            ),
        ];
    }
```

`GenerateWeeklyReportAction`: `indexedGroups(ClubName $club)` añade `->where('project', $club->value)`; el bucle pasa `$this->indexedGroups($club)`; `analyseModule(ClubName $club, string $group)` construye `new ModuleReportAnalyst($club, $group)`.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Ai tests/Feature/Reports tests/Feature/Http/Controllers/ChatStreamControllerTest.php`
Expected: PASS. Si `ChatStreamControllerTest` revienta con `MissingClubVectorStore`, su `beforeEach` necesita `config(['services.openai.vector_stores.ccm' => 'vs_test'])` y el usuario un `club_name` de `ccm`; añadirlo.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Ai/Agents/ClubAssistant.php app/Ai/Agents/ModuleReportAnalyst.php app/Actions/Reports/GenerateWeeklyReportAction.php tests/Feature/Ai/ClubAssistantContextTest.php tests/Feature/Reports/WeeklyReportTest.php tests/Feature/Http/Controllers/ChatStreamControllerTest.php
```

---

### Task 6: Salud de Fuentes por club y adiós a `vector_store_id`

**Files:**
- Modify: `app/Queries/AssistantSourcesHealthQuery.php`
- Modify: `config/services.php` (quitar `vector_store_id`)
- Modify: `tests/Feature/Sources/SourcesPageTest.php:54-70`

**Interfaces:**
- Consumes: `ClubVectorStore::idFor`.
- Produces: `AssistantSourcesHealthQuery::execute(ClubName $club): AssistantSourcesHealth`.

- [ ] **Step 1: Ajustar el test**

En `SourcesPageTest`, test `summarises the health of the sources`: sustituir `config(['services.openai.vector_store_id' => 'vs_abcdefgh12345678']);` por `config(['services.openai.vector_stores.ccm' => 'vs_abcdefgh12345678']);`. Añadir:

```php
it('reports no store when the club has none configured', function () {
    config(['services.openai.vector_stores' => ['ccm' => null]]);

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('health.vector_store_suffix', null));
});
```

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Sources/SourcesPageTest.php --filter=health`
Expected: FAIL en `vector_store_suffix`.

- [ ] **Step 3: Implementar**

`AssistantSourcesHealthQuery`: constructor `public function __construct(private readonly ClubVectorStore $stores) {}`; `execute(ClubName $club)` añade `->where('project', $club->value)` a `$totals` y a `File::expired()->count()`; `$sync = (array) config('knowledge.sync');` (esa clave nace en la tarea 8; hasta entonces dejar `services.softok2mds.sync`, y cambiarla en la tarea 8). `vectorStoreSuffix(ClubName $club)`:

```php
    private function vectorStoreSuffix(ClubName $club): ?string
    {
        try {
            return mb_substr($this->stores->idFor($club), -8);
        } catch (MissingClubVectorStore) {
            return null;
        }
    }
```

`AssistantSourcesController` pasa de momento `ClubName::CCM` a `$health->execute(...)`; la resolución real del club llega en la tarea 10.

`config/services.php`: borrar la línea `'vector_store_id' => env('OPENAI_VECTOR_STORE_ID'),`. Verificar con `grep -rn "vector_store_id" app config tests resources` que no queda ninguna lectura (solo `vector_store_suffix` en DTO/TS, que es otro nombre).

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Sources/SourcesPageTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Queries/AssistantSourcesHealthQuery.php app/Http/Controllers/AssistantSourcesController.php config/services.php tests/Feature/Sources/SourcesPageTest.php
```

---

### Task 7: Contrato `KnowledgeSource`, `RemoteDocument` y driver de Pentaho

**Files:**
- Create: `app/Ai/Sources/KnowledgeSource.php`
- Create: `app/Ai/Sources/RemoteDocument.php`
- Create: `app/Ai/Sources/Drivers/PentahoMdsSource.php`
- Test: `tests/Feature/Sources/Drivers/PentahoMdsSourceTest.php`

**Interfaces:**
- Produces: `interface KnowledgeSource { public function documents(): iterable; }`; `final readonly class RemoteDocument(string $name, string $group, string $checksum, SourceOrigin $origin, Closure $content)` con `content(): string`; `new PentahoMdsSource(string $baseUrl, string $username, string $password, array $files, string $project)`.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

use App\Enums\SourceOrigin;
use App\Ai\Sources\RemoteDocument;
use Illuminate\Support\Facades\Http;
use App\Ai\Sources\Drivers\PentahoMdsSource;

beforeEach(fn () => Http::preventStrayRequests());

function pentahoSource(): PentahoMdsSource
{
    return new PentahoMdsSource(
        baseUrl: 'https://mds.test/api/',
        username: 'u',
        password: 'p',
        files: ['golf-output', 'tennis-output'],
        project: 'ccm',
    );
}

it('yields one document per report with its group, a timestamped name and a computed checksum', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('# golf'),
        'https://mds.test/api/ccm/tennis-output.md' => Http::response('# tenis'),
    ]);

    $documents = collect(pentahoSource()->documents());

    expect($documents)->toHaveCount(2)
        ->and($documents[0])->toBeInstanceOf(RemoteDocument::class)
        ->and($documents[0]->group)->toBe('golf')
        ->and($documents[0]->name)->toMatch('/^golf-output-\d+\.md$/')
        ->and($documents[0]->origin)->toBe(SourceOrigin::Pentaho)
        ->and($documents[0]->checksum)->toBe(hash('sha256', '# golf'))
        ->and($documents[0]->content())->toBe('# golf');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('u:p')));
});

it('skips a report that fails to download and keeps the others', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('', 500),
        'https://mds.test/api/ccm/tennis-output.md' => Http::response('# tenis'),
    ]);

    $documents = collect(pentahoSource()->documents());

    expect($documents)->toHaveCount(1)
        ->and($documents[0]->group)->toBe('tennis');
});
```

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Sources/Drivers/PentahoMdsSourceTest.php`
Expected: FAIL, clase no encontrada.

- [ ] **Step 3: Implementar**

`app/Ai/Sources/KnowledgeSource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Sources;

/**
 * De dónde saca el asistente los documentos de un club. Cada club tiene un
 * driver (Pentaho de transición, manifiesto de `bi:knowledge`); el import no
 * sabe cuál es.
 */
interface KnowledgeSource
{
    /**
     * Documentos disponibles ahora mismo. Un documento que no se pudo leer se
     * registra y se omite: nunca aborta el resto.
     *
     * @return iterable<int, RemoteDocument>
     */
    public function documents(): iterable;
}
```

`app/Ai/Sources/RemoteDocument.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Sources;

use Closure;
use App\Enums\SourceOrigin;

/**
 * Un documento tal como lo describe su fuente. `content` es perezoso: el
 * manifiesto del BI trae el checksum sin el cuerpo, y solo se descarga lo que
 * de verdad cambió.
 */
final readonly class RemoteDocument
{
    /**
     * @param  string  $name  nombre de archivo SIN carpeta de club (`golf-live.md`)
     * @param  string  $checksum  sha256 hex del contenido
     * @param  Closure(): string  $content
     */
    public function __construct(
        public string $name,
        public string $group,
        public string $checksum,
        public SourceOrigin $origin,
        private Closure $content,
    ) {}

    public function content(): string
    {
        return ($this->content)();
    }
}
```

`app/Ai/Sources/Drivers/PentahoMdsSource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Sources\Drivers;

use Throwable;
use App\Enums\SourceOrigin;
use App\Ai\Sources\RemoteDocument;
use App\Ai\Sources\KnowledgeSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Driver de transición: los reportes `.md` del MDS de Pentaho. El MDS no
 * publica checksum, así que se baja el cuerpo y se calcula aquí; el nombre
 * lleva timestamp porque el MDS siempre sirve el mismo nombre.
 */
final class PentahoMdsSource implements KnowledgeSource
{
    /**
     * @param  array<int, string>  $files  nombres sin extensión (`golf-output`)
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly array $files,
        private readonly string $project,
    ) {}

    public function documents(): iterable
    {
        foreach ($this->files as $fileName) {
            $path = $this->project.'/'.$fileName;

            try {
                $body = Http::retry(3, 100)
                    ->withBasicAuth($this->username, $this->password)
                    ->get($this->baseUrl.$path.'.md')
                    ->throw()
                    ->body();
            } catch (Throwable $e) {
                Log::error("Pentaho: no se pudo bajar {$path}: ".$e->getMessage());

                continue;
            }

            yield new RemoteDocument(
                name: $fileName.'-'.time().'.md',
                group: (string) str($fileName)->before('-'),
                checksum: hash('sha256', $body),
                origin: SourceOrigin::Pentaho,
                content: fn (): string => $body,
            );
        }
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/pest tests/Feature/Sources/Drivers/PentahoMdsSourceTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Ai/Sources tests/Feature/Sources/Drivers/PentahoMdsSourceTest.php
```

---

### Task 8: Driver del manifiesto `bi:knowledge`, config `knowledge.php` y factory

**Files:**
- Create: `app/Ai/Sources/Drivers/BiKnowledgeManifestSource.php`
- Create: `app/Ai/Sources/KnowledgeSourceFactory.php`
- Create: `config/knowledge.php`
- Modify: `config/services.php` (borrar `softok2mds`)
- Modify: `bootstrap/app.php:44-46`
- Modify: `app/Queries/AssistantSourcesHealthQuery.php` (clave `knowledge.sync`)
- Modify: `.env.example:92-96`
- Test: `tests/Feature/Sources/Drivers/BiKnowledgeManifestSourceTest.php`
- Test: `tests/Feature/Sources/KnowledgeSourceFactoryTest.php`

**Interfaces:**
- Produces: `new BiKnowledgeManifestSource(string $baseUrl, string $username, string $password)`; `KnowledgeSourceFactory::for(ClubName $club): ?KnowledgeSource`; `KnowledgeSourceFactory::clubs(): array<int, ClubName>`; config `knowledge.sources.{club}` y `knowledge.sync`.

- [ ] **Step 1: Escribir los tests que fallan**

`tests/Feature/Sources/Drivers/BiKnowledgeManifestSourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\SourceOrigin;
use Illuminate\Support\Facades\Http;
use App\Ai\Sources\Drivers\BiKnowledgeManifestSource;

beforeEach(fn () => Http::preventStrayRequests());

function manifestSource(): BiKnowledgeManifestSource
{
    return new BiKnowledgeManifestSource(baseUrl: 'https://va.test/bi/knowledge', username: 'bi', password: 'secret');
}

function fakeManifest(): void
{
    Http::fake([
        'https://va.test/bi/knowledge/manifest.json' => Http::response([
            'generated_at' => '2026-09-16T03:15:00-06:00',
            'documents' => [
                ['name' => 'golf-annual.md', 'group' => 'golf', 'title' => 'Golf anual', 'bytes' => 4500, 'checksum' => 'aaa', 'generated_at' => '2026-09-16T03:15:00-06:00'],
                ['name' => 'golf-live.md', 'group' => 'golf', 'title' => 'Golf vivo', 'bytes' => 8000, 'checksum' => 'bbb', 'generated_at' => '2026-09-16T03:15:00-06:00'],
            ],
        ]),
        'https://va.test/bi/knowledge/golf-live.md' => Http::response('# golf vivo'),
    ]);
}

it('describes every manifest document without downloading it', function () {
    fakeManifest();

    $documents = collect(manifestSource()->documents());

    expect($documents)->toHaveCount(2)
        ->and($documents[1]->name)->toBe('golf-live.md')
        ->and($documents[1]->group)->toBe('golf')
        ->and($documents[1]->checksum)->toBe('bbb')
        ->and($documents[1]->origin)->toBe(SourceOrigin::BiKnowledge);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/manifest.json') && $request->hasHeader('Authorization', 'Basic '.base64_encode('bi:secret')));
});

it('downloads the body only when asked', function () {
    fakeManifest();

    $live = collect(manifestSource()->documents())[1];

    expect($live->content())->toBe('# golf vivo');
    Http::assertSentCount(2);
});

it('yields nothing and logs when the manifest is down', function () {
    Http::fake(['https://va.test/bi/knowledge/manifest.json' => Http::response('', 503)]);

    expect(collect(manifestSource()->documents()))->toHaveCount(0);
});
```

`tests/Feature/Sources/KnowledgeSourceFactoryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\Sources\KnowledgeSourceFactory;
use App\Ai\Sources\Drivers\PentahoMdsSource;
use App\Ai\Sources\Drivers\BiKnowledgeManifestSource;

beforeEach(function () {
    config(['knowledge.sources' => [
        'ccm' => ['driver' => 'pentaho', 'base_url' => 'https://mds.test/api/', 'username' => 'u', 'password' => 'p', 'files' => ['golf-output']],
        'vallealto' => ['driver' => 'bi_knowledge', 'base_url' => 'https://va.test/bi/knowledge', 'username' => 'bi', 'password' => 's'],
    ]]);
});

it('builds the driver of each configured club', function () {
    $factory = app(KnowledgeSourceFactory::class);

    expect($factory->for(ClubName::CCM))->toBeInstanceOf(PentahoMdsSource::class)
        ->and($factory->for(ClubName::VALLEALTO))->toBeInstanceOf(BiKnowledgeManifestSource::class)
        ->and($factory->for(ClubName::TERRALTA))->toBeNull()
        ->and($factory->clubs())->toBe([ClubName::CCM, ClubName::VALLEALTO]);
});

it('rejects an unknown driver', function () {
    config(['knowledge.sources.ccm.driver' => 'ftp']);

    app(KnowledgeSourceFactory::class)->for(ClubName::CCM);
})->throws(InvalidArgumentException::class, 'ftp');
```

- [ ] **Step 2: Correrlos y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Sources/Drivers/BiKnowledgeManifestSourceTest.php tests/Feature/Sources/KnowledgeSourceFactoryTest.php`
Expected: FAIL, clases no encontradas.

- [ ] **Step 3: Implementar**

`app/Ai/Sources/Drivers/BiKnowledgeManifestSource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Sources\Drivers;

use Throwable;
use App\Enums\SourceOrigin;
use App\Ai\Sources\RemoteDocument;
use App\Ai\Sources\KnowledgeSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Manifiesto que publica `bi:knowledge` en el panel del club:
 * `GET {base_url}/manifest.json` describe cada documento con su sha256, y
 * `GET {base_url}/{name}` sirve el markdown. Solo se baja lo que el import
 * decide que cambió.
 */
final class BiKnowledgeManifestSource implements KnowledgeSource
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function documents(): iterable
    {
        try {
            $manifest = $this->client()->get($this->url('manifest.json'))->throw()->json();
        } catch (Throwable $e) {
            Log::error("BI knowledge: no se pudo leer el manifiesto de {$this->baseUrl}: ".$e->getMessage());

            return;
        }

        foreach ((array) ($manifest['documents'] ?? []) as $entry) {
            if (! isset($entry['name'], $entry['group'], $entry['checksum'])) {
                Log::warning('BI knowledge: entrada del manifiesto incompleta', ['entry' => $entry]);

                continue;
            }

            $name = (string) $entry['name'];

            yield new RemoteDocument(
                name: $name,
                group: (string) $entry['group'],
                checksum: (string) $entry['checksum'],
                origin: SourceOrigin::BiKnowledge,
                content: fn (): string => $this->client()->get($this->url($name))->throw()->body(),
            );
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.$path;
    }

    private function client(): PendingRequest
    {
        return Http::retry(3, 100)
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson()
            ->timeout(30);
    }
}
```

`app/Ai/Sources/KnowledgeSourceFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Sources;

use App\Enums\ClubName;
use InvalidArgumentException;
use App\Ai\Sources\Drivers\PentahoMdsSource;
use App\Ai\Sources\Drivers\BiKnowledgeManifestSource;

/**
 * Construye la fuente de cada club a partir de `config/knowledge.php`. Un club
 * sin entrada no tiene fuente (devuelve null): el import lo salta.
 */
final class KnowledgeSourceFactory
{
    public function for(ClubName $club): ?KnowledgeSource
    {
        $config = config("knowledge.sources.{$club->value}");

        if (! is_array($config)) {
            return null;
        }

        return match ($config['driver'] ?? null) {
            'pentaho' => new PentahoMdsSource(
                baseUrl: (string) $config['base_url'],
                username: (string) $config['username'],
                password: (string) $config['password'],
                files: array_values(array_filter((array) ($config['files'] ?? []))),
                project: $club->value,
            ),
            'bi_knowledge' => new BiKnowledgeManifestSource(
                baseUrl: (string) $config['base_url'],
                username: (string) $config['username'],
                password: (string) $config['password'],
            ),
            default => throw new InvalidArgumentException("Driver de fuente desconocido [{$config['driver']}] para el club [{$club->value}]."),
        };
    }

    /**
     * Clubes con fuente configurada, en el orden del mapa.
     *
     * @return array<int, ClubName>
     */
    public function clubs(): array
    {
        return collect((array) config('knowledge.sources'))
            ->keys()
            ->map(fn (string $club) => ClubName::tryFrom($club))
            ->filter()
            ->values()
            ->all();
    }
}
```

`config/knowledge.php`:

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fuentes de conocimiento por club
    |--------------------------------------------------------------------------
    |
    | Cada club trae sus documentos de un sitio distinto. `pentaho` es el driver
    | de transición (MDS de Softok2); `bi_knowledge` lee el manifiesto que
    | publica `bi:knowledge` en el panel del club. Cuando ccm migre, basta con
    | cambiar aquí su driver.
    |
    */

    'sources' => [
        'ccm' => [
            'driver' => 'pentaho',
            'base_url' => env('SOFTOK2MDS_BASE_URL', 'https://softok2mds.example.com/api/'),
            'username' => env('SOFTOK2MDS_USERNAME'),
            'password' => env('SOFTOK2MDS_PASSWORD'),
            'files' => explode(',', env('SOFTOK2MDS_FILES', 'golf-output')),
        ],
        'vallealto' => [
            'driver' => 'bi_knowledge',
            'base_url' => env('VALLEALTO_KNOWLEDGE_URL'),
            'username' => env('VALLEALTO_KNOWLEDGE_USERNAME'),
            'password' => env('VALLEALTO_KNOWLEDGE_PASSWORD'),
        ],
    ],

    // Cadencia de `assistant-files:sync`. La lee el scheduler en
    // bootstrap/app.php y la franja de salud de Fuentes del asistente.
    'sync' => [
        'cron' => '0 */2 * * *',
        'from' => '08:00',
        'to' => '22:00',
        'label' => 'Cada 2 h',
    ],

];
```

`config/services.php`: borrar el bloque `softok2mds` entero. `bootstrap/app.php`: `config('services.softok2mds.sync.cron')` → `config('knowledge.sync.cron')`, ídem `from` y `to`. `AssistantSourcesHealthQuery`: `config('knowledge.sync')`. Verificar con `grep -rn "softok2mds" app config bootstrap tests resources` que solo queda en tests que se reescriben en la tarea 9 (`ImportDocsFromPentahoTest`).

`.env.example`: sustituir el bloque de ingesta por:

```
# ─── Fuentes de conocimiento por club (config/knowledge.php) ────────────────────
# ccm (transición): reportes .md del MDS de Pentaho.
SOFTOK2MDS_BASE_URL="https://softok2mds.example.com/api/"
SOFTOK2MDS_USERNAME=""
SOFTOK2MDS_PASSWORD=""
SOFTOK2MDS_FILES="golf-output"
# vallealto: manifiesto de `bi:knowledge` del panel (mismas credenciales que BI_KNOWLEDGE_* allá).
VALLEALTO_KNOWLEDGE_URL="https://vallealto-admin-api.test/bi/knowledge"
VALLEALTO_KNOWLEDGE_USERNAME=""
VALLEALTO_KNOWLEDGE_PASSWORD=""
```

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Sources/Drivers tests/Feature/Sources/KnowledgeSourceFactoryTest.php tests/Feature/Sources/SourcesPageTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Ai/Sources config/knowledge.php config/services.php bootstrap/app.php app/Queries/AssistantSourcesHealthQuery.php .env.example tests/Feature/Sources/Drivers/BiKnowledgeManifestSourceTest.php tests/Feature/Sources/KnowledgeSourceFactoryTest.php
```

---

### Task 9: `ImportKnowledgeDocuments` con checksum

**Files:**
- Create: `app/Jobs/ImportKnowledgeDocuments.php`
- Delete: `app/Jobs/ImportDocsFromPentaho.php`
- Delete: `tests/Feature/Files/ImportDocsFromPentahoTest.php`
- Modify: `app/Models/File.php` (borrar `fromPentaho`)
- Modify: `app/Actions/Files/StartAssistantFilesSyncAction.php`
- Modify: `app/Console/Commands/SyncAssistantFiles.php:14` (descripción)
- Modify: `tests/Feature/Files/SyncAssistantFilesTest.php`, `tests/Feature/Sources/SourcesSyncTest.php`
- Test: `tests/Feature/Files/ImportKnowledgeDocumentsTest.php`

**Interfaces:**
- Consumes: `KnowledgeSourceFactory::clubs()/for()`, `RemoteDocument`, columnas `checksum`/`origin`.
- Produces: `ImportKnowledgeDocuments` (job encadenable, misma firma de `failed()` que el viejo).

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use App\Enums\MediaStatus;
use App\Enums\SourceOrigin;
use App\Jobs\ImportKnowledgeDocuments;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Http::preventStrayRequests();
    config(['knowledge.sources' => [
        'ccm' => ['driver' => 'pentaho', 'base_url' => 'https://mds.test/api/', 'username' => 'u', 'password' => 'p', 'files' => ['golf-output']],
        'vallealto' => ['driver' => 'bi_knowledge', 'base_url' => 'https://va.test/bi/knowledge', 'username' => 'bi', 'password' => 's'],
    ]]);
});

function fakeSources(string $liveChecksum = 'live-1', string $golfBody = '# golf ccm'): void
{
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response($golfBody),
        'https://va.test/bi/knowledge/manifest.json' => Http::response(['documents' => [
            ['name' => 'golf-live.md', 'group' => 'golf', 'checksum' => $liveChecksum],
            ['name' => 'golf-annual.md', 'group' => 'golf', 'checksum' => 'annual-1'],
        ]]),
        'https://va.test/bi/knowledge/golf-live.md' => Http::response('# vivo'),
        'https://va.test/bi/knowledge/golf-annual.md' => Http::response('# anual'),
    ]);
}

it('creates one pending row per document of every club, under the club folder', function () {
    fakeSources();

    (new ImportKnowledgeDocuments)->handle();

    $live = File::where('project', 'vallealto')->where('name', 'vallealto/golf-live.md')->sole();
    $ccm = File::where('project', 'ccm')->sole();

    expect(File::pending()->count())->toBe(3)
        ->and(File::where('project', 'vallealto')->pluck('name')->sort()->values()->all())->toBe(['vallealto/golf-annual.md', 'vallealto/golf-live.md'])
        ->and($live->checksum)->toBe('live-1')
        ->and($live->origin)->toBe(SourceOrigin::BiKnowledge)
        ->and($live->status)->toBe(MediaStatus::PENDING)
        ->and($ccm->name)->toStartWith('ccm/golf-output-')
        ->and($ccm->checksum)->toBe(hash('sha256', '# golf ccm'));

    Storage::assertExists('docs/vallealto/golf-live.md');
});

it('does nothing for a manifest document whose checksum did not change', function () {
    fakeSources();
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create(['checksum' => 'live-1']);
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-annual.md')->completed()->create(['checksum' => 'annual-1']);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('project', 'vallealto')->count())->toBe(2);
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/golf-live.md'));
});

it('creates a new pending row when the manifest checksum changed and keeps the current one alive', function () {
    fakeSources(liveChecksum: 'live-2');
    $current = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create(['checksum' => 'live-1']);
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-annual.md')->completed()->create(['checksum' => 'annual-1']);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('name', 'vallealto/golf-live.md')->count())->toBe(2)
        ->and(File::pending()->where('name', 'vallealto/golf-live.md')->sole()->checksum)->toBe('live-2')
        ->and($current->fresh()->expired_at)->toBeNull();
});

it('skips a pentaho report whose content did not change since the current one', function () {
    fakeSources();
    File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf', 'name' => 'ccm/golf-output-100.md', 'checksum' => hash('sha256', '# golf ccm')]);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('project', 'ccm')->count())->toBe(1);
});

it('keeps importing the other club when one source is down', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('# golf ccm'),
        'https://va.test/bi/knowledge/manifest.json' => Http::response('', 503),
    ]);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('project', 'ccm')->count())->toBe(1)
        ->and(File::where('project', 'vallealto')->count())->toBe(0);
});
```

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Files/ImportKnowledgeDocumentsTest.php`
Expected: FAIL, clase no encontrada.

- [ ] **Step 3: Implementar**

`app/Jobs/ImportKnowledgeDocuments.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\File;
use App\Enums\ClubName;
use App\Enums\MediaStatus;
use App\Enums\SourceOrigin;
use Illuminate\Bus\Queueable;
use App\Ai\Sources\RemoteDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Ai\Sources\KnowledgeSourceFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

/**
 * Trae los documentos de la fuente de cada club y deja pendientes SOLO los
 * que cambiaron (por sha256). No caduca nada: eso pasa al indexar el nuevo
 * (File::upload → expireSiblings), para que el store conserve el documento
 * anterior si la subida falla.
 */
final class ImportKnowledgeDocuments implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(KnowledgeSourceFactory $sources): void
    {
        foreach ($sources->clubs() as $club) {
            $source = $sources->for($club);

            if ($source === null) {
                continue;
            }

            try {
                foreach ($source->documents() as $document) {
                    $this->import($club, $document);
                }
            } catch (Throwable $e) {
                Log::error("Import de conocimiento del club {$club->value} falló: ".$e->getMessage());
            }
        }
    }

    private function import(ClubName $club, RemoteDocument $document): void
    {
        $name = $club->value.'/'.$document->name;

        if ($this->current($club, $document)?->checksum === $document->checksum) {
            return;
        }

        try {
            Storage::put('docs/'.$name, $document->content());
        } catch (Throwable $e) {
            Log::error("No se pudo bajar {$name}: ".$e->getMessage());

            return;
        }

        File::query()->create([
            'project' => $club->value,
            'group' => $document->group,
            'origin' => $document->origin,
            'name' => $name,
            'checksum' => $document->checksum,
            'status' => MediaStatus::PENDING,
        ]);
    }

    /**
     * El documento vigente contra el que se compara el checksum: por nombre
     * cuando el nombre es estable; por grupo para Pentaho, que renombra en
     * cada corrida.
     */
    private function current(ClubName $club, RemoteDocument $document): ?File
    {
        $query = File::active()->where('project', $club->value)->where('group', $document->group);

        if ($document->origin !== SourceOrigin::Pentaho) {
            $query->where('name', $club->value.'/'.$document->name);
        }

        return $query->latest('id')->first();
    }

    public function failed(?Throwable $exception): void
    {
        Cache::lock(SyncLock::KEY)->forceRelease();

        Log::error('Import de conocimiento falló: '.($exception?->getMessage() ?? 'sin excepción'));
    }
}
```

Nota sobre el `catch` externo: `documents()` es un generador; una excepción del driver salta ahí. Los drivers ya registran y omiten por su cuenta, así que ese `catch` es la red de seguridad.

Borrar `app/Jobs/ImportDocsFromPentaho.php` y `tests/Feature/Files/ImportDocsFromPentahoTest.php` (`git rm`). Borrar `File::fromPentaho()` y, si quedan sin uso, sus imports (`Str`). `StartAssistantFilesSyncAction`: `new ImportKnowledgeDocuments` en la cadena (import nuevo, quitar el viejo); PHPDoc: "importa de la fuente de cada club". `SyncAssistantFiles::$description`: `'Importa los documentos de cada club, indexa lo que cambió en su vector store y limpia los caducados.'`. En `SyncAssistantFilesTest` y `SourcesSyncTest` sustituir `ImportDocsFromPentaho` por `ImportKnowledgeDocuments`.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Files tests/Feature/Sources/SourcesSyncTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git rm -q app/Jobs/ImportDocsFromPentaho.php tests/Feature/Files/ImportDocsFromPentahoTest.php
git add app/Jobs/ImportKnowledgeDocuments.php app/Models/File.php app/Actions/Files/StartAssistantFilesSyncAction.php app/Console/Commands/SyncAssistantFiles.php tests/Feature/Files/ImportKnowledgeDocumentsTest.php tests/Feature/Files/SyncAssistantFilesTest.php tests/Feature/Sources/SourcesSyncTest.php
```

---

### Task 10: Fuentes por club en pantalla (backend)

**Files:**
- Create: `app/Actions/Files/ResolveSourcesClubAction.php`
- Modify: `app/Queries/AssistantSourcesQuery.php`
- Modify: `app/Http/Controllers/AssistantSourcesController.php`
- Modify: `app/Http/Controllers/AssistantSourcesManagementController.php`
- Modify: `app/Http/Requests/ReconcileSourceFilesRequest.php`, `app/Http/Requests/StoreSourceFileRequest.php`
- Modify: `app/Dtos/ManualSourceFileData.php`, `app/Actions/Files/StoreManualSourceFileAction.php`
- Modify: `app/Jobs/RemoveExpiredDocs.php`
- Modify: `tests/Feature/Sources/SourcesPageTest.php`, `SourcesFileUploadTest.php`, `SourcesReconcileTest.php`, `SourcesExpiredPurgeTest.php`

**Interfaces:**
- Consumes: `ClubVectorStore::configured()`, `KnowledgeSourceFactory::clubs()`, `ReconcileAssistantFilesAction::report(ClubName, bool)`.
- Produces: `ResolveSourcesClubAction::execute(?User $user, ?string $requested): ClubName`; props Inertia `club: string`, `clubs: array<int, array{value: string, label: string}>`, `clubLocked: bool`; parámetro `club` en `sources`, `sources.reconcile.report`, `sources.reconcile.apply`, `sources.files.store`, `sources.expired.purge`; `RemoveExpiredDocs(?string $project = null)`.

- [ ] **Step 1: Escribir los tests que fallan**

Añadir a `SourcesPageTest`:

```php
it('scopes the page to the club of an external admin and locks the selector', function () {
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'golf']);
    File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf']);
    $admin = adminUser();
    $admin->forceFill(['club_name' => 'vallealto'])->save();

    $this->actingAs($admin)
        ->get(route('sources', ['club' => 'ccm']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('club', 'vallealto')
            ->where('clubLocked', true)
            ->has('files', 1)
            ->where('files.0.name', fn ($name) => str_starts_with($name, 'vallealto/')));
});

it('lets a local admin without club pick one and defaults to the first configured', function () {
    config(['knowledge.sources' => ['ccm' => ['driver' => 'pentaho'], 'vallealto' => ['driver' => 'bi_knowledge']]]);
    File::factory()->forClub(ClubName::VALLEALTO)->completed()->create(['group' => 'golf']);

    $this->actingAs(adminUser())
        ->get(route('sources'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('club', 'ccm')
            ->where('clubLocked', false)
            ->where('clubs.1.value', 'vallealto')
            ->has('files', 0));

    $this->actingAs(adminUser())
        ->get(route('sources', ['club' => 'vallealto']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('club', 'vallealto')->has('files', 1));
});

it('labels the origin from the column instead of the file name', function () {
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create();

    $this->actingAs(adminUser())
        ->get(route('sources', ['club' => 'vallealto']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('files.0.origin', 'bi_knowledge'));
});
```

(import `App\Enums\ClubName`; el test viejo `labels the group and tells apart a pentaho report from a manual upload` pasa a crear las filas con `'origin' => SourceOrigin::Manual` en la segunda y se queda). Añadir a `SourcesFileUploadTest`: en el primer test enviar `'club' => 'ccm'` y esperar `$file->project` `'ccm'`, `$file->origin` `SourceOrigin::Manual` y `$file->checksum` no nulo. Nuevo:

```php
it('rejects a club the assistant does not serve', function () {
    $this->actingAs(adminUser())
        ->post(route('sources.files.store'), ['club' => 'marte', 'group' => 'golf', 'file' => UploadedFile::fake()->create('r.md', 1)])
        ->assertSessionHasErrors('club');
});
```

En `SourcesReconcileTest` y `SourcesExpiredPurgeTest`: añadir `'club' => 'ccm'` a las peticiones y la clave `services.openai.vector_stores.ccm` donde haya `vector_store_id`. En `SourcesExpiredPurgeTest` añadir:

```php
it('purges only the expired rows of the requested club', function () {
    Bus::fake();

    $this->actingAs(adminUser())->delete(route('sources.expired.purge'), ['club' => 'vallealto']);

    Bus::assertDispatched(RemoveExpiredDocs::class, fn (RemoveExpiredDocs $job) => $job->project === 'vallealto');
});
```

- [ ] **Step 2: Correrlos y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Sources`
Expected: FAIL en `club`, `clubLocked`, `origin`.

- [ ] **Step 3: Implementar**

`app/Actions/Files/ResolveSourcesClubAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\User;
use App\Enums\ClubName;
use App\Ai\Sources\KnowledgeSourceFactory;

/**
 * Qué club ve la pantalla de Fuentes. Un admin externo tiene club y no lo
 * cambia; un admin local sin club elige, y por defecto ve el primero con
 * fuente configurada.
 */
final class ResolveSourcesClubAction
{
    public function __construct(private readonly KnowledgeSourceFactory $sources) {}

    public function execute(?User $user, ?string $requested): ClubName
    {
        $own = $user?->clubName();

        if ($own !== null) {
            return $own;
        }

        $requestedClub = is_string($requested) ? ClubName::tryFrom($requested) : null;

        return $requestedClub ?? $this->sources->clubs()[0] ?? ClubName::CCM;
    }

    public function isLocked(?User $user): bool
    {
        return $user?->clubName() !== null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_map(fn (ClubName $club) => ['value' => $club->value, 'label' => $club->label()], $this->sources->clubs());
    }
}
```

`AssistantSourcesQuery::execute(ClubName $club)`: `files()`, `expiredFiles()` y `groups()` añaden `->where('project', $club->value)`; `present()` devuelve `'origin' => $file->origin?->value ?? 'manual'`; borrar `PENTAHO_NAME`.

`AssistantSourcesController`:

```php
    public function __invoke(
        Request $request,
        ResolveSourcesClubAction $resolveClub,
        AssistantSourcesQuery $sources,
        AssistantSourcesHealthQuery $health,
        ChatHistoryQuery $chatHistory,
    ): Response {
        $club = $resolveClub->execute($request->user(), $request->query('club'));

        return Inertia::render('Sources', [
            ...$sources->execute($club),
            'club' => $club->value,
            'clubs' => $resolveClub->options(),
            'clubLocked' => $resolveClub->isLocked($request->user()),
            'health' => $health->execute($club)->toArray(),
            'chatHistory' => Inertia::deepMerge($chatHistory->execute($request->user())),
        ]);
    }
```

`ReconcileSourceFilesRequest`: regla `'club' => ['required', Rule::in(array_column(ClubName::cases(), 'value'))]` (import `Illuminate\Validation\Rule`, `App\Enums\ClubName`) y método `public function club(): ClubName { return ClubName::from($this->string('club')->value()); }`. `AssistantSourcesManagementController::reconcileReport/reconcile` llaman `$reconcile->report($request->club(), $request->includeUntagged())` y `$reconcile->apply($request->club(), $report)`. `purgeExpired(Request $request, ResolveSourcesClubAction $resolveClub)` despacha `RemoveExpiredDocs::dispatch($resolveClub->execute($request->user(), $request->input('club'))->value)`.

`RemoveExpiredDocs`: `public function __construct(public readonly ?string $project = null) {}` y en `handle()`: `File::expired()->when($this->project !== null, fn ($q) => $q->where('project', $this->project))->lazy()->each->remove();`.

`StoreSourceFileRequest`: regla `'club' => ['required', Rule::in(array_column(ClubName::cases(), 'value'))]` (import `Illuminate\Validation\Rule`, `App\Enums\ClubName`) con mensajes `'club.required' => 'Indica el club del documento.'` y `'club.in' => 'Ese club no está configurado.'`. `ManualSourceFileData` gana `public ClubName $club` (primer parámetro; `fromValidated` hace `ClubName::from($data['club'])`). `StoreManualSourceFileAction::execute`: nombre `sprintf('%s/%s-manual-%d-%s.%s', $data->club->value, $data->group, ...)`, y `create([... 'project' => $data->club->value, 'origin' => SourceOrigin::Manual, 'checksum' => hash_file('sha256', $data->file->getRealPath()), ...])`. PHPDoc: "el nombre lleva el club como carpeta, como el import".

Nota de alcance: `sync` sigue siendo global (el import recorre todos los clubes y el candado es uno); no lleva `club`.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Sources tests/Feature/Files/RemoveExpiredDocsTest.php`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Actions/Files/ResolveSourcesClubAction.php app/Queries/AssistantSourcesQuery.php app/Http/Controllers/AssistantSourcesController.php app/Http/Controllers/AssistantSourcesManagementController.php app/Http/Requests app/Dtos/ManualSourceFileData.php app/Actions/Files/StoreManualSourceFileAction.php app/Jobs/RemoveExpiredDocs.php tests/Feature/Sources
```

---

### Task 11: Fuentes por club en pantalla (Vue)

**Files:**
- Create: `resources/js/components/sources/SourcesClubSwitch.vue`
- Modify: `resources/js/pages/Sources.vue`
- Modify: `resources/js/components/sources/types.ts`
- Modify: `resources/js/components/sources/format.ts`
- Modify: `resources/js/components/sources/UploadSourceDialog.vue`
- Modify: `resources/js/components/sources/ReconcileDialog.vue:59,85`

**Interfaces:**
- Consumes: props `club`, `clubs`, `clubLocked` de la tarea 10; parámetro `club` en las rutas.
- Produces: `SourcesClubSwitch` (`clubs`, `modelValue`, `locked`), `SourceOrigin = 'pentaho' | 'bi_knowledge' | 'manual'`.

- [ ] **Step 1: Tipos y etiquetas**

`types.ts`: `export type SourceOrigin = 'pentaho' | 'bi_knowledge' | 'manual'` y `export interface ClubOption { value: string, label: string }`. `format.ts`: añadir `bi_knowledge: 'BI del club'` a `originLabel`.

- [ ] **Step 2: Selector**

`resources/js/components/sources/SourcesClubSwitch.vue`, sobre el `Select` de shadcn-vue ya instalado en `@/components/ui/select`:

```vue
<script setup lang="ts">
import type { ClubOption } from './types'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const props = withDefaults(defineProps<{
  clubs: ClubOption[]
  modelValue: string
  locked?: boolean
}>(), { locked: false })

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const currentLabel = () => props.clubs.find(c => c.value === props.modelValue)?.label ?? props.modelValue
</script>

<template>
  <div v-if="locked || clubs.length <= 1" class="text-sm text-muted-foreground">
    Club: <span class="font-medium text-foreground">{{ currentLabel() }}</span>
  </div>
  <div v-else class="flex items-center gap-3">
    <Label for="sources-club" class="text-sm text-muted-foreground">Club</Label>
    <Select :model-value="modelValue" @update:model-value="(value) => emit('update:modelValue', String(value))">
      <SelectTrigger id="sources-club" class="h-9 w-56">
        <SelectValue placeholder="Elige un club" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem v-for="club in clubs" :key="club.value" :value="club.value">
          {{ club.label }}
        </SelectItem>
      </SelectContent>
    </Select>
  </div>
</template>
```

- [ ] **Step 3: Página**

`Sources.vue`: props nuevos `club: string`, `clubs?: ClubOption[]` (default `[]`), `clubLocked?: boolean` (default `false`). Cambiar de club recarga la página con el query `club`:

```ts
function switchClub(club: string): void {
  router.get(route('sources'), { club }, { preserveState: false, preserveScroll: true })
}
```

Debajo del `<header>` y antes de `SourcesHealthStrip`, renderizar `<SourcesClubSwitch :clubs="clubs" :model-value="club" :locked="clubLocked" @update:model-value="switchClub" />`. `purgeExpired()` manda `{ club }` como data: `router.delete(route('sources.expired.purge'), { data: { club }, preserveScroll: true })`. Pasar `:club="club"` a `UploadSourceDialog` y a `ReconcileDialog`. Actualizar el subtítulo: "Documentos del BI de cada club, reportes de Pentaho y archivos subidos a mano que el asistente consulta al responder."

`UploadSourceDialog.vue`: prop `club: string`; el `useForm` incluye `club: props.club` (y un `watch(() => props.club, v => form.club = v)`); el `DialogDescription` pasa a "El asistente lo indexa en el store del club y sustituye al documento anterior del mismo nombre."

`ReconcileDialog.vue`: prop `club: string`; en la línea 59 añadir `params: { club: props.club, include_untagged: ... }` como ya se mande hoy `include_untagged`, y en la línea 85 añadir `club: props.club` al cuerpo del `router.post`.

- [ ] **Step 4: Comprobar tipos y build**

Run: `npm run build 2>&1 | tail -5` (o `npx vue-tsc --noEmit` si existe el script `types`).
Expected: build sin errores.

- [ ] **Step 5: Stage**

```bash
git add resources/js/components/sources resources/js/pages/Sources.vue
```

---

### Task 12: Secreto de firma de vallealto

**Files:**
- Modify: `config/app.php:128-131`
- Modify: `.env.example:80-84`
- Modify: `tests/Feature/Auth/ExternalAuthenticationTest.php`

- [ ] **Step 1: Escribir el test que falla**

```php
it('authenticates a vallealto link signed with its own secret', function (): void {
    // La clave debe existir en config/app.php: Config::set sobre una clave
    // inexistente la crearía y el test pasaría sin tocar la config real.
    expect(config('app.club_signature_secrets'))->toHaveKey('vallealto');

    Config::set('app.club_signature_secrets.vallealto', 'va-secret');
    Role::firstOrCreate(['name' => 'restaurant_manager']);

    $params = ['club' => 'vallealto', 'user_id' => 7, 'user_name' => 'Ana', 'role' => 'restaurant_manager', 'issued_at' => now()->getTimestamp()];
    $params['sig'] = hash_hmac('sha256', implode('|', $params), 'va-secret');

    $this->get('/?'.http_build_query($params))->assertRedirect(route('chats.index'));

    expect(User::sole()->club_name)->toBe('vallealto');
});
```

- [ ] **Step 2: Correrlo y ver que falla**

Run: `vendor/bin/pest tests/Feature/Auth/ExternalAuthenticationTest.php --filter=vallealto`
Expected: FAIL en `toHaveKey('vallealto')`.

- [ ] **Step 3: Implementar**

`config/app.php`:

```php
    'club_signature_secrets' => [
        'ccm' => env('CCM_SIGNATURE_SECRET'),
        'saltillo' => env('SALTILLO_SIGNATURE_SECRET'),
        'vallealto' => env('VALLEALTO_SIGNATURE_SECRET'),
    ],
```

`.env.example`, debajo de `CCM_SIGNATURE_SECRET`: `VALLEALTO_SIGNATURE_SECRET="<64-char-random-hex>"` con comentario "en vallealto: SOFTOK2_AI_CHAT_SECRET". Añadir el origen de vallealto al ejemplo de `FRAME_ANCESTORS`.

- [ ] **Step 4: Correr el test**

Run: `vendor/bin/pest tests/Feature/Auth/ExternalAuthenticationTest.php`
Expected: PASS.

- [ ] **Step 5: Stage**

```bash
git add config/app.php .env.example tests/Feature/Auth/ExternalAuthenticationTest.php
```

---

### Task 13: Suite completa, Pint y barrido final

**Files:**
- Ninguno nuevo. Ajustes que salgan.

- [ ] **Step 1: Barrido de referencias muertas**

```bash
grep -rn "vector_store_id\|softok2mds\|ImportDocsFromPentaho\|fromPentaho\|'manual'" app config bootstrap tests resources/js --include='*.php' --include='*.ts' --include='*.vue'
```

Expected: cero resultados en `app/`, `config/`, `bootstrap/`. En tests solo puede quedar `'manual'` como valor de `origin`.

- [ ] **Step 2: Suite completa**

Run: `php artisan test --compact`
Expected: todo en verde. Si algo cae por `MissingClubVectorStore` en un test viejo, ese test fija `services.openai.vector_stores.ccm` en su `beforeEach`.

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty`
Expected: sin cambios pendientes o solo formato.

- [ ] **Step 4: Stage y reporte**

```bash
git add -A app config bootstrap database resources/js tests .env.example docs
git status --short
```

Reportar al usuario la lista de archivos staged y los pasos de despliegue de la sección 8 de la spec (crear el store de vallealto en OpenAI, `.env`, `migrate`, `db:seed --class=RoleSeeder`, `assistant-files:sync`). Sin commit.
