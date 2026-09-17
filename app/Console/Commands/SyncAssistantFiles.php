<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\Files\StartAssistantFilesSyncAction;

final class SyncAssistantFiles extends Command
{
    protected $signature = 'assistant-files:sync';

    protected $description = 'Importa los documentos de cada club, indexa lo que cambió en su vector store y limpia los caducados.';

    public function handle(StartAssistantFilesSyncAction $startSync): int
    {
        if (! $startSync->execute()) {
            $this->warn('Ya hay una sincronización en curso; no se inició otra.');

            return self::SUCCESS;
        }

        $this->info('Sincronización de documentos iniciada en segundo plano.');

        return self::SUCCESS;
    }
}
