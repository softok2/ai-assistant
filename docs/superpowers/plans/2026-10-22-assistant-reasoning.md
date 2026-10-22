# Razonamiento visible en el stream del asistente — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el asistente y los agentes de reportes usen `gpt-6-luna` con razonamiento `low`, que el resumen del razonamiento se vea en vivo ("Pensando…") y quede guardado en el mensaje ("Razonó N s"), y que desaparezca el selector de modelo por mensaje.

**Architecture:** Backend: `config('services.openai.model')` pasa a `gpt-6-luna` y un trait `ConfiguresOpenAiReasoning` da `HasProviderOptions` a `ClubAssistant` (con `summary`) y a los dos agentes de reportes (solo `effort`); `ResolveStreamInsightsAction` convierte los eventos `ReasoningStart/Delta/End` de laravel/ai en una parte `reasoning` persistida en `messages.parts`. El controller de stream no cambia: ya reenvía todos los eventos. Frontend: `useAssistantStream.ts` acumula los eventos de razonamiento en `parts.reasoning`; un componente nuevo `ReasoningTrail.vue` lo pinta encima de `ActivityTrail`.

**Tech Stack:** Laravel 13 / PHP 8.5 / laravel/ai 1.3 (`Laravel\Ai\Contracts\HasProviderOptions`, `Laravel\Ai\Streaming\Events\{ReasoningStart,ReasoningDelta,ReasoningEnd}`), Pest 5, Vue 3.5 + Inertia 3, `@laravel/stream-vue`, Tailwind 4, `@lucide/vue`.

**Spec:** `docs/superpowers/specs/2026-10-22-assistant-reasoning-design.md`

## Global Constraints

- **No commitear ni pushear.** Cada tarea termina con `git add -A` y `git status --short`; Franky commitea.
- Rama `feature/assistant-reasoning` creada desde `stage` **con el working tree limpio** (la ronda de correcciones del upgrade debe estar commiteada antes; si `git status` no está limpio, parar y avisar).
- Un solo modelo: `services.openai.model` (env `OPENAI_MODEL`) default `gpt-6-luna`; `services.openai.reasoning_effort` (env `OPENAI_REASONING_EFFORT`) default `low`, vacío = sin razonamiento. Lo usan `ClubAssistant`, `ModuleReportAnalyst` y `ExecutiveSummaryWriter`. Títulos y sugerencias (`#[UseCheapestModel]`) no se tocan.
- El bloque enviado a OpenAI es exactamente `['reasoning' => ['effort' => <effort>, 'summary' => 'auto']]` en el asistente y `['reasoning' => ['effort' => <effort>]]` en los agentes de reportes, solo para el driver `openai` y solo si el modelo es de razonamiento (prefijos `gpt-5` salvo `gpt-5-chat`, `gpt-6`, `o` + dígito).
- Parte persistida: `parts.reasoning = ['summary' => string, 'seconds' => int]`; ausente si no hubo deltas. Bloques concatenados con `"\n\n"`. `seconds` = suma por bloque de `end.timestamp - start.timestamp`, mínimo 1.
- El resumen se guarda crudo; solo se formatea al pintar (negritas `**…**` como títulos). Sin Markdown.
- "Pensando…" solo con razonamiento real. El indicador de puntos previo pierde el texto.
- Nunca enviar `reasoning` a un modelo que no razona (OpenAI devuelve 400).
- `grep` del shell está aliased a `ugrep`: usar `rg`. Tests Pest con sqlite `:memory:`; nunca migrar la BD de `.env`.

## Review Focus

1. **Cliente viejo que aún envía `model`** (localStorage de usuarios activos): el stream debe ignorarlo sin error de validación. Test en Tarea 1 (`ChatStreamControllerTest`, `ChatControllerTest`).
2. **Modelo configurado sin razonamiento** (`OPENAI_MODEL=gpt-4.1-mini`) o `OPENAI_REASONING_EFFORT=` vacío: `providerOptions()` devuelve `[]` y el chat funciona como hoy. Tests en Tarea 2.
3. **Respuesta con razonamiento pero sin texto** (p. ej. error tras razonar): la parte `reasoning` se persiste igual y la UI no muestra "Razonó" sobre un mensaje vacío de forma rara. Test de persistencia en Tarea 3; comprobación visual en Tarea 5.
4. **Varios bloques de razonamiento** con herramientas entre medias: un solo "Pensando…", segundos sumados. Test en Tarea 3 (dos `reasoning_id`).
5. **Resumen con negritas pegadas** ("…resultados.**Reviso cifras**"): el formateador separa en líneas. Test manual en Tarea 5 con un resumen fijo en el componente (no hay runner de tests de Vue).

