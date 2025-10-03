<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ImportDocsFromPentaho extends Command
{
    protected $signature = 'import:docs-from-pentaho';

    protected $description = 'This command connects to the Pentaho API, retrieves the latest documentation files, and stores them in our local database for easy access and management.';

    public function handle(): void
    {

        \App\Jobs\ImportDocsFromPentaho::dispatch();
    }
}
