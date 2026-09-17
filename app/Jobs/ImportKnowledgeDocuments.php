<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\File;
use App\Enums\ClubName;
use App\Enums\MediaStatus;
use App\Enums\SourceOrigin;
use Illuminate\Bus\Queueable;
use App\Ai\Sources\RemoteDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use App\Ai\Sources\KnowledgeSourceFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

/**
 * Trae los documentos de la fuente de cada club y deja pendientes SOLO los
 * que cambiaron (por sha256). No caduca nada: eso pasa al indexar el nuevo
 * (File::upload → expireSiblings), para que el store conserve el documento
 * anterior si la subida falla.
 */
final class ImportKnowledgeDocuments implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(?KnowledgeSourceFactory $sources = null): void
    {
        $sources ??= app(KnowledgeSourceFactory::class);

        foreach ($sources->clubs() as $club) {
            $source = $sources->for($club);

            if ($source === null) {
                continue;
            }

            try {
                foreach ($source->documents() as $document) {
                    $this->import($club, $document);
                }
            } catch (Throwable $e) {
                Log::error("Import de conocimiento del club {$club->value} falló: ".$e->getMessage());
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::lock(SyncLock::KEY)->forceRelease();

        Log::error('Import de conocimiento falló: '.($exception?->getMessage() ?? 'sin excepción'));
    }

    private function import(ClubName $club, RemoteDocument $document): void
    {
        $name = $club->value.'/'.$document->name;

        if ($this->current($club, $document)?->checksum === $document->checksum) {
            return;
        }

        try {
            $saved = Storage::put('docs/'.$name, $document->content());
        } catch (Throwable $e) {
            Log::error("No se pudo bajar {$name}: ".$e->getMessage());

            return;
        }

        if ($saved === false) {
            Log::error("No se pudo guardar {$name} en disco.");

            return;
        }

        File::query()->create([
            'project' => $club->value,
            'group' => $document->group,
            'origin' => $document->origin,
            'name' => $name,
            'checksum' => $document->checksum,
            'status' => MediaStatus::PENDING,
        ]);
    }

    /**
     * El documento vigente contra el que se compara el checksum: por nombre
     * cuando el nombre es estable; por grupo para Pentaho, que renombra en
     * cada corrida.
     */
    private function current(ClubName $club, RemoteDocument $document): ?File
    {
        $query = File::active()->where('project', $club->value)->where('group', $document->group);

        if ($document->origin !== SourceOrigin::Pentaho) {
            $query->where('name', $club->value.'/'.$document->name);
        }

        return $query->latest('id')->first();
    }
}
