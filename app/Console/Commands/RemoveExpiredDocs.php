<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class RemoveExpiredDocs extends Command
{
    protected $signature = 'expired-docs:remove';

    protected $description = 'Remove documentation files that have expired based on their expiration date.';

    public function handle(): void
    {
        \App\Jobs\RemoveExpiredDocs::dispatch();
    }
}
