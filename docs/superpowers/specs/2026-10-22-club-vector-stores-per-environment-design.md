# Vector stores por club y entorno, creados por la app

**Fecha:** 2026-10-22
**Estado:** borrador para revisión

## Intención

Hoy el id del vector store de cada club llega por `.env`
(`OPENAI_VECTOR_STORE_CCM`, `OPENAI_VECTOR_STORE_VALLEALTO`). En la práctica
local y stage comparten el mismo store (`ccm-assistant-local`, 32 archivos),
cada entorno sube sus Pentaho encima de los del otro y la reconciliación tuvo
que inventar la noción de archivos "de otro entorno". Además, al cambiar la
clave de CCM a su propio proyecto de OpenAI el store dejó de existir para esa
clave (404) y la sincronización falla en bloque.

Queremos que **cada entorno (local, stage, production) tenga su propio store
por club, creado por la app la primera vez que lo necesita**, y que el id viva
en la base de datos de ese entorno, no en el `.env`.

## Objetivos

- Un store por `(club, entorno, proveedor)`; ningún entorno escribe en el
  store de otro.
- Cero configuración manual: no hay que crear stores en el panel de OpenAI ni
  pegar ids en `.env`.
- Al levantar un entorno nuevo (o cambiar el proyecto de OpenAI de un club) la
  primera sincronización lo deja funcionando sola.
- En el panel de OpenAI cada store se reconoce por nombre: `ccm-assistant-stage`.

## No objetivos

- Migrar los archivos de los stores actuales. Se empieza de cero en todos los
  entornos; los stores viejos se borran a mano en el panel de OpenAI (decisión
  de Franky, 2026-10-22). Los archivos se vuelven a subir desde el disco local
  de cada entorno (ver "Filas existentes").
- Detectar que alguien borró el store en el panel y recrearlo solo. La
  recuperación es manual: borrar la fila y volver a sincronizar; la app
  adopta el store del proyecto que tenga el nombre y la descripción del
  entorno o, si no hay ninguno, crea uno nuevo.
- Compartir un store entre proveedores o entre entornos.
- Cambiar el modelo de permisos de la pantalla de Fuentes.

## Anti-objetivos (lo que haría fracasar esto aunque funcione)

- Que dos jobs en paralelo creen dos stores para el mismo club y entorno.
- Que una copia de la base de producción en local haga que local escriba en el
  store de producción.
- Que las pruebas automatizadas creen stores reales en OpenAI.
- Que el chat de un usuario tarde o falle porque el store aún no existe.

## Restricciones

- Laravel 13, PHP 8.5, `laravel/ai` ^1.3 (`Laravel\Ai\Stores::create(name,
  description, fileIds, expiresWhenIdleFor, provider)`; no acepta metadatos).
- La elección de proveedor por club sigue siendo `App\Ai\ClubAiProvider`
  (`openai_{club}` si tiene clave, si no `openai`). No se toca.
- Pest 5; los tests de Feature usan `RefreshDatabase` sobre sqlite desechable.
- Pint con `final_class`; sin `migrate:fresh` en la base de desarrollo.

## Diseño

### Persistencia

Tabla nueva `vector_stores` (no `club_vector_stores`, y el modelo se llama `VectorStore`, porque el servicio ya se llama `ClubVectorStore`):

| columna | tipo | notas |
|---|---|---|
| `id` | bigint | |
| `club` | string | valor de `ClubName` |
| `environment` | string | `app()->environment()` al crearlo |
| `provider` | string | nombre del proveedor con el que se creó (`openai`, `openai_ccm`…) |
| `store_id` | string | id de OpenAI (`vs_…`) |
| `name` | string | nombre con el que se creó en OpenAI |
| `created_at`, `updated_at` | timestamps | |

Índice único en `(club, environment, provider)`.

Modelo `App\Models\VectorStore` (Eloquent, `final`). Guardar el proveedor
en la clave es lo que hace que cambiar un club a su propio proyecto de OpenAI
(como pasó hoy con CCM) cree un store nuevo en el proyecto correcto sin
intervención. Un cambio de clave dentro del mismo proveedor y proyecto no
necesita nada; un cambio de clave a otro proyecto bajo el mismo nombre de
proveedor requiere borrar la fila (recuperación manual, ver No objetivos).

### Resolución

`App\Ai\Files\ClubVectorStore` (el servicio actual) mantiene su contrato
público, `idFor(ClubName): string` y `storeFor(ClubName): Store`, pero deja de
leer `services.openai.vector_stores`:

1. Busca la fila `(club, app()->environment(), providers->nameFor(club) ?? 'openai')`.
2. Si existe, devuelve su `store_id`.
3. Si no, bajo un candado de caché `club-vector-store:{club}:{environment}:{provider}`
   (bloqueante, con espera corta y TTL de un minuto), vuelve a comprobar la fila
   y, si sigue sin existir, lista los stores del proyecto (`GET vector_stores`)
   y adopta el que tenga a la vez el nombre `{club}-assistant-{environment}` y
   la descripción exacta de abajo: guarda la fila con su id y no crea nada. Uno
   que coincida solo por nombre (el viejo `ccm-assistant-local`) se ignora. Así un `migrate:fresh` (stage lo corre en cada deploy)
   o una fila perdida no dejan stores abandonados. Solo si no hay ninguno con
   ese nombre y descripción llama a `Stores::create` con:
   - `name`: `{club}-assistant-{environment}` (p. ej. `ccm-assistant-stage`).
   - `description`: `Fuentes del asistente · club={club} environment={environment} provider={provider}`.
   - `fileIds`: vacío. `expiresWhenIdleFor`: `null` (no caduca).
   - `provider`: el nombre resuelto.
   Guarda la fila y devuelve el id.
4. Si al crear falla la API (sin clave, sin crédito, red), la excepción se
   propaga tal cual: quien llama ya trata los fallos de OpenAI (la subida marca
   la fila `failed`, el agente de reportes falla el job).

`MissingClubVectorStore` desaparece junto con `configured()`, que ya no usa
nadie en la aplicación. El listado de clubes con fuentes sigue saliendo de
`config/knowledge.php` como hasta ahora.

### Quién crea el store y quién solo lo lee

Regla: **solo la subida crea stores.** Todo lo demás lee la fila y, si no
existe, actúa como si el store estuviera vacío. Así ni una petición HTTP de un
usuario ni una reconciliación de un club sin archivos crean recursos en OpenAI.

`ClubVectorStore` expone dos pares de métodos:

- `idFor(ClubName): string` / `storeFor(ClubName): Store`: devuelven el store,
  creándolo si falta. Único consumidor: `File::upload()` (vía `UploadAssistantDoc`).
- `existingIdFor(ClubName): ?string` / `existingStoreFor(ClubName): ?Store`:
  solo lectura; `null` si la fila no existe. Consumidores:
  - **Chat** (`ClubAssistant::tools()`): sin store, solo `WebSearch`, como hoy.
  - **Reporte semanal** (`ModuleReportAnalyst`): sin store, el agente corre
    sin `FileSearch`; con store vacío, corre igual (OpenAI devuelve cero
    resultados).
  - **Reconciliación e inventario** (`ReconcileAssistantFilesAction`,
    `OpenAiFileInventory::storeFiles`): sin store, el inventario del store es
    la colección vacía y la reconciliación solo ve archivos sueltos de la cuenta.
  - **Borrado** (`File::remove()`, `File::resetForReindex()`): sin store, se
    salta la llamada al store y borra solo el archivo de la cuenta y la fila.
  - **Pantalla de Fuentes** (`AssistantSourcesHealthQuery`): sin store muestra
    el estado "sin crear" que hoy muestra cuando falta configuración.

### Filas existentes en `files`

Las filas `completed` apuntan a documentos del store viejo. Si se dejan así,
el store nuevo quedaría vacío mientras la pantalla dice "Indexado". La
migración que crea la tabla también **devuelve a pendiente** todas las filas no
caducadas (`status = pending`, `assistant_media_id = null`, `synced_at = null`,
`bytes = null`) sin llamar a OpenAI. Como tanto los Pentaho como los documentos
subidos a mano siguen en el disco de cada entorno (`storage docs/{name}`), la
siguiente sincronización los vuelve a subir todos al store nuevo: no hace falta
volver a subir nada a mano. Las filas caducadas (`expired_at` no nulo) no se
tocan: `RemoveExpiredDocs` las limpia y, al no existir ya en el store nuevo,
el 404 cuenta como borrado, igual que hoy.

### Carrera al crear

Si dos workers piden el store a la vez, el candado deja pasar a uno. Si aun así
el insert choca con el índice único (candado caducado, otro proceso), se
captura la violación, se vuelve a leer la fila y se intenta borrar el store
recién creado con `Stores::delete` ignorando fallos. Un store huérfano en
OpenAI es tolerable; dos ids distintos en uso para el mismo club y entorno, no.

### Entornos

