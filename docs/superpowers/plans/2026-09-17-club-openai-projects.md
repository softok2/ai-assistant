# Un proyecto de OpenAI por club — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que cada llamada a OpenAI hecha en nombre de un club use la clave del proyecto de OpenAI de ese club, con degradación al proveedor por defecto cuando el club no tiene clave.

**Architecture:** Un proveedor `openai_{club}` por club en `config/ai.php` y una clase `ClubAiProvider` que resuelve nombre y clave. Todo punto que toca OpenAI recibe el club y pasa `provider:` al SDK (`Stores::get`, `Files::delete`, `prompt`, `stream`, `generate`); el inventario HTTP usa la clave del club.

**Tech Stack:** Laravel 12, `laravel/ai` ^0.7, Pest 3.

**Spec:** `docs/superpowers/specs/2026-09-17-club-openai-projects-design.md`

## Global Constraints

- **No commits, no push.** Cada tarea termina con `git add` de sus archivos.
- Rama `stage` de `~/Projects/ai-assistant`.
- PHP nuevo: `declare(strict_types=1);`, clases `final`, PHPDoc en español. `vendor/bin/pint --dirty` antes de cerrar cada tarea.
- Tests Pest en `tests/Feature/...`; correr solo los archivos tocados, salvo la tarea final.
- Un club sin clave propia NUNCA falla: `nameFor()` devuelve `null` y el SDK usa el proveedor por defecto.
- No tocar `.env`; solo `.env.example`.

---

### Task 1: `ClubAiProvider` y proveedores por club

**Files:**
- Create: `app/Ai/ClubAiProvider.php`
- Modify: `config/ai.php` (bloque `providers`, junto a `openai`)
- Modify: `.env.example` (bloque IA)
- Test: `tests/Feature/Ai/ClubAiProviderTest.php`

**Interfaces:**
- Produces: `ClubAiProvider::nameFor(?ClubName $club): ?string`, `ClubAiProvider::keyFor(?ClubName $club): string`.

- [ ] **Step 1: Test que falla**

```php
<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\ClubAiProvider;

beforeEach(function () {
    config([
        'ai.providers.openai.key' => 'sk-default',
        'ai.providers.openai_ccm.key' => 'sk-ccm',
        'ai.providers.openai_vallealto.key' => 'sk-va',
        'ai.providers.openai_terralta' => null,
    ]);
});

it('names the provider of a club that has its own key', function () {
    expect(app(ClubAiProvider::class)->nameFor(ClubName::VALLEALTO))->toBe('openai_vallealto')
        ->and(app(ClubAiProvider::class)->keyFor(ClubName::VALLEALTO))->toBe('sk-va');
});

it('falls back to the default provider without a club or without a club key', function () {
    config(['ai.providers.openai_ccm.key' => '']);

    expect(app(ClubAiProvider::class)->nameFor(null))->toBeNull()
        ->and(app(ClubAiProvider::class)->nameFor(ClubName::TERRALTA))->toBeNull()
        ->and(app(ClubAiProvider::class)->nameFor(ClubName::CCM))->toBeNull()
        ->and(app(ClubAiProvider::class)->keyFor(ClubName::CCM))->toBe('sk-default')
        ->and(app(ClubAiProvider::class)->keyFor(null))->toBe('sk-default');
});
```

- [ ] **Step 2: Correr y ver que falla**

Run: `vendor/bin/pest tests/Feature/Ai/ClubAiProviderTest.php`
Expected: FAIL, `Class "App\Ai\ClubAiProvider" not found`.

- [ ] **Step 3: Implementar**

`app/Ai/ClubAiProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\ClubName;

/**
 * Qué proveedor de OpenAI usa cada club. Cada club tiene su proyecto en
 * OpenAI (uso y costo separados, y sus stores solo los ve su clave); un club
 * sin clave propia usa el proveedor por defecto y nunca falla.
 */
final class ClubAiProvider
{
    /**
     * Nombre del proveedor en `config/ai.php`, o null para el de por defecto.
     */
    public function nameFor(?ClubName $club): ?string
    {
        if ($club === null) {
            return null;
        }

        $name = 'openai_'.$club->value;
        $key = config("ai.providers.{$name}.key");

        return is_string($key) && trim($key) !== '' ? $name : null;
    }

    /**
     * Clave con la que hablar con la API por HTTP directo (inventario).
     */
    public function keyFor(?ClubName $club): string
    {
        $name = $this->nameFor($club) ?? 'openai';

        return (string) config("ai.providers.{$name}.key");
    }
}
```

