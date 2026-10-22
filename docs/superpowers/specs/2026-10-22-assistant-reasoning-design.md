# Razonamiento visible en el stream del asistente

Fecha: 2026-10-22. Repo: softok2/ai-assistant. Base: `stage` con el upgrade a
Laravel 13 / laravel/ai 1.3 ya mezclado (requiere que la ronda final de
correcciones, hoy staged, esté commiteada). Rama de trabajo:
`feature/assistant-reasoning`.

## Objetivo

Que el usuario del club vea que el asistente "piensa" antes de la primera
palabra, con el resumen real del razonamiento del modelo, y que ese resumen
quede consultable en el mensaje una vez respondido. Hoy entre enviar la
pregunta y el primer token la pantalla está muda salvo por el rastro de
actividad de herramientas ("Consultando documentos…").

Idea tomada del repo original (`pushpak1300/ai-chat`, eventos `thinking`),
reimplementada sobre `laravel/ai` 1.3, que ya emite `reasoning_start`,
`reasoning_delta` y `reasoning_end` cuando OpenAI manda resúmenes de
razonamiento.

## Decisiones del usuario (Franky)

1. El asistente del club pasa a usar por defecto el modelo `gpt-6-luna`
   (modelo de razonamiento, el "cheapest" de laravel/ai 1.x).
2. Esfuerzo de razonamiento `low` por defecto.
3. Se acepta el coste extra (tokens de razonamiento) y la latencia extra hasta
   el primer texto, a cambio de mostrar el razonamiento.
4. Se elimina el selector de modelo por mensaje de `ChatInput.vue`
   (`app/Enums/ModelName.php`: gpt-4o-mini, gpt-4.1-nano, o4-mini; elección
   guardada en localStorage y enviada en cada stream, donde sobrescribía
   `ClubAssistant::model()`). El modelo del asistente lo fija solo la
   configuración. Se borra el enum, la regla `model` de `ChatStreamRequest`,
   el parámetro `model:` del stream en `ChatStreamController`, el estado en
   localStorage y la UI del selector; si el cliente aún envía `model`, se
   ignora.

## Decisiones del diseñador

- **Solo cambia el `ClubAssistant`.** Títulos, sugerencias y los agentes de
  reportes (`ModuleReportAnalyst`, `ExecutiveSummaryWriter`) siguen con su
  modelo actual. Para no arrastrarlos, el asistente deja de leer
  `services.openai.model` y lee una clave nueva `services.openai.assistant`:
  `model` (env `OPENAI_ASSISTANT_MODEL`, default `gpt-6-luna`) y
  `reasoning_effort` (env `OPENAI_REASONING_EFFORT`, default `low`; vacío
  desactiva el razonamiento).
- **Cómo se pide el razonamiento.** `ClubAssistant` implementa
  `Laravel\Ai\Contracts\HasProviderOptions` y en `providerOptions()` devuelve,
  solo para el driver `openai` y solo si el esfuerzo está configurado y el
  modelo es de razonamiento (prefijos `gpt-5`, `gpt-6`, `o`), el bloque
  `['reasoning' => ['effort' => <effort>, 'summary' => 'auto']]`. Si el modelo
  no soporta razonamiento, no se envía nada (OpenAI rechaza el parámetro con
  400). `laravel/ai` mezcla ese array en el cuerpo de la petición Responses.
- **El stream no cambia de forma.** `ChatStreamController` ya reenvía al
  cliente todos los eventos de `laravel/ai` tal cual; los de razonamiento
  llegan solos. Nada que tocar en el controller salvo la persistencia.
- **Persistencia.** `ResolveStreamInsightsAction` gana una parte `reasoning`
  en `AssistantStreamInsights::toParts()`:
  `['summary' => string, 'seconds' => int]`, construida con
  `ReasoningDelta::combine()` y la diferencia de `timestamp` entre
  `ReasoningStart` y `ReasoningEnd` (mínimo 1 s; los timestamps son segundos).
  Si no hay deltas, la clave no se emite. Se guarda dentro de `parts` del
  mensaje del asistente como ya se guardan `activity` y `sources`. Sin
  migración: `parts` es JSON.
