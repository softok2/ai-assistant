<?php

declare(strict_types=1);

namespace App\Actions\Files;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ReconciliationReport implements Arrayable
{
    /**
     * @param  Collection<int, array{id: string, filename: string, bytes: int, created_at: int, reason: string}>  $orphans
     * @param  Collection<int, array{id: string, filename: string, bytes: int, created_at: int, reason: string}>  $duplicates
     * @param  Collection<int, array{id: string, filename: string, bytes: int, created_at: int, reason: string}>  $loose
     */
    public function __construct(
        public int $storeFiles,
        public int $accountFiles,
        public int $referenced,
        public Collection $orphans,
        public Collection $duplicates,
        public Collection $loose,
    ) {}

    public function isClean(): bool
    {
        return $this->orphans->isEmpty() && $this->duplicates->isEmpty() && $this->loose->isEmpty();
    }

    public function toArray(): array
    {
        return [
            'store_files' => $this->storeFiles,
            'account_files' => $this->accountFiles,
            'referenced' => $this->referenced,
            'orphans' => $this->orphans->values()->all(),
            'duplicates' => $this->duplicates->values()->all(),
            'loose' => $this->loose->values()->all(),
        ];
    }
}
