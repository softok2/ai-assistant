<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

/**
 * Reparte los pendientes en un lote de UploadAssistantDoc. Al terminar el
 * lote se limpian los caducados y se libera el candado del sync; si no hay
 * nada pendiente, se hace de inmediato.
 */
final class IngestAssistantDocs implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const BATCH_NAME = 'assistant-docs-upload';

    public static function finish(): void
    {
        RemoveExpiredDocs::dispatch();
        SyncLock::release();
    }

    public function handle(): void
    {
        $jobs = File::pending()
            ->pluck('id')
            ->map(fn (int $id) => new UploadAssistantDoc($id))
            ->all();

        if ($jobs === []) {
            self::finish();

            return;
        }

        Bus::batch($jobs)
            ->name(self::BATCH_NAME)
            ->allowFailures()
            ->finally(static fn () => self::finish())
            ->dispatch();
    }
}
