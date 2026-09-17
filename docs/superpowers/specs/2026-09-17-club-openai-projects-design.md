# Un proyecto de OpenAI por club

Fecha: 2026-09-17. Repo: softok2/ai-assistant, rama `stage`. Continúa
`2026-09-16-club-knowledge-sources-design.md`.

## Objetivo

Que todo lo que el asistente hace en OpenAI en nombre de un club (chat,
búsqueda de documentos, stores, archivos, reportes, títulos, sugerencias, voz
y transcripción) use la clave del proyecto de OpenAI de ese club. Así el uso
y el costo de cada club aparecen en su propio proyecto, y un store solo es
visible para la clave de su proyecto, que añade una segunda frontera entre
clubes del lado de OpenAI. El saldo sigue siendo único, de la organización.

## Decisiones del usuario

1. Un proyecto de OpenAI por club (ya existen "CCM" y "Vallealto").
2. Se factura al proyecto del club TODO lo que dispara un usuario de ese club,
   no solo el conocimiento.

## Diseño

### Proveedores por club

`config/ai.php` gana un proveedor por club, mismo driver y URL que `openai`:

```php
'openai_ccm' => ['driver' => 'openai', 'key' => env('OPENAI_API_KEY_CCM'), 'url' => env('OPENAI_URL', 'https://api.openai.com/v1')],
'openai_vallealto' => ['driver' => 'openai', 'key' => env('OPENAI_API_KEY_VALLEALTO'), 'url' => env('OPENAI_URL', 'https://api.openai.com/v1')],
```

`openai` (con `OPENAI_API_KEY`) queda como proveedor por defecto: lo usan los
usuarios sin club y cualquier club que todavía no tenga clave propia.

### `App\Ai\ClubAiProvider`

Clase final inyectable, hermana de `ClubVectorStore`:

- `nameFor(?ClubName $club): ?string` devuelve `openai_{club}` cuando
  `ai.providers.openai_{club}.key` es un string no vacío; en cualquier otro
  caso devuelve `null`, que para el SDK significa "proveedor por defecto".
- `keyFor(?ClubName $club): string` devuelve la clave de ese proveedor o la de
  `ai.providers.openai.key`. Solo la usa `OpenAiFileInventory`, que habla con
  la API por HTTP directo.

Un club sin clave propia nunca falla: degrada al proveedor por defecto.

### Puntos que pasan el proveedor del club

| Punto | Cambio |
|---|---|
| `ClubVectorStore::storeFor` | `Stores::get($id, $providers->nameFor($club))`; de ahí salen `File::upload` y `File::removeFromProvider`, que no cambian |
| `OpenAiFileInventory` | `accountFiles(ClubName $club)` y `storeFiles(ClubName $club)` usan un cliente HTTP con `keyFor($club)` |
| `ReconcileAssistantFilesAction` | `accountFiles($club)`; `Files::delete($id, $providers->nameFor($club))` |
| `ChatStreamController` | `->stream(..., provider: nameFor(club del usuario))` |
| `ChatSuggestionsController` | `->prompt($exchange, provider: ...)` con el club del usuario |
| `ResolveChatStartersAction` | `->prompt(..., provider: nameFor($club))` |
| `GenerateChatTitle` | club del dueño del chat |
| `GenerateWeeklyReportAction` | analista y resumen ejecutivo con el club del reporte |
| `ChatSpeechController`, `ChatTranscriptionController` | `->generate(provider: ...)` con el club del usuario autenticado |

El filtro `loose` de la reconciliación conserva la red `referencedAnywhere`:
con un proyecto por club la cuenta de archivos ya es solo del club, pero un
club sin clave propia sigue compartiendo la cuenta por defecto.

### Configuración

`.env.example`: `OPENAI_API_KEY_CCM`, `OPENAI_API_KEY_VALLEALTO`, con la nota
de que cada store (`OPENAI_VECTOR_STORE_*`) debe vivir en el proyecto de su
clave. `OPENAI_API_KEY` se queda como respaldo.

### Pruebas

- `ClubAiProviderTest`: nombre y clave por club; club sin clave y sin club
  degradan al defecto.
- Stores, inventario y reconciliación: con `Http::fake`, la petición lleva
  `Authorization: Bearer <clave del club>`.
- Agentes: `Agent::fake()` y `assertPrompted(fn (AgentPrompt $p) => $p->provider->name() === 'openai_vallealto')`;
  el SDK resuelve el proveedor real aun con el gateway falso.
- Voz y transcripción: `Audio::fake()` / `Transcription::fake()` y aserción
  del proveedor si el fake lo expone; si no, por `Http::fake` y la cabecera.

### Despliegue

En cada entorno del asistente: `OPENAI_API_KEY_VALLEALTO` con la clave del
proyecto Vallealto y `OPENAI_VECTOR_STORE_VALLEALTO` con el store de ese
proyecto y entorno (local `vs_6aab5a1e49288191ba481bf0f2eb2f46`, producción
`vs_6aab5a1f1e788191a3e6155005135603`). Para ccm, `OPENAI_API_KEY_CCM` con la
clave actual. Antes de cambiar el store de un club, sus filas se eliminan con `File::remove()`
mientras la config aún apunta al store viejo (así también salen de OpenAI);
después del cambio, `assistant-files:sync` las recrea en el store nuevo. Los stores `vallealto` y `vallealto-prod` creados
en el proyecto de CCM se eliminan.

## Fuera de alcance

- Crear proyectos o claves por API (exige clave de administrador).
- Proyectos para terralta, herradura y saltillo: basta añadir su proveedor y
  su clave cuando existan.
