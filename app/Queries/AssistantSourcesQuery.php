<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\File;
use App\Jobs\SyncLock;
use App\Enums\SourceGroup;
use Illuminate\Support\Collection;

/**
 * Todo lo que la pantalla de Fuentes del asistente necesita para pintarse:
 * los documentos vivos, los caducados que quedan por purgar, los grupos que
 * ofrece la subida manual y si hay una sincronización corriendo.
 */
final class AssistantSourcesQuery
{
    /** Los reportes que llegan de Pentaho llevan el turno en el nombre. */
    private const PENTAHO_NAME = '/-output-\d+\.md$/';

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return [
            'files' => $this->files(),
            'expiredFiles' => $this->expiredFiles(),
            'groups' => $this->groups(),
            'syncRunning' => SyncLock::isHeld(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function files(): array
    {
        return File::active()
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'group', 'status', 'bytes', 'synced_at'])
            ->map(fn (File $file): array => $this->present($file))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiredFiles(): array
    {
        return File::expired()
            ->orderByDesc('expired_at')
            ->get(['id', 'name', 'group', 'status', 'bytes', 'synced_at', 'expired_at', 'assistant_media_id'])
            ->map(fn (File $file): array => $this->present($file) + [
                'expired_at' => $file->expired_at?->toISOString(),
                'assistant_media_id' => $file->assistant_media_id,
            ])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function groups(): Collection
    {
        return File::active()
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
            'origin' => preg_match(self::PENTAHO_NAME, (string) $file->name) === 1 ? 'pentaho' : 'manual',
            'status' => $file->status?->value ?? (string) $file->getRawOriginal('status'),
            'bytes' => $file->bytes === null ? null : (int) $file->bytes,
            'synced_at' => $file->synced_at?->toISOString(),
        ];
    }
}