`config/ai.php`, debajo del proveedor `openai`:

```php
        // Un proyecto de OpenAI por club: uso y costo separados, y cada store
        // solo lo ve la clave de su proyecto. Lo resuelve App\Ai\ClubAiProvider.
        'openai_ccm' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY_CCM'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'openai_vallealto' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY_VALLEALTO'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],
```

`.env.example`, debajo de `OPENAI_API_KEY`:

```
# Clave del proyecto de OpenAI de cada club. El store de un club
# (OPENAI_VECTOR_STORE_*) debe vivir en el proyecto de su clave. Sin clave
# propia el club usa OPENAI_API_KEY.
OPENAI_API_KEY_CCM=""
OPENAI_API_KEY_VALLEALTO=""
```

- [ ] **Step 4: Correr y ver que pasa**

Run: `vendor/bin/pest tests/Feature/Ai/ClubAiProviderTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Ai/ClubAiProvider.php config/ai.php .env.example tests/Feature/Ai/ClubAiProviderTest.php
```

---

### Task 2: Stores, inventario y reconciliación con la clave del club

**Files:**
- Modify: `app/Ai/Files/ClubVectorStore.php`
- Modify: `app/Ai/Files/OpenAiFileInventory.php`
- Modify: `app/Actions/Files/ReconcileAssistantFilesAction.php`
- Modify: `tests/Feature/Files/RemoveExpiredDocsTest.php`, `tests/Feature/Files/OpenAiFileInventoryTest.php`, `tests/Feature/Files/ReconcileAssistantFilesTest.php`

**Interfaces:**
- Consumes: `ClubAiProvider::nameFor/keyFor`.
- Produces: `OpenAiFileInventory::accountFiles(ClubName $club)`.

- [ ] **Step 1: Tests que fallan**

`RemoveExpiredDocsTest`: nuevo test (mismo `Http::fake` que el primero del archivo, con `'ai.providers.openai_ccm.key' => 'sk-ccm'` en config):

```php
it('talks to OpenAI with the key of the club that owns the document', function () {
    config(['ai.providers.openai_ccm.key' => 'sk-ccm']);
    Http::fake([
        '*/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 1, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 1]]),
        '*/vector_stores/vs_test/files/file-abc' => Http::response(['deleted' => true]),
        '*/files/file-abc' => Http::response(['deleted' => true]),
    ]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-abc']);
    Storage::put('docs/'.$file->name, '# viejo');

    (new RemoveExpiredDocs)->handle();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->hasHeader('Authorization', 'Bearer sk-ccm'));
});
```

`OpenAiFileInventoryTest`: nuevo test que fija `'ai.providers.openai_vallealto.key' => 'sk-va'` y `'services.openai.vector_stores.vallealto' => 'vs_va'`, hace `Http::fake` de `*/vector_stores/vs_va/files?*` y `*/files?*` con `['data' => [], 'has_more' => false]`, llama `app(OpenAiFileInventory::class)->storeFiles(ClubName::VALLEALTO)` y `->accountFiles(ClubName::VALLEALTO)` y afirma `Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk-va'))` y `Http::assertNotSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk-test'))`. Las llamadas existentes a `accountFiles()` del archivo pasan a `accountFiles(ClubName::CCM)`.

`ReconcileAssistantFilesTest`: nuevo test con `'ai.providers.openai_ccm.key' => 'sk-ccm'`, `fakeOpenAiInventory()`, una fila `completed` con `assistant_media_id => 'file-keep'`, `report(ClubName::CCM, true)` + `apply(ClubName::CCM, $report)` y `Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->hasHeader('Authorization', 'Bearer sk-ccm'))`.

- [ ] **Step 2: Correr y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Files/RemoveExpiredDocsTest.php tests/Feature/Files/OpenAiFileInventoryTest.php tests/Feature/Files/ReconcileAssistantFilesTest.php`
Expected: FAIL (cabecera con la clave por defecto; `accountFiles()` sin argumento).

- [ ] **Step 3: Implementar**

`ClubVectorStore`: constructor `public function __construct(private readonly ClubAiProvider $providers) {}` (import `App\Ai\ClubAiProvider`) y:

```php
    public function storeFor(ClubName $club): Store
    {
        return Stores::get($this->idFor($club), $this->providers->nameFor($club));
    }
