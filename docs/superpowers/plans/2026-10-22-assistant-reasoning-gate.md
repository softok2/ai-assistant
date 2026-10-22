# Gate — razonamiento visible en el stream del asistente

Fecha: 2026-10-22. Rama `feature/assistant-reasoning` (desde `stage` 5b3c094).
Plan: `2026-10-22-assistant-reasoning.md`. Spec: `specs/2026-10-22-assistant-reasoning-design.md`.

## Resultados

| Comprobación | Resultado |
|---|---|
| `php artisan test` | 331 passed (1189 assertions). Base al abrir la rama: 318. Nuevos: 5 opciones de razonamiento, 3 de persistencia en `StreamInsightsTest`, 1 end-to-end en `ChatStreamControllerTest`, 4 de presupuesto de tokens (`AgentTokenBudgetTest`); 2 tests de validación de `model` reescritos. |
| `vendor/bin/pint --test` | passed |
| `vendor/bin/rector --dry-run` | OK, sin cambios |
| `npm run lint` (oxlint + eslint) | sin hallazgos |
| `npm run typecheck` (vue-tsc, TS 6) | sin errores |
| `npm run build` (Vite 8) | OK |
| Saldo OpenAI (sonda `prompt('Responde solo: hola')` vía tinker) | **429 `RateLimitedException` — sin crédito** |
| Humo manual (a)–(f) | **PENDIENTE**: requiere crédito en OpenAI |
| Nota de coste `gpt-4.1-mini` vs `gpt-6-luna` + `low` | **PENDIENTE**: requiere crédito en OpenAI |

## Revisión final (una ronda de correcciones)

- **Topes de tokens**: con un modelo de razonamiento los tokens de razonamiento cuentan dentro de `max_output_tokens`. `ExecutiveSummaryWriter` pasa de 400 a 2000; y, aunque el spec no los tocaba, los agentes con `#[UseCheapestModel]` (que ya resuelve a `gpt-6-luna` desde el upgrade en `stage`) tenían topes de 30/120/300 que podían devolver títulos y sugerencias vacíos: ahora 400/800/1200. Un tope no cuesta nada si no se usa.
- **Duración del razonamiento**: el SDK marca `reasoning_start` al primer delta del resumen, no cuando el modelo empieza a pensar; medir `end − start` daba el tiempo de streaming del resumen. Ahora cada bloque se mide desde el evento anterior (`stream_start`, fin del bloque previo, herramienta). Cambia la fórmula del spec.
- **Texto en vivo**: mientras piensa, `ReasoningTrail` muestra solo el último fragmento recortado a dos líneas (antes el clamp congelaba las dos primeras líneas).

## Qué cambia para el usuario

- Desaparece el selector de modelo del cuadro de chat. El valor `assistant-model` que algunos navegadores guardan en localStorage queda huérfano e inofensivo; si el cliente viejo aún lo envía, el servidor lo ignora (test).
- Con `gpt-6-luna` y `OPENAI_REASONING_EFFORT=low`, mientras llega la respuesta aparece "Pensando…" con el resumen del razonamiento en vivo; al terminar, "Razonó N s" desplegable. Los chats anteriores se pintan como antes.
- Los tres puntos previos a la respuesta siguen, sin el texto "Pensando…" (`role="status"`, texto solo para lectores de pantalla).

## Configuración

- `OPENAI_MODEL` default `gpt-6-luna` (asistente y agentes de reportes). Borrar `OPENAI_MODEL=gpt-4.1-mini` del `.env` para tomar el nuevo default.
- `OPENAI_REASONING_EFFORT` default `low`; vacío desactiva el bloque `reasoning` (y con un modelo sin razonamiento nunca se envía).
- Títulos y sugerencias siguen con `#[UseCheapestModel]`.

## Pendiente antes de mezclar a `stage`

1. Recargar crédito en las cuentas de OpenAI (CCM y Vallealto) y repetir: (a) "Pensando…" con texto en vivo antes del primer token; (b) "Razonó N s" desplegable con títulos separados; (c) sigue tras recargar el chat; (d) con esfuerzo vacío no aparece y el chat responde; (e) un chat antiguo sin `parts.reasoning` se pinta igual; (f) un reporte semanal responde con `gpt-6-luna`.
2. Medir coste y latencia: tres preguntas iguales con `gpt-4.1-mini` y con `gpt-6-luna` + `low`, leyendo `usage` de la respuesta (cambiando `services.openai.model` en tinker, sin tocar `.env`).
