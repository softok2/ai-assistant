<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class IngestAssistantDocs extends Command
{
    protected $signature = 'assistant:ingest-docs';

    protected $description = 'This command processes and ingests assistant documentation files that have been previously imported and are pending processing.';

    public function handle(): void
    {
        \App\Jobs\IngestAssistantDocs::dispatch();
    }
}