---

### Task 1: Quitar el selector de modelo por mensaje

**Files:**
- Delete: `app/Enums/ModelName.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:8,58` (quitar `availableModels`)
- Modify: `app/Http/Requests/StoreChatRequest.php:7,33`, `app/Http/Requests/ChatStreamRequest.php:7,34` (quitar la regla `model`)
- Modify: `app/Http/Controllers/ChatStreamController.php:46` (quitar `model: $validated['model'] ?? null`)
- Modify: `resources/js/components/assistant/ChatInput.vue` (prop `models`, `selectedModelId`, `useStorage('assistant-model')`, el menú "Modelo" y el `emit('submit', message, model, files)` → `emit('submit', message, files)`)
- Modify: `resources/js/pages/Chat/Index.vue:19-24`, `resources/js/pages/Chat/Show.vue:22,54-60` (quitar `models` y el parámetro `model`)
- Modify: `resources/js/composables/useAssistantStream.ts:8` (quitar `model` de `StreamParams`)
- Modify: `resources/js/types/index.d.ts:152-156` (quitar `interface Model`) y `SharedData.availableModels` si existe
- Modify: `tests/Feature/Http/Controllers/ChatControllerTest.php:8,76,101,113-118`, `tests/Feature/Http/Controllers/ChatStreamControllerTest.php:166-167`

**Interfaces:**
- Produces: `ChatInput` emite `submit(message: string, attachments: ChatAttachment[])`; el stream ya no acepta ni necesita `model`.

- [ ] **Step 1: Rama**

Run: `cd /Volumes/FrankySSD/Projects/ai-assistant && git status --short | wc -l && git checkout -b feature/assistant-reasoning stage`
Expected: `0` (limpio) y la rama creada. Si no es 0, PARAR y avisar: falta commitear la ronda de correcciones del upgrade.

- [ ] **Step 2: Reescribir los tests que validaban `model`**

En `ChatControllerTest.php`: quitar `'model' => ModelName::…` de los `post` (líneas 76 y 101) y el `use`; sustituir el test de `'invalid-model'` (113-118) por:

```php
it('ignores a model sent by an outdated client', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chat.store'), ['message' => 'Hola', 'model' => 'gpt-4o-mini'])
        ->assertSessionDoesntHaveErrors('model');
});
```

En `ChatStreamControllerTest.php:166-167`: el test que esperaba `assertSessionHasErrors('model')` con `'modelo-inexistente'` pasa a `->assertSessionDoesntHaveErrors('model')` (mantener el resto del arrange tal cual).

- [ ] **Step 3: Verificar que fallan**

Run: `php artisan test --filter="ignores a model|modelo-inexistente" 2>&1 | tail -8`
Expected: FAIL (hoy sí hay error de validación en `model`).

- [ ] **Step 4: Quitar el selector en backend**

Borrar `app/Enums/ModelName.php`; quitar la regla `model` y el `use` en los dos FormRequests; quitar `availableModels` y el `use` en `HandleInertiaRequests`; en `ChatStreamController` quitar el argumento `model:` de la llamada a `stream(...)`.

- [ ] **Step 5: Quitar el selector en frontend**

`ChatInput.vue`: eliminar prop `models`, `selectedModelId`/`selectedModel`, el `useStorage('assistant-model', '')`, el import de `Model`, el bloque del menú "Modelo" (compacto y normal), y cambiar la firma del emit a `(e: 'submit', message: string, attachments: ChatAttachment[])`. `Index.vue` y `Show.vue`: quitar `models`, el parámetro `model` de `startChat`/`sendMessage` y del payload a `send(...)`/`router.post`. `useAssistantStream.ts`: quitar `model` de `StreamParams`. `types/index.d.ts`: quitar `Model` y `availableModels`.

