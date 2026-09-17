<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\File;
use App\Enums\ClubName;
use App\Enums\RoleName;
use App\Enums\MediaStatus;
use App\Dtos\LibrarySnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documentos vivos de la biblioteca: los que siguen activos y ya quedaron
 * indexados en el proveedor. Es lo único que el asistente puede citar, así que
 * el recorte es el mismo que el de `ClubAssistant::tools()`: el store del club
 * de quien pregunta y, si su rol solo cubre unas áreas, esos grupos. Sin club
 * no hay store que buscar y por tanto no hay documentos.
 */
final class LibrarySnapshotQuery
{
    private const MAX_DOCUMENTS = 40;

    /**
     * El conteo y la fecha salen en una sola consulta agregada porque se piden
     * en cada render. La lista de documentos solo la necesita el sugeridor de
     * preguntas, así que se pide aparte.
     */
    public function execute(?ClubName $club, ?RoleName $role = null, bool $withDocuments = false): LibrarySnapshot
    {
        if ($club === null) {
            return new LibrarySnapshot(syncedAt: null, documentCount: 0, documents: []);
        }

        $totals = $this->indexed($club, $role)
            ->reorder()
            ->toBase()
            ->selectRaw('count(*) as documents_count, max(synced_at) as latest_sync')
            ->first();

        $latest = $totals->latest_sync ?? null;

        return new LibrarySnapshot(
            syncedAt: $latest === null ? null : Carbon::parse($latest),
            documentCount: (int) ($totals->documents_count ?? 0),
            documents: $withDocuments ? $this->documents($club, $role) : [],
        );
    }

    /**
     * @return array<int, array{name: string, group: string, synced_at: ?string}>
     */
    private function documents(ClubName $club, ?RoleName $role): array
    {
        return $this->indexed($club, $role)
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
    private function indexed(ClubName $club, ?RoleName $role): Builder
    {
        $groups = $role?->sourceGroups() ?? [];

        return File::query()
            ->active()
            ->where('status', MediaStatus::COMPLETED->value)
            ->where('project', $club->value)
            ->when($groups !== [], fn (Builder $query) => $query->whereIn('group', $groups));
    }
}
