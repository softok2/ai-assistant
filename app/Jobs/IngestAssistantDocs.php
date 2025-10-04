<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class IngestAssistantDocs
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Media::pending()->lazy()->each->upload();
    }
}
