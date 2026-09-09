<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use App\Enums\MediaStatus;
use Illuminate\Support\Collection;
use App\Ai\Files\OpenAiFileInventory;
use Illuminate\Http\Client\RequestException;

/**
 * Compara lo que hay en OpenAI con las filas de `files` y borra lo sobrante:
 * archivos del store sin fila (huérfanos), nombres repetidos en el store
 * (se conserva el referenciado o, si ninguno lo está, el más nuevo) y
 * archivos de la cuenta que no están en el store ni referenciados.
 *
 * Varios entornos (local, stage, prod) pueden compartir la misma cuenta y el
 * mismo vector store, cada uno con su propia tabla `files`. Por eso cada
 * subida etiqueta el archivo con su entorno y aquí SOLO se tocan los del
 * entorno actual: los de otro entorno se ignoran siempre, y los sin etiqueta
 * (subidos antes de esta versión) y los sueltos de la cuenta, que no se pueden
 * atribuir, solo entran cuando se pide explícitamente `$includeUntagged`.
 */
final class ReconcileAssistantFilesAction
{
    public function __construct(private readonly OpenAiFileInventory $inventory) {}

    public function report(bool $includeUntagged = false): ReconciliationReport
    {
        $referenced = File::query()->whereNotNull('assistant_media_id')->pluck('assistant_media_id')->flip();
        $account = $this->inventory->accountFiles()->keyBy('id');
        $allStoreFiles = $this->inventory->storeFiles()->keyBy('id');
        $environment = app()->environment();

        $foreign = $allStoreFiles->filter(fn (array $file) => $file['environment'] !== null && $file['environment'] !== $environment);
        $untagged = $allStoreFiles->filter(fn (array $file) => $file['environment'] === null);

        $store = $allStoreFiles
            ->reject(fn (array $file, string $id) => $foreign->has($id) || $referenced->has($id) === false && ! $includeUntagged && $untagged->has($id));

        $describe = fn (string $id, string $reason): array => [
            'id' => $id,
            'filename' => $account[$id]['filename'] ?? '',
            'bytes' => $account[$id]['bytes'] ?? 0,
            'created_at' => $account[$id]['created_at'] ?? ($store[$id]['created_at'] ?? 0),
            'reason' => $reason,
        ];

        $orphans = $store->keys()
            ->reject(fn (string $id) => $referenced->has($id))
            ->map(fn (string $id) => $describe($id, 'orphan'));

        $duplicates = $store->keys()
            ->filter(fn (string $id) => $referenced->has($id) || $account->has($id))
            ->groupBy(fn (string $id) => $account[$id]['filename'] ?? $id)
            ->filter(fn (Collection $ids) => $ids->count() > 1)
            ->flatMap(function (Collection $ids) use ($referenced, $account) {
                $keep = $ids->first(fn (string $id) => $referenced->has($id))
                    ?? $ids->sortByDesc(fn (string $id) => $account[$id]['created_at'] ?? 0)->first();

                return $ids->reject(fn (string $id) => $id === $keep);
            })
            ->reject(fn (string $id) => $orphans->contains('id', $id))
            ->map(fn (string $id) => $describe($id, 'duplicate'));

        $loose = $includeUntagged
            ? $account->keys()
                ->reject(fn (string $id) => $allStoreFiles->has($id) || $referenced->has($id))
                ->map(fn (string $id) => $describe($id, 'loose'))
            : collect();

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

    public function apply(ReconciliationReport $report): int
    {
        $store = Stores::get(config('services.openai.vector_store_id'));
        $deleted = collect();

        foreach ($report->orphans->concat($report->duplicates) as $file) {
            $this->ignoringMissing(fn () => $store->remove($file['id'], deleteFile: true));
            $deleted->push($file['id']);
        }

        foreach ($report->loose as $file) {
            $this->ignoringMissing(fn () => Files::delete($file['id']));
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
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }
        }
    }
}
