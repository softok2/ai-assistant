# Fuentes de conocimiento por club

Fecha: 2026-09-16. Repo: softok2/ai-assistant, rama `stage`.

## Objetivo

Que el asistente consuma el conocimiento del BI de cada club desde su propia
fuente, lo indexe en un vector store exclusivo de ese club, no vuelva a subir
lo que no cambió, y recorte la búsqueda al área del rol cuando el rol es de
una sola área. La separación entre clubes es un requisito duro: ningún camino
de código puede consultar ni escribir un store sin saber de qué club es.

## Situación de partida

- Un solo store (`services.openai.vector_store_id`) con metadata `group` y
  `environment`. La tabla `files` tiene `project` (default `ccm`) pero nadie
  lo escribe ni lo filtra.
- `ImportDocsFromPentaho` baja `ccm/golf-output.md` del MDS de Pentaho, lo
  guarda con timestamp en el nombre y lo sube siempre. `File::expireSiblings`
  caduca los más viejos del mismo `group` sin mirar el club.
- `ClubAssistant::tools()` busca en todo el store sin `where`. Solo
  `ModuleReportAnalyst` filtra por `group`.
- `club_signature_secrets` solo tiene `ccm` y `saltillo`.
- Vallealto (rama `misiones`) ya publica `GET /bi/knowledge/manifest.json` y
  `GET /bi/knowledge/{doc}.md` con Basic Auth, `ETag`/`If-None-Match` y 304.
  Cada entrada del manifiesto trae `name`, `group`, `title`, `bytes`,
  `checksum` (sha256 del markdown) y `generated_at`. Ccm migrará a ese mismo
  mecanismo pronto; Pentaho queda como driver de transición y no se pule.

## Decisiones tomadas con el usuario

1. Un vector store por club (frontera dura), no un store con filtro por
   metadata.
2. Contrato de fuente con dos drivers: Pentaho tal cual para ccm, manifiesto
   de `bi:knowledge` para vallealto. Ccm cambia de driver cuando tenga
   `bi:knowledge`.
3. Filtro por rol solo para roles de una sola área. `admin` y
   `general_service_manager` buscan en todo el store de su club. No se toca el
   enlace firmado.
4. Los roles del asistente adoptan las claves del BI (`<clave>_manager`) y
   conservan los nombres viejos como alias hasta que ccm migre a
   `bi:knowledge`. Estética, masaje, podología y servicios ya no son
   documentos separados: son categorías dentro del documento de bienestar
   (`wellness`).

## Diseño

### 1. Store por club

`config/services.php`:

```php
'openai' => [
    'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
    'vector_stores' => [
        'ccm' => env('OPENAI_VECTOR_STORE_CCM'),
        'vallealto' => env('OPENAI_VECTOR_STORE_VALLEALTO'),
    ],
],
```

`App\Ai\Files\ClubVectorStore` (clase final, inyectable) expone
`idFor(ClubName $club): string` y `storeFor(ClubName $club): Store`
(`Stores::get(...)`). Si el mapa no tiene el club, lanza
`MissingClubVectorStore` con el nombre del club. `vector_store_id` desaparece
de config y de `.env.example`; el store actual pasa a ser el de ccm.

Consumidores que cambian para recibir el club:

- `File::upload()`, `File::removeFromProvider()`: usan `ClubName::from($this->project)`.
- `OpenAiFileInventory::storeFiles(ClubName $club)`.
- `ReconcileAssistantFilesAction::report(ClubName $club, bool $includeUntagged)`
  y `apply(ClubName $club, ...)`: el inventario del store y las filas
  referenciadas se acotan a `project = $club`. Los archivos sueltos de la
  cuenta (`loose`) no tienen club; solo se listan cuando
  `$includeUntagged` y se siguen tratando como hoy.
- `AssistantSourcesHealthQuery::execute(ClubName $club)`: totales y sufijo del
  store del club.
- `ClubAssistant` y `ModuleReportAnalyst`: ver sección 4.

