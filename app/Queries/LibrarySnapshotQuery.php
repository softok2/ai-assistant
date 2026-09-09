<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\File;
use App\Enums\MediaStatus;
use App\Dtos\LibrarySnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos vivos de la biblioteca: los que siguen activos y ya quedaron
 * indexados en el proveedor. Es lo único que el asistente puede citar.
 */
final class LibrarySnapshotQuery
{
    private const MAX_DOCUMENTS = 40;

    /**
     * El conteo y la fecha salen en una sola consulta agregada porque se piden
     * en cada render. La lista de documentos solo la necesita el sugeridor de
     * preguntas, así que se pide aparte.
     */
    public function execute(bool $withDocuments = false): LibrarySnapshot
    {
        $totals = $this->indexed()
            ->reorder()
            ->toBase()
            ->selectRaw('count(*) as documents_count, max(synced_at) as latest_sync')
            ->first();

        $latest = $totals->latest_sync ?? null;

        return new LibrarySnapshot(
            syncedAt: $latest === null ? null : Carbon::parse($latest),
            documentCount: (int) ($totals->documents_count ?? 0),
            documents: $withDocuments ? $this->documents() : [],
        );
    }

    /**
     * @return array<int, array{name: string, group: string, synced_at: ?string}>
     */
    private function documents(): array
    {
        return $this->indexed()
            ->orderByDesc('synced_at')
            ->limit(self::MAX_DOCUMENTS)
            ->get(['name', 'group', 'synced_at'])
            ->map(fn (File $file): array => [
                'name' => (string) $file->name,
                'group' => (string) $file->group,
                'synced_at' => $file->synced_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * @return Builder<File>
     */
    private function indexed(): Builder
    {
        return File::query()->active()->where('status', MediaStatus::COMPLETED->value);
    }
}