Run: `rg -n "ModelName|availableModels|assistant-model|selectedModel|\bModel\b" app resources/js tests --glob '!*.lock'`
Expected: sin resultados.

- [ ] **Step 6: Verde**

Run: `php artisan test 2>&1 | tail -4 && npm run typecheck 2>&1 | tail -2 && npm run lint 2>&1 | tail -2 && npm run build 2>&1 | tail -2`
Expected: tests en verde (mismo total que la base menos/más los tests reescritos), typecheck, lint y build sin errores.

- [ ] **Step 7: Stage**

```bash
git add -A && git status --short
```

---

### Task 2: Modelo único y opciones de razonamiento en los agentes OpenAI

**Files:**
- Modify: `config/services.php:39-41` (default de `model`, clave `reasoning_effort`)
- Modify: `.env.example` (`OPENAI_MODEL`, `OPENAI_REASONING_EFFORT`)
- Create: `app/Ai/Concerns/ConfiguresOpenAiReasoning.php`
- Modify: `app/Ai/Agents/ClubAssistant.php:27`, `app/Ai/Agents/ModuleReportAnalyst.php`, `app/Ai/Agents/ExecutiveSummaryWriter.php` (`implements HasProviderOptions` + trait)
- Create: `tests/Feature/Ai/OpenAiReasoningOptionsTest.php`

**Interfaces:**
- Produces: trait `ConfiguresOpenAiReasoning` con `public function providerOptions(Lab|string $provider): array`, `protected function reasoningSummary(): ?string` (el asistente devuelve `'auto'`, los reportes `null`) y `private function supportsReasoning(string $model): bool`. Los tres agentes siguen leyendo `config('services.openai.model')` en `model()`.

- [ ] **Step 1: Tests**

```php
<?php

declare(strict_types=1);

use App\Ai\Agents\ClubAssistant;
use App\Ai\Agents\ModuleReportAnalyst;

function configureOpenAi(string $model, string $effort = 'low'): void
{
    config([
        'services.openai.model' => $model,
        'services.openai.reasoning_effort' => $effort,
    ]);
}

it('asks openai for a low-effort reasoning summary in the assistant', function (): void {
    configureOpenAi('gpt-6-luna');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('openai'))
        ->toBe(['reasoning' => ['effort' => 'low', 'summary' => 'auto']]);
});

it('asks for low-effort reasoning without a summary in report agents', function (): void {
    configureOpenAi('gpt-6-luna');

    expect(app(ModuleReportAnalyst::class)->providerOptions('openai'))
        ->toBe(['reasoning' => ['effort' => 'low']]);
});

it('sends no reasoning options for a model without reasoning', function (): void {
    configureOpenAi('gpt-4.1-mini');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('openai'))->toBe([]);
});

it('sends no reasoning options when the effort is empty', function (): void {
    configureOpenAi('gpt-6-luna', '');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('openai'))->toBe([]);
});

it('sends no reasoning options to other providers', function (): void {
    configureOpenAi('gpt-6-luna');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('anthropic'))->toBe([]);
});
```

Si `ClubAssistant::forUser()` o `app(ModuleReportAnalyst::class)` no son la forma de construirlos en el código (leer `ChatStreamController` y `GenerateWeeklyReportAction`), usar la que esté en uso.

- [ ] **Step 2: Verificar que fallan**

Run: `php artisan test --filter=OpenAiReasoningOptionsTest 2>&1 | tail -8`
Expected: FAIL (no existe `providerOptions`).

- [ ] **Step 3: Config**

`config/services.php`: `'model' => env('OPENAI_MODEL', 'gpt-6-luna'),` y debajo:

```php
// Esfuerzo de razonamiento para los agentes que usan `model` (vacío = sin razonamiento).
'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
```

`.env.example`: `OPENAI_MODEL=gpt-6-luna` y, debajo, `OPENAI_REASONING_EFFORT=low`.

- [ ] **Step 4: Trait y agentes**

`app/Ai/Concerns/ConfiguresOpenAiReasoning.php`:

```php
public function providerOptions(Lab|string $provider): array
```