### 2. Fuentes por club

Contrato `App\Ai\Sources\KnowledgeSource`:

```php
interface KnowledgeSource
{
    /** @return iterable<RemoteDocument> */
    public function documents(): iterable;
}
```

`RemoteDocument` es un DTO final de solo lectura: `name` (ruta relativa
dentro del club, p. ej. `golf-live.md`), `group`, `checksum` (sha256 hex) y
un `Closure(): string` `content` que baja el cuerpo solo cuando hace falta.

Drivers, en `App\Ai\Sources\Drivers`:

- `PentahoMdsSource`: la lógica de hoy de `ImportDocsFromPentaho`, movida sin
  cambios de comportamiento. Como el MDS no da checksum, baja el cuerpo y
  calcula el sha256 en el momento (`content` ya resuelto). El `group` sigue
  saliendo del prefijo del nombre antes del primer guion.
- `BiKnowledgeManifestSource`: `GET {base_url}/manifest.json` con Basic Auth
  y `Http::retry(3, 100)`. Cada entrada del manifiesto se vuelve un
  `RemoteDocument` con `checksum` del manifiesto y `content` que hace
  `GET {base_url}/{name}` solo si el import lo pide. No manda
  `If-None-Match`: el checksum del manifiesto ya evita la descarga.

Config `config/knowledge.php`:

```php
return [
    'sources' => [
        'ccm' => [
            'driver' => 'pentaho',
            'base_url' => env('SOFTOK2MDS_BASE_URL'),
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
    'sync' => [ /* cron, from, to, label: se mueven aquí desde services.softok2mds.sync */ ],
];
```

`KnowledgeSourceFactory::for(ClubName $club): KnowledgeSource` construye el
driver a partir del mapa. Un club sin entrada no se sincroniza y se registra
en el log como aviso, sin abortar la corrida de los demás.
`services.softok2mds` desaparece; `SOFTOK2MDS_PROJECTS` deja de existir
(el club es la clave del mapa).

### 3. Sync con checksum

Migración sobre `files`: `checksum` string(64) nullable; índice sobre
`(project, group, expired_at)`. Las filas existentes quedan con
`project = ccm` por el default y `checksum` nulo.

`ImportDocsFromPentaho` se renombra a `ImportKnowledgeDocuments`. Por cada
club del mapa de fuentes:

1. Pide `documents()` al driver.
2. Para cada documento busca la fila activa (`expired_at` nulo) con el mismo
   `project`, `group` y `name`. Si su `checksum` coincide, no hace nada.
3. Si no existe o el checksum cambió, guarda el cuerpo en
   `docs/{project}/{name}` y crea la fila pendiente con `project`, `group`,
   `name`, `checksum`. La ruta en disco pasa a incluir el club para que dos
   clubes con el mismo nombre de archivo no se pisen.
4. Un fallo en un documento se registra y sigue con el siguiente; un fallo en
   el driver completo (manifiesto caído) se registra y sigue con el siguiente
   club.

Nombres y origen: para el manifiesto el `name` es estable (`golf-live.md`) y
las versiones se distinguen por `checksum`. Pentaho sigue generando un nombre
con timestamp por corrida, porque su driver no se toca. `files` gana la
columna `origin` (`pentaho`, `bi_knowledge`, `manual`), escrita por el import
o por la subida manual; `AssistantSourcesQuery` deja de adivinar el origen
por el sufijo del nombre.

`File::expireSiblings()` caduca solo filas más viejas del mismo `project` y
`group`, y además del mismo `name` salvo cuando `origin = pentaho` (ahí el
nombre cambia en cada corrida, así que basta `project` + `group`, que es el
comportamiento actual). Así `golf-live.md` y `golf-annual.md` del mismo club
conviven sin caducarse entre sí, y un club nunca caduca documentos de otro.

`IngestAssistantDocs`, `UploadAssistantDoc`, `RemoveExpiredDocs` y el candado
no cambian de forma: el lote sigue recorriendo `File::pending()` de todos los
clubes, y cada `File` sabe su store por `project`.

