<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RemoveExpiredDocs;
use Illuminate\Console\Command;
use App\Jobs\IngestAssistantDocs;
use App\Jobs\ImportDocsFromPentaho;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

final class SyncAssistantFiles extends Command
{
    protected $signature = 'assistant-files:sync';

    protected $description = 'Sync assistant files.';

    public function handle(): void
    {
        $lock = Cache::lock('syncing-assistant-files', 300); // 5min timeout

        Bus::chain([
            new ImportDocsFromPentaho,
            new IngestAssistantDocs,
            new RemoveExpiredDocs,
            fn () => Cache::lock('syncing-assistant-files')->forceRelease(), // Don't use $lock->release() here because Serializable closure issue
        ])
            ->catch($lock->release())
            ->dispatchIf($lock->get());

        $this->info('Assistant files sync process has been initiated  and is running in the background.');
    }
}