Devuelve `[]` salvo que el driver sea `openai` (`$provider instanceof Lab ? $provider->value : $provider`; comprobar el valor de `Lab::OpenAI` en `vendor/laravel/ai/src/Enums/Lab.php`), `config('services.openai.reasoning_effort')` esté `filled` y `$this->supportsReasoning($this->model())`. Entonces `['reasoning' => array_filter(['effort' => <effort>, 'summary' => $this->reasoningSummary()])]`. `protected function reasoningSummary(): ?string { return null; }`; `private function supportsReasoning(string $model): bool` con `preg_match('/^(gpt-5(?!-chat)|gpt-6|o\d)/', $model) === 1`. Comentario de una línea: OpenAI rechaza `reasoning` en modelos sin razonamiento.

Los tres agentes: `implements … , HasProviderOptions` + `use ConfiguresOpenAiReasoning;`. `ClubAssistant` sobrescribe `reasoningSummary(): ?string` devolviendo `'auto'`. `model()` no cambia en ninguno (ya leen `services.openai.model`).

- [ ] **Step 5: Verde**

Run: `php artisan test --filter=OpenAiReasoningOptionsTest 2>&1 | tail -4 && php artisan test 2>&1 | tail -3`
Expected: 5 passed; suite completa en verde (los tests de reportes siguen con fakes y no dependen del modelo).

- [ ] **Step 6: Stage**

```bash
git add -A && git status --short
```

---

### Task 3: Persistir el razonamiento en `parts.reasoning`

**Files:**
- Modify: `app/Dtos/AssistantStreamInsights.php`
- Modify: `app/Actions/Chats/ResolveStreamInsightsAction.php:30-66`
- Modify: `tests/Feature/Ai/StreamInsightsTest.php`
- Modify: `tests/Feature/Http/Controllers/ChatStreamControllerTest.php`

**Interfaces:**
- Consumes: eventos `Laravel\Ai\Streaming\Events\ReasoningStart(id, reasoningId, timestamp)`, `ReasoningDelta(id, reasoningId, delta, timestamp, ?summary)`, `ReasoningEnd(id, reasoningId, timestamp)` (verificar los constructores en `vendor/laravel/ai/src/Streaming/Events/`).
- Produces: `AssistantStreamInsights::__construct(array $activity = [], array $sources = [], ?array $reasoning = null)`; `toParts()` añade `'reasoning' => ['summary' => string, 'seconds' => int]` cuando no es null.

- [ ] **Step 1: Tests en `StreamInsightsTest.php`**

```php
it('keeps the reasoning summary and its duration across blocks', function (): void {
    $parts = resolveInsights([
        new ReasoningStart('e1', 'r1', 1000),
        new ReasoningDelta('e2', 'r1', 'Reviso ', 1000),
        new ReasoningDelta('e3', 'r1', 'el presupuesto', 1001),
        new ReasoningEnd('e4', 'r1', 1003),
        new ReasoningStart('e5', 'r2', 1005),
        new ReasoningDelta('e6', 'r2', 'Confirmo cifras', 1005),
        new ReasoningEnd('e7', 'r2', 1006),
        new TextDelta('e8', 'm1', 'Hola', 1006),
    ]);

    expect($parts['reasoning'])->toBe([
        'summary' => "Reviso el presupuesto\n\nConfirmo cifras",
        'seconds' => 4,
    ]);
});

it('counts at least one second of reasoning', function (): void {
    $parts = resolveInsights([
        new ReasoningStart('e1', 'r1', 1000),
        new ReasoningDelta('e2', 'r1', 'Pienso', 1000),
        new ReasoningEnd('e3', 'r1', 1000),
    ]);

    expect($parts['reasoning']['seconds'])->toBe(1);
});

it('omits reasoning when no delta arrived', function (): void {
    $parts = resolveInsights([
        new ReasoningStart('e1', 'r1', 1000),
        new ReasoningEnd('e2', 'r1', 1002),
        new TextDelta('e3', 'm1', 'Hola', 1002),
    ]);

    expect($parts)->not->toHaveKey('reasoning');
});
```

Añadir los `use` de los tres eventos.

- [ ] **Step 2: Verificar que fallan**

Run: `php artisan test --filter=StreamInsightsTest 2>&1 | tail -8`
Expected: 3 FAIL por clave `reasoning` inexistente.

- [ ] **Step 3: DTO y acción**

`AssistantStreamInsights`: tercer parámetro `public ?array $reasoning = null` (docblock `array{summary: string, seconds: int}|null`); `toParts()` incluye `'reasoning' => $this->reasoning` y el filtro deja fuera `null` además de `[]`.