```

`OpenAiFileInventory`: constructor `(private readonly ClubVectorStore $stores, private readonly ClubAiProvider $providers)`; `accountFiles(ClubName $club)` y `storeFiles(ClubName $club)` llaman `$this->paginate($club, ...)`; `paginate(ClubName $club, string $path, array $query = [])` usa `$this->client($club)`; y:

```php
    private function client(ClubName $club): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai.providers.openai.url'), '/'))
            ->withToken($this->providers->keyFor($club))
            ->acceptJson()
            ->timeout(30);
    }
```

Actualizar el PHPDoc de clase: "cada club habla con el proyecto de OpenAI de su clave".

`ReconcileAssistantFilesAction`: constructor suma `private readonly ClubAiProvider $providers`; en `report()` `$this->inventory->accountFiles($club)`; en `apply()` `Files::delete($file['id'], $this->providers->nameFor($club))`. El conjunto `referencedAnywhere` se queda.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Files tests/Feature/Sources`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Ai/Files/ClubVectorStore.php app/Ai/Files/OpenAiFileInventory.php app/Actions/Files/ReconcileAssistantFilesAction.php tests/Feature/Files
```

---

### Task 3: El chat y sus agentes usan el proveedor del club

**Files:**
- Modify: `app/Http/Controllers/ChatStreamController.php:36-37`
- Modify: `app/Http/Controllers/ChatSuggestionsController.php:33`
- Modify: `app/Actions/Chats/ResolveChatStartersAction.php:61`
- Modify: `app/Jobs/GenerateChatTitle.php:35`
- Modify: `tests/Feature/Http/Controllers/ChatStreamControllerTest.php`, `tests/Feature/Http/Controllers/ChatFeaturesTest.php`, `tests/Feature/Ai/ChatStartersTest.php`

**Interfaces:**
- Consumes: `ClubAiProvider::nameFor(?ClubName)`, `User::clubName(): ?ClubName`.

- [ ] **Step 1: Tests que fallan**

Patrón de aserción (el SDK resuelve el proveedor real aun con el gateway falso; `AgentPrompt` está en `Laravel\Ai\Prompts\AgentPrompt`):

```php
ClubAssistant::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === 'openai_vallealto');
```

Añadir, fijando `config(['ai.providers.openai_vallealto.key' => 'sk-va'])` y un usuario con `club_name = vallealto`:

- `ChatStreamControllerTest`: tras enviar un mensaje, la aserción de arriba sobre `ClubAssistant`; y un segundo test con un usuario sin club que afirma `$prompt->provider->name() === 'openai'`.
- `ChatFeaturesTest`: en el test de sugerencias, la misma aserción sobre `ChatFollowUpSuggester`; en el del título, sobre `ChatTitleGenerator` (el job corre con el dueño del chat en vallealto).
- `ChatStartersTest`: la misma aserción sobre `ChatStarterSuggester` cuando el usuario es de vallealto.

Si `assertPrompted` no existe con ese nombre en esta versión del SDK, usar el método de aserción de prompts que exponga el fake del agente (`vendor/laravel/ai/src/Concerns/InteractsWithFakeAgents.php` o similar) y decirlo en el reporte.

- [ ] **Step 2: Correr y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Http/Controllers/ChatStreamControllerTest.php tests/Feature/Http/Controllers/ChatFeaturesTest.php tests/Feature/Ai/ChatStartersTest.php`
Expected: FAIL (el proveedor es `openai`).

- [ ] **Step 3: Implementar**

- `ChatStreamController`: inyectar `ClubAiProvider $providers` en el método y pasar `provider: $providers->nameFor($request->user()->clubName())` a `->stream(...)`.
- `ChatSuggestionsController`: `->prompt($exchange, provider: $providers->nameFor($request->user()?->clubName()))`.
- `ResolveChatStartersAction`: constructor suma `ClubAiProvider`; `->prompt($this->documentList($library), provider: $this->providers->nameFor($club))` (si `$club` llega como string, convertir con `ClubName::tryFrom`).
- `GenerateChatTitle::handle(ClubAiProvider $providers)`: `->prompt($exchange, provider: $providers->nameFor($this->chat->user?->clubName()))`. Verificar que `Chat` tiene la relación `user`; si se llama distinto, usar la existente.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Http tests/Feature/Ai`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/ChatStreamController.php app/Http/Controllers/ChatSuggestionsController.php app/Actions/Chats/ResolveChatStartersAction.php app/Jobs/GenerateChatTitle.php tests/Feature/Http tests/Feature/Ai
```

