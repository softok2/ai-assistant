<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\File;
use App\Enums\MediaStatus;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

/**
 * Sube UN documento al vector store. Un job por archivo (antes uno solo subía
 * todos y, al pasar de retry_after, la cola lo reasignaba a otro worker que
 * volvía a subirlos: de ahí los duplicados en OpenAI). Único por archivo,
 * sin reintentos automáticos y con timeout holgado para la indexación.
 */
final class UploadAssistantDoc implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $fileId) {}

    public function uniqueId(): string
    {
        return (string) $this->fileId;
    }

    public function handle(): void
    {
        $file = File::claimForUpload($this->fileId);

        if ($file === null) {
            return;
        }

        $file->upload();
    }

    /**
     * Un timeout o un `queue:restart` a media subida dejaba la fila en
     * "Procesando" para siempre. Al fallar la marcamos como fallida para que
     * se pueda reindexar.
     */
    public function failed(?Throwable $exception): void
    {
        File::query()
            ->whereKey($this->fileId)
            ->where('status', MediaStatus::IN_PROGRESS->value)
            ->update(['status' => MediaStatus::FAILED->value]);

        Log::error("Upload job failed for file {$this->fileId}: ".($exception?->getMessage() ?? 'sin excepción'));
    }
}