`ResolveStreamInsightsAction::execute()`: acumular por `reasoningId` el texto (`$reasoning[$id]['text'] .= $delta`) y los timestamps de start/end; al final, `summary` = bloques con texto no vacío unidos por `"\n\n"`; `seconds` = `max(1, sum(end - start))` sobre los bloques con texto. Si no hay bloques con texto → `null`. Las ramas nuevas van en el `match` existente (`ReasoningStart`, `ReasoningDelta`, `ReasoningEnd`), extraídas a un método privado `readReasoning(...)` si el `match` crece demasiado.

- [ ] **Step 4: Verde parcial**

Run: `php artisan test --filter=StreamInsightsTest 2>&1 | tail -4`
Expected: todos passed.

- [ ] **Step 5: Test de persistencia end-to-end**

En `ChatStreamControllerTest.php`, con el mismo patrón de stub de gateway que el test de errores de stream añadido en la ronda final del upgrade (yield de eventos antes de terminar):

```php
it('persists the reasoning summary with the assistant message', function (): void {
    // arrange como el test de stream existente, con un gateway que emite:
    // ReasoningStart('e1','r1',time()), ReasoningDelta('e2','r1','Reviso cifras',time()),
    // ReasoningEnd('e3','r1',time()), TextDelta('e4','m1','Listo',time())
    // …
    $message = Message::query()->where('role', 'assistant')->latest('id')->first();

    expect($message->parts['reasoning']['summary'])->toBe('Reviso cifras')
        ->and($message->parts['reasoning']['seconds'])->toBe(1)
        ->and($message->parts['text'])->toBe('Listo');
});
```

(Usar los nombres reales del modelo de mensajes y del helper de arrange del archivo.)

- [ ] **Step 6: Verde**

Run: `php artisan test 2>&1 | tail -3`
Expected: suite en verde.

- [ ] **Step 7: Stage**

```bash
git add -A && git status --short
```

---

### Task 4: Stream en el cliente y `ReasoningTrail`

**Files:**
- Modify: `resources/js/types/index.d.ts:106-110` (`MessageParts.reasoning?: ReasoningPart`; `interface ReasoningPart { summary: string; seconds: number }`)
- Modify: `resources/js/composables/useAssistantStream.ts` (`StreamEvent` gana `reasoning_id?: string`, `timestamp?: number`; tres handlers nuevos)
- Create: `resources/js/components/assistant/ReasoningTrail.vue`
- Modify: `resources/js/components/assistant/MessageBubble.vue:159` (montar `ReasoningTrail` encima de `ActivityTrail`)
- Modify: `resources/js/components/assistant/ChatMessageList.vue:92-99` (puntos sin texto)

**Interfaces:**
- Consumes: eventos SSE `{"type":"reasoning_start","reasoning_id":…,"timestamp":…}`, `{"type":"reasoning_delta","reasoning_id":…,"delta":…,"timestamp":…}`, `{"type":"reasoning_end","reasoning_id":…,"timestamp":…}` y `parts.reasoning` de la Tarea 3.
- Produces: `ReasoningTrail` props `{ reasoning?: ReasoningPart | null, thinking: boolean }`.

- [ ] **Step 1: Tipos y stream**

En `useAssistantStream.ts`, en `handle()` antes del bloque `*_call`:
- `reasoning_start`: `reasoningStarts.set(reasoning_id, timestamp)` (un `Map<string, number>` local, como `activityKeys`); si `current.parts.reasoning` no existe, crearlo `{ summary: '', seconds: 0 }`.
- `reasoning_delta`: si `summary` ya tiene texto de OTRO bloque (`lastReasoningId !== reasoning_id` y `summary !== ''`), añadir `"\n\n"`; concatenar `delta`; recordar `lastReasoningId`.
- `reasoning_end`: `seconds += Math.max(0, timestamp - (reasoningStarts.get(reasoning_id) ?? timestamp))`.
Actualizar el comentario que hoy dice que los de razonamiento se ignoran.

- [ ] **Step 2: `ReasoningTrail.vue`**