- **UI mientras llega la respuesta.** Un componente nuevo
  `resources/js/components/assistant/ReasoningTrail.vue`, renderizado en
  `MessageBubble.vue` justo encima de `ActivityTrail`, con el mismo lenguaje
  visual (texto `text-xs` muted, icono pequeño). Estado `in_progress`: etiqueta
  "Pensando…" con el resumen creciendo en vivo, recortado a dos líneas
  (`line-clamp-2`), y un control para desplegar el texto completo. El texto se
  muestra como texto plano con `whitespace-pre-wrap`; no se pasa por el
  renderizador de Markdown.
- **UI al terminar.** La entrada se contrae a "Razonó N s" (N = `seconds`),
  plegada, con chevron para desplegar el resumen. Si el mensaje no tiene
  `parts.reasoning`, el componente no renderiza nada. Los mensajes históricos
  se pintan desde `parts.reasoning` con el mismo componente.
- **Cliente del stream.** `useAssistantStream.ts` maneja tres eventos nuevos:
  `reasoning_start` crea el estado `{ summary: '', seconds: null, status:
  'in_progress', startedAt }`; `reasoning_delta` concatena `delta`;
  `reasoning_end` pone `status: 'completed'` y `seconds` (redondeo hacia
  arriba, mínimo 1). Varios bloques de razonamiento en una misma respuesta
  (distintos `reasoning_id`) se concatenan con una línea en blanco. Tipos en
  `resources/js/types/index.d.ts`: `ReasoningPart { summary: string; seconds:
  number | null }` y, en el estado de stream, el mismo tipo más `status`.
- **Accesibilidad.** El control de desplegar es un `<button>` con
  `aria-expanded` y texto visible; el icono lleva `aria-hidden`.
- **Varios bloques de razonamiento en una respuesta** (lo normal con FileSearch
  o WebSearch: razona, busca, razona, escribe): una sola entrada. Pasa a
  "Pensando…" en el primer `reasoning_start` y sigue así hasta el primer
  `text_delta` (o el fin del stream); los bloques se concatenan con una línea en
  blanco. `seconds` = suma de cada bloque (`reasoning_end.timestamp` −
  `reasoning_start.timestamp`), mínimo 1, calculada con los timestamps de los
  eventos tanto en servidor como en cliente para que "en vivo" y "recargado"
  coincidan. La entrada va siempre encima de `ActivityTrail`; no se intercala
  con la actividad de herramientas.
