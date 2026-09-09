<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Estado de las fuentes del asistente para la franja de salud: cuántas están
 * indexadas, cuándo corre la siguiente sincronización y contra qué cuenta de
 * OpenAI trabaja este entorno.
 */
final readonly class AssistantSourcesHealth
{
    public function __construct(
        public int $indexed,
        public int $total,
        public ?string $latestSyncAt,
        public string $every,
        public string $window,
        public ?string $nextRunAt,
        public string $environment,
        public ?string $vectorStoreSuffix,
        public int $expiredCount,
    ) {}

    /**
     * @return array{
     *     indexed: int,
     *     total: int,
     *     latest_sync_at: ?string,
     *     schedule: array{every: string, window: string, next_run_at: ?string},
     *     environment: string,
     *     vector_store_suffix: ?string,
     *     expired_count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'indexed' => $this->indexed,
            'total' => $this->total,
            'latest_sync_at' => $this->latestSyncAt,
            'schedule' => [
                'every' => $this->every,
                'window' => $this->window,
                'next_run_at' => $this->nextRunAt,
            ],
            'environment' => $this->environment,
            'vector_store_suffix' => $this->vectorStoreSuffix,
            'expired_count' => $this->expiredCount,
        ];
    }
}
