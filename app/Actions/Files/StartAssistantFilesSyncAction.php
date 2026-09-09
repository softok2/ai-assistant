<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Jobs\SyncLock;
use App\Jobs\IngestAssistantDocs;
use App\Jobs\ImportDocsFromPentaho;
use Illuminate\Support\Facades\Bus;

/**
 * Arranca la sincronización de documentos: importa de Pentaho y luego indexa
 * lo pendiente. El candado se toma aquí y lo libera la ingesta al terminar (o
 * el `catch` de la cadena si algo revienta), para que dos corridas nunca suban
 * los mismos archivos.
 */
final class StartAssistantFilesSyncAction
{
    /**
     * Devuelve false cuando ya hay una sincronización en curso.
     */
    public function execute(): bool
    {
        if (SyncLock::acquire() === null) {
            return false;
        }

        Bus::chain([
            new ImportDocsFromPentaho,
            new IngestAssistantDocs,
        ])
            ->catch(static fn () => SyncLock::release())
            ->dispatch();

        return true;
    }
}
