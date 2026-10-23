# Gate — vector stores por club y entorno

Fecha: 2026-10-22. Rama `feature/club-vector-stores-per-environment` (desde `stage` 05e4bbc).
Plan: `2026-10-22-club-vector-stores-per-environment.md`. Spec: `specs/2026-10-22-club-vector-stores-per-environment-design.md`.

## Resultados

| Comando | Resultado |
|---|---|
| `vendor/bin/pint --test` | passed |
| `vendor/bin/rector --dry-run` | `[OK] Rector is done!`, sin cambios |
| `php artisan test --compact` | 349 passed (1250 assertions), tras la ola de correcciones de la revisión final |
| `npm run lint` (oxlint + eslint) | sin hallazgos |
| `npm run typecheck` (vue-tsc) | sin errores |
| `npm run build` (Vite) | OK (solo avisos de tamaño de chunk) |
| `grep -rn "vector_stores" app config tests resources/js` sin `vector_stores/` ni `VectorStore` | tras quitar la config solo queda un `assertNotSent` sobre la ruta HTTP `/vector_stores` en `RemoveExpiredDocsTest`; nada lee la configuración vieja |

La ola de correcciones cambió dos textos del frontend (diálogo de reconciliación y franja de salud); lint, typecheck y build pasan.

## Qué cambia

- `services.openai.vector_stores` y las variables `OPENAI_VECTOR_STORE_CCM` / `OPENAI_VECTOR_STORE_VALLEALTO` desaparecen de `config/services.php` y de `.env.example`.
- El store de cada club lo crea la app, por club, entorno y proveedor, en la primera subida, y se guarda en la tabla `vector_stores` (modelo `App\Models\VectorStore`).
- La migración devuelve a pendiente todas las filas de `files`, así que todo lo que hay en disco se vuelve a subir al store nuevo en la primera sincronización.

## Despliegue

En cada entorno, en este orden:

1. Desplegar, correr `php artisan migrate` y reiniciar a la vez los workers de cola (`php artisan queue:restart`) y Octane (`php artisan octane:reload`), para que ningún proceso con el código viejo siga subiendo al store viejo. La migración crea `vector_stores` vacía y devuelve a pendiente las filas de `files`.
2. "Sincronizar ahora". La primera subida crea el store del club, o adopta uno que ya tenga su nombre y su descripción, y vuelve a subir todo lo que hay en disco. La adopción exige que coincidan nombre y descripción, así que el viejo `ccm-assistant-local` nunca se adopta.
3. Revisar las filas `failed`: una fila cuyo archivo ya no está en disco termina `failed` y pierde su copia en el RAG. Si la primera creación del store tarda más de 10 s, otros jobs concurrentes pueden fallar una vez por timeout del candado; basta con reindexarlos o volver a sincronizar.
4. Solo entonces quitar `OPENAI_VECTOR_STORE_CCM` y `OPENAI_VECTOR_STORE_VALLEALTO` del `.env` (lo hace Franky) y borrar en el panel los stores viejos.
5. Con los stores viejos ya borrados, sus archivos quedan sueltos en la cuenta: "Reconciliar" con "Incluir archivos sin etiqueta" los borra. Es seguro desde cualquier entorno, porque solo cuenta como suelto un archivo que no está en ningún store del proyecto.

Un reporte semanal programado entre el deploy y la primera sincronización falla con "no indexed content"; se resuelve solo al sincronizar.

### Stage y `migrate:fresh`

El deploy de stage corre `php artisan migrate:fresh --seed` en cada push y borra la tabla `vector_stores`. Es inofensivo: la siguiente subida adopta el `ccm-assistant-stage` que ya existe (mismo nombre y descripción) en vez de crear otro.

## Recuperación manual

Si un store se borra en el panel de OpenAI, las subidas fallan y el log de error trae club, entorno y `store_id`. Borrar la fila de `vector_stores` (club, entorno y proveedor) y volver a sincronizar: la app adopta un store con el nombre y la descripción del entorno si lo hay o crea uno nuevo, y resube los archivos.

## Pendiente conocido

Las filas caducadas que todavía apuntan al store viejo dejan su archivo en la cuenta al purgarse: el store responde 404 y el SDK se salta el borrado del archivo. Cuando los stores viejos estén borrados, esos archivos ya no están en ningún store y la limpieza de sueltos del paso 5 los quita.