- **El indicador previo de puntos.** `ChatMessageList.vue` ya muestra tres
  puntos con el texto "Pensando…" mientras la petición está en vuelo y antes del
  primer evento. Se mantiene el indicador (es feedback honesto de "petición
  enviada") pero sin texto: solo los puntos, con `aria-label="Esperando
  respuesta"`. "Pensando…" queda reservado para el razonamiento real.
- **Formato del resumen.** OpenAI entrega los resúmenes por partes que suelen
  abrir con un título en negrita (`**Revisando el presupuesto**`) y laravel/ai
  las concatena sin separador. Se persiste el texto crudo; `ReasoningTrail`
  aplica un formateador mínimo solo al renderizar: cada `**…**` se pinta como
  línea de título en negrita precedida de salto de línea, el resto como texto
  plano. No se usa el renderizador de Markdown.
- **Un solo modelo, el de la configuración.** Con el selector eliminado
  (decisión 4), `ClubAssistant::model()` es la única fuente del modelo y
  `providerOptions()` decide el bloque `reasoning` a partir de él.
- **Nada falso.** No hay indicador de "pensando" sin deltas reales. Si el
  modelo no razona o el resumen llega vacío, la UI se comporta como hoy.

## Alcance

Backend:
- `config/services.php`: clave `openai.assistant` (`model`, `reasoning_effort`).
- `.env.example`: `OPENAI_ASSISTANT_MODEL=gpt-6-luna`,
  `OPENAI_REASONING_EFFORT=low`.
- `app/Ai/Agents/ClubAssistant.php`: `implements HasProviderOptions`,
  `model()` lee la clave nueva, `providerOptions(Lab|string $provider): array`.
- `app/Actions/Chats/ResolveStreamInsightsAction.php` y
  `app/Dtos/AssistantStreamInsights.php`: parte `reasoning`.
- Tests Pest: `ClubAssistant` devuelve las opciones de razonamiento con un
  modelo de razonamiento y array vacío con `gpt-4.1-mini` o esfuerzo vacío;
  `ResolveStreamInsightsAction` produce `reasoning` desde una secuencia
  `ReasoningStart` → `ReasoningDelta`×n → `ReasoningEnd` y la omite sin deltas;
  `ChatStreamController` persiste `parts.reasoning` cuando el stream fake
  incluye esos eventos (reutilizar el patrón de stub de gateway del test de
  errores de stream).

Frontend:
- `resources/js/composables/useAssistantStream.ts`: tres eventos nuevos.
- `resources/js/components/assistant/ReasoningTrail.vue`: nuevo.
- `resources/js/components/assistant/MessageBubble.vue`: lo monta.
- `resources/js/components/assistant/ChatMessageList.vue`: indicador de puntos sin texto.
- Eliminar el selector de modelo: `ChatInput.vue` (UI y localStorage), `app/Enums/ModelName.php`, regla `model` en `ChatStreamRequest.php`, parámetro `model:` en `ChatStreamController.php`, y los tests que lo referencien.
- `resources/js/types/index.d.ts`: tipos.

## No objetivos

- Razonamiento en el PDF del mensaje, en la búsqueda de chats o en los
  reportes semanales.
- Selector de esfuerzo en la UI; se configura por `.env`.
- Cambiar el modelo de títulos, sugerencias o reportes.
- Mostrar cadena de pensamiento cruda (OpenAI solo entrega resúmenes).
- Tests de navegador (el proyecto no los tiene).

## Anti-objetivos

- Un "Pensando…" que aparezca sin razonamiento real.
- Que el stream pierda o reordene los eventos que ya existen (`text_delta`,
  herramientas, citas, error).
- Que un modelo sin razonamiento rompa el chat por enviar `reasoning` a
  OpenAI.
- Que el resumen de razonamiento se renderice como Markdown y rompa el layout.

## Verificación

1. `php artisan test` en verde con los tests nuevos.
2. `npm run lint && npm run typecheck && npm run build`.
3. Humo manual en `https://ai-assistant.test`: una pregunta al asistente con
   `gpt-6-luna` muestra "Pensando…" con texto en vivo antes del primer token,
   luego "Razonó N s" desplegable; al recargar el chat la entrada sigue ahí;
   con `OPENAI_REASONING_EFFORT=` vacío no aparece nada y el chat funciona.
   **Requiere crédito en OpenAI**: hoy ambas cuentas de club devuelven 429, así
   que este paso queda pendiente hasta que haya saldo.
4. Comparar en el log/response `usage` el coste de 3 preguntas iguales con
   `gpt-4.1-mini` y con `gpt-6-luna` + `low`, y anotarlo en el documento de
   cierre para que Franky lo vea.

## Qué queda al constructor

- Nombre exacto del icono de lucide para la entrada (algo tipo "cerebro" o
  "sparkles"; que exista en `@lucide/vue` 1.x).
- Cómo partir los commits (sugerido: backend + tests, frontend).
- Si `ReasoningTrail` y `ActivityTrail` comparten un wrapper visual, extraerlo
  solo si evita duplicación real.

## Preguntas abiertas

Ninguna que bloquee. El humo manual depende del saldo de OpenAI.