`app()->environment()` vale `local` en las máquinas de desarrollo, `stage` en
el servidor de stage (así lo muestra hoy la pantalla de Fuentes) y
`production` en producción. Cada entorno tiene su propia base; aun si dos la
compartieran, la clave incluye el entorno y no se pisarían.

### Configuración que se va

- `services.openai.vector_stores` en `config/services.php`.
- `OPENAI_VECTOR_STORE_CCM` y `OPENAI_VECTOR_STORE_VALLEALTO` en `.env.example`
  y en los `.env` de cada entorno (los `.env` los toca Franky).

### Reconciliación

La lógica de archivos "de otro entorno" (`foreign`) deja de tener sentido con
stores separados, pero no estorba y protege el periodo de transición si un
store viejo se reutilizara por error. Se conserva sin cambios.

### Pruebas

- Helper en `tests/Pest.php`: `clubVectorStore(ClubName $club, string $storeId = 'vs_test'): ClubVectorStore`
  que inserta la fila para `app()->environment()` (`testing`) y el proveedor
  que `ClubAiProvider` resolvería en ese momento. Reemplaza los
  `config(['services.openai.vector_stores…'])` de los tests existentes (unos
  15 archivos).
- Guardia global contra la red: `Http::preventStrayRequests()` en un
  `beforeEach` de Feature en `tests/Pest.php`, para que cualquier test que
  llegue a `Stores::create` (o a cualquier otra llamada a OpenAI) sin fake
  falle en vez de crear recursos reales. No se usa `Stores::fake()` global
  porque varios tests actuales comprueban con `Http::fake` qué clave viajó en
  la petición al store, y un fake del SDK los dejaría sin petición que
  inspeccionar. Si la guardia destapa tests que hoy hacen red sin querer, se
  les añade su fake; no se relaja la guardia.
- Nuevos tests sobre `ClubVectorStore`:
  - crea el store con `Stores::fake()` y el nombre `ccm-assistant-testing`
    cuando no hay fila, y persiste la fila con proveedor y entorno;
  - reutiliza la fila existente sin llamar a `Stores::create`;
  - una fila de otro entorno (`production`) no se usa: se crea otro store;
  - una fila de otro proveedor (`openai`) no se usa cuando el club pasa a
    tener clave propia (`openai_ccm`): se crea otro store;
  - `existingIdFor` devuelve `null` sin crear nada;
  - el chat sin fila ofrece solo `WebSearch` y no crea store;
  - la reconciliación de un club sin fila no crea store y reporta cero
    archivos en el store;
  - la migración devuelve a pendiente las filas `completed` y deja intactas
    las caducadas.
- Los tests existentes de subida, reconciliación, borrado, reportes y pantalla
  de Fuentes siguen pasando con el helper.

### Despliegue

1. Mezclar y desplegar; la migración crea la tabla vacía y devuelve a
   pendiente las filas de `files`.
2. Quitar las dos variables de los `.env` (Franky).
3. "Sincronizar ahora" en cada entorno: crea el store y vuelve a subir todo
   lo que hay en disco. La adopción exige que coincidan nombre y descripción,
   así que el viejo `ccm-assistant-local` nunca se adopta.
4. Borrar en el panel de OpenAI los stores viejos (`ccm-assistant-local`,
   el de Vallealto) cuando ya nadie los use.

## Decisiones que quedan al constructor

- Forma exacta del candado (`Cache::lock(...)->block(5, fn)`) y manejo del
  `LockTimeoutException` (propagar).
- Si `ClubVectorStore` cachea la fila en memoria durante la petición.
- Nombre y ubicación de la factory del modelo si hace falta más allá del helper.
- Cómo `File::remove()` y `resetForReindex()` reparten el trabajo entre
  "borrar del store" y "borrar de la cuenta" cuando no hay store.

## Decisiones tomadas tras la revisión del constructor (2026-10-22)

Las tomó Claude al cerrar la spec; Franky puede revertir cualquiera:

1. La migración devuelve a pendiente las filas de `files` en vez de borrarlas
   o de pedir un comando aparte; los archivos se resuben solos desde disco.
2. Solo `File::upload()` crea stores; el resto lee con `existingIdFor`.
3. Guardia global `Http::preventStrayRequests()` en los tests de Feature.
4. Entornos: `local`, `stage`, `production` tal cual los usa la app hoy.
5. Carrera al crear: capturar la violación del índice único, releer y borrar
   el store sobrante en el mejor esfuerzo.
6. El reporte semanal con store vacío corre igual; sin store, corre sin
   búsqueda en archivos.