### 4. Filtro por rol y store por club en los agentes

Roles: vallealto deriva el rol del enlace como `<clave de bi.reports>_manager`
y hoy sus claves son `golf`, `restaurant`, `tennis`, `paddle`, `futbol`,
`incidences`, `guests` (y `wellness` cuando exista). El asistente valida el
rol con `exists:roles,name`, así que cualquier clave sin rol devuelve 400 y el
usuario no entra. `RoleName` gana los casos `restaurant_manager`,
`futbol_manager`, `incidences_manager`, `guests_manager` y `wellness_manager`,
con su `label()`, `areas()` y `getName()`, y `RoleSeeder` los crea. Los
nombres viejos (`restaurant_captain`, `aesthetics_manager`, `massage_manager`,
`podiatry_manager`) se quedan como alias mientras ccm siga en Pentaho.

`SourceGroup` gana `Wellness = 'wellness'` (etiqueta "Bienestar") y también
`Futbol`, `Incidences` y `Guests`. `Services`, `Massage`, `Aesthetic` y
`Experience` se conservan solo para etiquetar filas viejas de Pentaho.

`RoleName::sourceGroups(): array<int, string>`:

| Rol | Grupos |
|---|---|
| `admin`, `general_service_manager` | `[]` (sin filtro) |
| `golf_manager` | `['golf']` |
| `tennis_manager` | `['tennis']` |
| `paddle_manager` | `['paddle']` |
| `restaurant_manager`, `restaurant_captain` | `['restaurant']` |
| `futbol_manager` | `['futbol']` |
| `incidences_manager` | `['incidences']` |
| `guests_manager` | `['guests']` |
| `wellness_manager` | `['wellness']` |
| `aesthetics_manager` | `['wellness', 'aesthetic']` |
| `massage_manager` | `['wellness', 'massage']` |
| `podiatry_manager` | `['wellness']` |

Los alias de bienestar llevan también su grupo viejo para que en ccm sigan
encontrando sus documentos de Pentaho hasta la migración. Un rol con varios
grupos usa `whereIn`.

`ClubAssistant::tools()`:

```php
$store = $this->stores->idFor($this->club);
$groups = $this->role?->sourceGroups() ?? [];

return [
    $groups === []
        ? new FileSearch(stores: [$store])
        : new FileSearch(stores: [$store], where: fn (FileSearchQuery $q) => $q->whereIn('group', $groups)),
    new WebSearch,
];
```

Si el usuario no tiene club (`$this->club === null`) el agente no ofrece
`FileSearch`: responde solo con `WebSearch` y el prompt. Hoy ese caso solo se
da con usuarios locales de desarrollo.

`ModuleReportAnalyst` recibe `ClubName $club` además de `string $group`, y
`GenerateWeeklyReportAction` se lo pasa desde el `ReportSetting` del club.

`ClubVectorStore` se inyecta en los agentes por el contenedor (los agentes ya
se construyen con `new` en `forUser`; ahí se resuelve con `app()`), sin
cambiar la firma pública de `forUser`.

### 5. Fuentes en pantalla

`AssistantSourcesController` y `AssistantSourcesManagementController`
resuelven el club así: el club del usuario autenticado si lo tiene; si no,
el parámetro `club` de la petición validado contra `ClubName`; si tampoco,
el primer club del mapa de fuentes. La página `Sources.vue` muestra un
selector de club solo cuando el usuario no tiene club fijo. Todas las
acciones (sync, reconciliar, subir, reindexar, borrar, purgar) llevan el club
y operan sobre `project = club`. La subida manual escribe `project`,
`origin = manual` y calcula `checksum` del contenido.

`SourcesHealthStrip` muestra el sufijo del store del club seleccionado.

### 6. Firma de vallealto

`config/app.php`: `'vallealto' => env('VALLEALTO_SIGNATURE_SECRET')` en
`club_signature_secrets`. Se añade a `.env.example`. Nada más cambia en
`AuthenticateExternalUser`.

