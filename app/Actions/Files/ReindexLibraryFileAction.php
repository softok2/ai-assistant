<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\File;
use App\Jobs\UploadAssistantDoc;

/**
 * Vuelve a subir a OpenAI un documento que ya está en disco. Devuelve false
 * cuando el archivo se perdió del disco y no hay nada que reindexar.
 */
final class ReindexLibraryFileAction
{
    public function execute(File $file): bool
    {
        if (! $file->hasStoredContent()) {
            return false;
        }

        $file->resetForReindex();

        UploadAssistantDoc::dispatch($file->id);

        return true;
    }
}
