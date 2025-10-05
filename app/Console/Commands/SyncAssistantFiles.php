<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RemoveExpiredDocs;
use Illuminate\Console\Command;
use App\Jobs\IngestAssistantDocs;
use App\Jobs\ImportDocsFromPentaho;
use Illuminate\Support\Facades\Bus;

final class SyncAssistantFiles extends Command
{
    protected $signature = 'assistant-files:sync';

    protected $description = 'Sync assistant files.';

    public function handle(): void
    {
        Bus::chain([
            new ImportDocsFromPentaho,
            new IngestAssistantDocs,
            new RemoveExpiredDocs,
        ])->dispatch();

        $this->info('Assistant files sync process has been initiated  and is running in the background.');
    }
}