Props `reasoning?: ReasoningPart | null`, `thinking: boolean`. No renderiza nada si `!reasoning || reasoning.summary.trim() === ''`. Estado local `expanded = ref(false)`. Cabecera: un `<button type="button" :aria-expanded="expanded" @click="expanded = !expanded">` con icono de `@lucide/vue` (`Brain` si existe en 1.x, si no `Sparkles`; `aria-hidden`), texto `thinking ? 'Pensando…' : \`Razonó ${Math.max(1, reasoning.seconds)} s\``, y un `ChevronDown` que rota con `expanded`. Cuerpo: mientras `thinking` y no `expanded`, el resumen con `line-clamp-2`; si `expanded`, completo. Formateador `formatReasoning(summary): Array<{ kind: 'heading' | 'text', text: string }>` que parte por `/\*\*(.+?)\*\*/g`: los grupos capturados son `heading`, el resto `text`; se pinta cada `heading` como `<p class="font-medium">` y cada `text` como `<p class="whitespace-pre-wrap">`, ignorando trozos vacíos. Estilos como `ActivityTrail` (`text-xs text-muted-foreground`, `mb-2`).

- [ ] **Step 3: Montarlo y quitar el texto de los puntos**

`MessageBubble.vue`: antes de `<ActivityTrail …>` añadir `<ReasoningTrail :reasoning="message.parts.reasoning" :thinking="streaming && !message.parts.text" />` e importar. `ChatMessageList.vue:92-99`: dejar los tres puntos, quitar el texto "Pensando…", y poner en el contenedor `role="status"` con `<span class="sr-only">Esperando respuesta</span>`.

- [ ] **Step 4: Verde**

Run: `npm run typecheck 2>&1 | tail -2 && npm run lint 2>&1 | tail -2 && npm run build 2>&1 | tail -2`
Expected: sin errores.

- [ ] **Step 5: Stage**

```bash
git add -A && git status --short
```

---

### Task 5: Gate, humo y nota de coste

**Files:**
- Create: `docs/superpowers/plans/2026-10-22-assistant-reasoning-gate.md`

- [ ] **Step 1: Gate**

Run: `php artisan test 2>&1 | tail -3 && vendor/bin/pint --test 2>&1 | tail -2 && vendor/bin/rector --dry-run 2>&1 | tail -2 && npm run lint 2>&1 | tail -2 && npm run typecheck 2>&1 | tail -2 && npm run build 2>&1 | tail -2`
Expected: todo en verde.

- [ ] **Step 2: Comprobar saldo de OpenAI**

Run: `php artisan tinker --execute="echo \App\Ai\Agents\ClubAssistant::forUser(\App\Models\User::first())->prompt('Di hola')->text;"` (ajustar al constructor real).
Expected: texto de respuesta. Si devuelve 429 "no credits", anotar en el gate doc que el humo queda PENDIENTE y saltar al paso 5.

- [ ] **Step 3: Humo en `https://ai-assistant.test`** (con `agent-browser` o a mano, capturas al scratchpad)

(a) Pregunta que requiera consultar documentos: aparece "Pensando…" con texto creciendo antes del primer token; (b) al terminar, "Razonó N s" plegado, se despliega y se ve el resumen con títulos separados; (c) recargar el chat: la entrada sigue; (d) con `OPENAI_REASONING_EFFORT=` vacío (vía config temporal en tinker, sin tocar `.env`) el chat responde y no aparece la entrada; (f) generar un reporte semanal con `reports:send-due` o la acción directa y comprobar que responde con `gpt-6-luna`; (e) un mensaje en un chat antiguo sin `parts.reasoning` se pinta como antes.

- [ ] **Step 4: Nota de coste**

Tres preguntas iguales con `gpt-4.1-mini` y con `gpt-6-luna` + `low` (cambiando `services.openai.model` en tinker) (vía tinker, leyendo `->usage` de la respuesta): tabla con tokens de entrada, salida y razonamiento, y segundos hasta el primer token si se puede medir. Va al gate doc.

- [ ] **Step 5: Gate doc y stage**

`docs/superpowers/plans/2026-10-22-assistant-reasoning-gate.md`: resultados de 1–4, pendientes, y la nota para Franky de que los usuarios con `assistant-model` en localStorage dejan de poder elegir modelo (el valor queda huérfano, inofensivo).

```bash
git add -A && git status --short
```