---

### Task 4: Reportes, voz y transcripción con el proveedor del club

**Files:**
- Modify: `app/Actions/Reports/GenerateWeeklyReportAction.php:76,112`
- Modify: `app/Http/Controllers/ChatSpeechController.php:22`
- Modify: `app/Http/Controllers/ChatTranscriptionController.php:19`
- Modify: `tests/Feature/Reports/WeeklyReportTest.php`, `tests/Feature/Http/Controllers/ChatFeaturesTest.php`

- [ ] **Step 1: Tests que fallan**

- `WeeklyReportTest`, en `generates report data from indexed modules, skipping empty ones`: fijar `'ai.providers.openai_ccm.key' => 'sk-ccm'` y añadir tras `execute(...)`:

```php
    ModuleReportAnalyst::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === 'openai_ccm');
    ExecutiveSummaryWriter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === 'openai_ccm');
```

- `ChatFeaturesTest`, tests de voz y de transcripción: usuario de vallealto con `'ai.providers.openai_vallealto.key' => 'sk-va'`; afirmar el proveedor con el método de aserción que expongan `Audio::fake()` y `Transcription::fake()` (buscar en `vendor/laravel/ai/src` `assertGenerated` o equivalente y si el objeto recibido trae `provider`). Si el fake no expone el proveedor, extraer la resolución a una línea probada indirectamente: afirmar que el controlador responde 200 y documentar en el reporte que el proveedor no es observable con ese fake.

- [ ] **Step 2: Correr y ver que fallan**

Run: `vendor/bin/pest tests/Feature/Reports/WeeklyReportTest.php tests/Feature/Http/Controllers/ChatFeaturesTest.php`
Expected: FAIL en el nombre del proveedor.

- [ ] **Step 3: Implementar**

- `GenerateWeeklyReportAction`: constructor suma `private readonly ClubAiProvider $providers`; `analyseModule` hace `->prompt("...", provider: $this->providers->nameFor($club))`; `executiveSummary(ClubName $club, array $moduleSummaries)` hace `->prompt(implode("\n\n", $moduleSummaries), provider: $this->providers->nameFor($club))` y `execute()` le pasa `$club`.
- `ChatSpeechController::__invoke(Message $message, ClubAiProvider $providers)`: `Audio::of($text)->generate(provider: $providers->nameFor(auth()->user()?->clubName()))`.
- `ChatTranscriptionController::__invoke(Request $request, ClubAiProvider $providers)`: `->generate(provider: $providers->nameFor($request->user()?->clubName()))`.

- [ ] **Step 4: Correr los tests**

Run: `vendor/bin/pest tests/Feature/Reports tests/Feature/Http`
Expected: PASS.

- [ ] **Step 5: Pint y stage**

```bash
vendor/bin/pint --dirty
git add app/Actions/Reports/GenerateWeeklyReportAction.php app/Http/Controllers/ChatSpeechController.php app/Http/Controllers/ChatTranscriptionController.php tests/Feature/Reports tests/Feature/Http
```

---

### Task 5: Barrido y suite completa

- [ ] **Step 1: Barrido de llamadas sin proveedor**

```bash
grep -rnE -- '->(prompt|stream|generate)\(' app | grep -v 'provider:' | grep -v '^app/Ai/Agents'
grep -rn "ai.providers.openai.key" app
```

Expected: el primer grep no devuelve llamadas a OpenAI sin `provider:`; el segundo solo `app/Ai/ClubAiProvider.php` (vía interpolación no aparecerá: entonces cero resultados).

- [ ] **Step 2: Suite, Pint y build**

Run: `php artisan test --compact && vendor/bin/pint --dirty && npm run build 2>&1 | tail -2`
Expected: todo verde.

- [ ] **Step 3: Stage**

```bash
git add -A app config tests .env.example docs
git status --short
```
