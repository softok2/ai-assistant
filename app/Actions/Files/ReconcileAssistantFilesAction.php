<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Store;
use App\Enums\ClubName;
use App\Ai\ClubAiProvider;
use App\Enums\MediaStatus;
use App\Ai\Files\ClubVectorStore;
use Illuminate\Support\Collection;
use App\Ai\Files\OpenAiFileInventory;
use Illuminate\Http\Client\RequestException;

/**
 * Compara lo que hay en OpenAI con las filas de `files` y borra lo sobrante:
 * archivos del store sin fila (huérfanos), nombres repetidos en el store
 * (se conserva el referenciado o, si ninguno lo está, el más nuevo) y
 * archivos sueltos de la cuenta: los que no están en NINGÚN vector store del
 * proyecto ni los referencia ninguna fila.
 *
 * Cada club y entorno tiene su store; el reporte y el borrado se acotan al
 * club recibido y al store de este entorno.
 *
 * Los entornos (local, stage, prod) comparten el proyecto de OpenAI del club,
 * así que la cuenta lista los archivos de todos. Huérfanos y duplicados salen
 * solo del store de este entorno, y dentro de él lo etiquetado con otro
 * entorno se ignora siempre. Un archivo adjunto al store de otro entorno nunca
 * es suelto, así que la limpieza de sueltos es segura desde cualquier entorno.
 * Los sin etiqueta (subidos antes de esta versión) y los sueltos solo entran
 * cuando se pide explícitamente `$includeUntagged`.
 */
final readonly class ReconcileAssistantFilesAction
{
    public function __construct(
        private OpenAiFileInventory $inventory,
        private ClubVectorStore $stores,
        private ClubAiProvider $providers,
    ) {}

    public function report(ClubName $club, bool $includeUntagged = false): ReconciliationReport
    {
        $referenced = File::query()->where('project', $club->value)->whereNotNull('assistant_media_id')->pluck('assistant_media_id')->flip();
        $referencedAnywhere = File::query()->whereNotNull('assistant_media_id')->pluck('assistant_media_id')->flip();
        $account = $this->inventory->accountFiles($club)->keyBy('id');
        $allStoreFiles = $this->inventory->storeFiles($club)->keyBy('id');
        $environment = app()->environment();

        $foreign = $allStoreFiles->filter(fn (array $file): bool => $file['environment'] !== null && $file['environment'] !== $environment);
        $untagged = $allStoreFiles->filter(fn (array $file): bool => $file['environment'] === null);

        $store = $allStoreFiles
            ->reject(fn (array $file, string $id): bool => $foreign->has($id) || $referenced->has($id) === false && ! $includeUntagged && $untagged->has($id));

        $describe = fn (string $id, string $reason): array => [
            'id' => $id,
            'filename' => $account[$id]['filename'] ?? '',
            'bytes' => $account[$id]['bytes'] ?? 0,
            'created_at' => $account[$id]['created_at'] ?? ($store[$id]['created_at'] ?? 0),
            'reason' => $reason,
        ];

        $orphans = $store->keys()
            ->reject(fn (string $id) => $referenced->has($id))
            ->map(fn (string $id): array => $describe($id, 'orphan'));

        $duplicates = $store->keys()
            ->filter(fn (string $id): bool => $referenced->has($id) || $account->has($id))
            ->groupBy(fn (string $id) => $account[$id]['filename'] ?? $id)
            ->filter(fn (Collection $ids): bool => $ids->count() > 1)
            ->flatMap(function (Collection $ids) use ($referenced, $account) {
                $keep = $ids->first(fn (string $id) => $referenced->has($id))
                    ?? $ids->sortByDesc(fn (string $id) => $account[$id]['created_at'] ?? 0)->first();

                return $ids->reject(fn (string $id): bool => $id === $keep);
            })
            ->reject(fn (string $id) => $orphans->contains('id', $id))
            ->map(fn (string $id): array => $describe($id, 'duplicate'));

        $loose = collect();

        if ($includeUntagged) {
            $attached = $this->inventory->attachedFileIds($club);

            $loose = $account->keys()
                ->reject(fn (string $id): bool => $allStoreFiles->has($id) || $attached->has($id) || $referencedAnywhere->has($id))
                ->map(fn (string $id): array => $describe($id, 'loose'));
        }

        return new ReconciliationReport(
            storeFiles: $allStoreFiles->count(),
            accountFiles: $account->count(),
            referenced: $referenced->count(),
            foreign: $foreign->count(),
            untagged: $untagged->count(),
            orphans: $orphans->values(),
            duplicates: $duplicates->values(),
            loose: $loose->values(),
        );
    }

    public function apply(ClubName $club, ReconciliationReport $report): int
    {
        $store = $this->stores->existingStoreFor($club);
        $deleted = collect();

        // Sin store el reporte no puede traer huérfanos ni duplicados.
        if ($store instanceof Store) {
            foreach ($report->orphans->concat($report->duplicates) as $file) {
                $this->ignoringMissing(fn (): bool => $store->remove($file['id'], deleteFile: true));
                $deleted->push($file['id']);
            }
        }

        foreach ($report->loose as $file) {
            $this->ignoringMissing(fn () => Files::delete($file['id'], $this->providers->nameFor($club)));
            $deleted->push($file['id']);
        }

        $this->unlinkDeleted($deleted);

        return $deleted->count();
    }

    /**
     * Una fila que apuntaba a algo que acabamos de borrar seguiría diciendo
     * "Indexado" sin documento detrás. La devolvemos a pendiente para que el
     * siguiente sync la vuelva a subir.
     *
     * @param  Collection<int, string>  $ids
     */
    private function unlinkDeleted(Collection $ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        File::query()
            ->whereIn('assistant_media_id', $ids->all())
            ->update([
                'assistant_media_id' => null,
                'status' => MediaStatus::PENDING->value,
                'synced_at' => null,
            ]);
    }

    private function ignoringMissing(callable $callback): void
    {
        try {
            $callback();
        } catch (RequestException $requestException) {
            if ($requestException->response->status() !== 404) {
                throw $requestException;
            }
        }
    }
}
