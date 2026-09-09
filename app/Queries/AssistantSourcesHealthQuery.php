<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\File;
use Cron\CronExpression;
use App\Enums\MediaStatus;
use Illuminate\Support\Carbon;
use App\Dtos\AssistantSourcesHealth;

/**
 * Resumen de salud de las fuentes: cuántas quedaron indexadas, cuándo vuelve a
 * correr la sincronización y contra qué cuenta de OpenAI trabaja el entorno.
 */
final class AssistantSourcesHealthQuery
{
    /**
     * Cuántas corridas del cron se prueban hasta dar con una dentro de la
     * ventana horaria. Con "cada 2 h" y una ventana de 14 h sobran de sobra.
     */
    private const MAX_CANDIDATES = 24;

    public function execute(): AssistantSourcesHealth
    {
        $totals = File::active()
            ->reorder()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as indexed', [MediaStatus::COMPLETED->value])
            ->selectRaw('max(synced_at) as latest_sync')
            ->first();

        $latest = $totals->latest_sync ?? null;
        $sync = (array) config('services.softok2mds.sync');

        return new AssistantSourcesHealth(
            indexed: (int) ($totals->indexed ?? 0),
            total: (int) ($totals->total ?? 0),
            latestSyncAt: $latest === null ? null : Carbon::parse($latest)->toISOString(),
            every: (string) ($sync['label'] ?? ''),
            window: sprintf('De %s a %s', $sync['from'] ?? '00:00', $sync['to'] ?? '23:59'),
            nextRunAt: $this->nextRunAt($sync),
            environment: app()->environment(),
            vectorStoreSuffix: $this->vectorStoreSuffix(),
            expiredCount: File::expired()->count(),
        );
    }

    /**
     * El scheduler solo existe cuando arranca Artisan, así que en una petición
     * HTTP no se puede preguntar. Se recalcula desde la misma config que usa
     * `bootstrap/app.php`, respetando la ventana horaria del `between`.
     *
     * @param  array<string, mixed>  $sync
     */
    private function nextRunAt(array $sync): ?string
    {
        if (! isset($sync['cron']) || ! CronExpression::isValidExpression((string) $sync['cron'])) {
            return null;
        }

        $cron = new CronExpression((string) $sync['cron']);
        [$from, $to] = [(string) ($sync['from'] ?? '00:00'), (string) ($sync['to'] ?? '23:59')];
        $now = Carbon::now();

        for ($nth = 0; $nth < self::MAX_CANDIDATES; $nth++) {
            $candidate = Carbon::instance($cron->getNextRunDate($now, $nth));

            if ($candidate->copy()->between($candidate->copy()->setTimeFromTimeString($from), $candidate->copy()->setTimeFromTimeString($to))) {
                return $candidate->toISOString();
            }
        }

        return null;
    }

    private function vectorStoreSuffix(): ?string
    {
        $store = (string) config('services.openai.vector_store_id');

        return $store === '' ? null : mb_substr($store, -8);
    }
}
