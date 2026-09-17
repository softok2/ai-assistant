<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\File;
use App\Jobs\SyncLock;
use App\Enums\ClubName;
use App\Enums\SourceGroup;
use App\Enums\SourceOrigin;
use Illuminate\Support\Collection;

/**
 * Todo lo que la pantalla de Fuentes del asistente necesita para pintarse:
 * los documentos vivos, los caducados que quedan por purgar, los grupos que
 * ofrece la subida manual y si hay una sincronización corriendo. Todo acotado
 * al club de la pantalla.
 */
final class AssistantSourcesQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(ClubName $club): array
    {
        return [
            'files' => $this->files($club),
            'expiredFiles' => $this->expiredFiles($club),
            'groups' => $this->groups($club),
            'syncRunning' => SyncLock::isHeld(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function files(ClubName $club): array
    {
        return File::active()
            ->where('project', $club->value)
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'group', 'status', 'origin', 'bytes', 'synced_at'])
            ->map(fn (File $file): array => $this->present($file))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiredFiles(ClubName $club): array
    {
        return File::expired()
            ->where('project', $club->value)
            ->orderByDesc('expired_at')
            ->get(['id', 'name', 'group', 'status', 'origin', 'bytes', 'synced_at', 'expired_at', 'assistant_media_id'])
            ->map(fn (File $file): array => $this->present($file) + [
                'expired_at' => $file->expired_at?->toISOString(),
                'assistant_media_id' => $file->assistant_media_id,
            ])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function groups(ClubName $club): Collection
    {
        return File::active()
            ->where('project', $club->value)
            ->distinct()
            ->orderBy('group')
            ->pluck('group');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'group' => $file->group,
            'group_label' => SourceGroup::labelFor($file->group),
            'origin' => $file->origin?->value ?? SourceOrigin::Manual->value,
            'status' => $file->status?->value ?? (string) $file->getRawOriginal('status'),
            'bytes' => $file->bytes === null ? null : (int) $file->bytes,
            'synced_at' => $file->synced_at?->toISOString(),
        ];
    }
}
