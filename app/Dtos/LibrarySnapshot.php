<?php

declare(strict_types=1);

namespace App\Dtos;

use Illuminate\Support\Carbon;

/**
 * Qué tiene indexado la biblioteca del club ahora mismo: cuántos documentos
 * activos hay, de cuándo es el más reciente y cuáles son.
 */
final class LibrarySnapshot
{
    /**
     * @param  array<int, array{name: string, group: string, synced_at: ?string}>  $documents
     */
    public function __construct(
        public readonly ?Carbon $syncedAt = null,
        public readonly int $documentCount = 0,
        public readonly array $documents = [],
    ) {}

    public function freshness(): ?string
    {
        return $this->syncedAt?->toISOString();
    }

    public function isEmpty(): bool
    {
        return $this->documentCount === 0;
    }
}