### 7. Pruebas (Pest, en este repo)

- `ClubVectorStoreTest`: resuelve por club; lanza `MissingClubVectorStore`
  si falta.
- `PentahoMdsSourceTest`: con `Http::fake`, produce un `RemoteDocument` por
  archivo con checksum calculado y grupo del prefijo.
- `BiKnowledgeManifestSourceTest`: lee el manifiesto con Basic Auth, no baja
  cuerpos hasta que se pide `content`, y propaga el checksum del manifiesto.
- `ImportKnowledgeDocumentsTest`: no crea fila si el checksum coincide; crea
  fila pendiente si cambió o no existe; guarda en `docs/{project}/{name}`;
  un manifiesto caído no impide el import del otro club.
- `File` (`UploadAssistantDocTest`): sube al store del `project`;
  `expireSiblings` no toca otro club ni otro nombre del mismo grupo.
- `ReconcileAssistantFilesTest`: el reporte de un club ignora filas y
  archivos del otro.
- `ClubAssistantContextTest`: `FileSearch` apunta al store del club del
  usuario; `golf_manager` lleva `group in [golf]`; `massage_manager` lleva
  `group in [wellness, massage]`; `admin` no lleva filtro; sin club no hay
  `FileSearch`.
- `UserRolesTest` / `ExternalAuthenticationTest`: un enlace con
  `restaurant_manager`, `futbol_manager`, `incidences_manager`,
  `guests_manager` o `wellness_manager` entra tras correr el seeder.
- `WeeklyReportTest`: el analista usa el store del club del `ReportSetting`.
- `SourcesPageTest` y hermanos: la página y cada acción se acotan al club;
  un admin sin club recibe el selector.
- `ExternalAuthenticationTest`: un enlace de vallealto firmado con su secreto
  entra.

Las pruebas existentes que fijan `services.openai.vector_store_id` pasan a
fijar `services.openai.vector_stores.ccm`.

### 8. Despliegue

Asistente:

1. Crear en OpenAI el store de vallealto. El store actual queda como
   `OPENAI_VECTOR_STORE_CCM`.
2. `.env`: `OPENAI_VECTOR_STORE_CCM`, `OPENAI_VECTOR_STORE_VALLEALTO`,
   `VALLEALTO_KNOWLEDGE_URL` (`https://<host de vallealto>/bi/knowledge`),
   `VALLEALTO_KNOWLEDGE_USERNAME/PASSWORD`, `VALLEALTO_SIGNATURE_SECRET`.
   Quitar `OPENAI_VECTOR_STORE_ID` y `SOFTOK2MDS_PROJECTS`.
3. `php artisan migrate` y `php artisan db:seed --class=RoleSeeder` (crea los
   roles nuevos sin tocar los existentes), luego `assistant-files:sync` y
   comprobar en Fuentes
   que vallealto indexa `golf-live.md` y `golf-annual.md`.

Vallealto (ya implementado en la rama `misiones`): `permissions:sync`,
`BI_KNOWLEDGE_USERNAME/PASSWORD` y `SOFTOK2_AI_CHAT_SECRET/URL` en su `.env`,
`bi:knowledge golf`, y verificar el manifiesto con `curl -u`. El secreto de
firma y las credenciales del manifiesto deben coincidir en ambos lados.

## Fuera de alcance

- Portar `bi:knowledge` a ccm (se hace en el repo de ccm; aquí solo cambia su
  entrada del mapa a `bi_knowledge`).
- Mandar la lista de áreas en el enlace firmado para recortar roles
  multi-área.
- Retirar los roles alias (`restaurant_captain`, `aesthetics_manager`,
  `massage_manager`, `podiatry_manager`): se hace cuando ccm migre.
- Mejorar el driver de Pentaho más allá de calcular el checksum.
- Adjuntos de chat de usuarios: no pasan por el vector store y no cambian.
