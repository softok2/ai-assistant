<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\File;
use App\Enums\ClubName;
use App\Enums\SourceOrigin;
use App\Jobs\RemoveExpiredDocs;
use Illuminate\Console\Command;

final class RetireSourceOrigin extends Command
{
    protected $signature = 'assistant-files:retire-origin
        {club : Club cuyos documentos se retiran}
        {origin : Origen a retirar (pentaho, bi_knowledge, manual)}
        {--dry-run : Solo mostrar cuántos se retirarían}';

    protected $description = 'Caduca los documentos activos de un club y origen y despacha su purga.';

    public function handle(): int
    {
        $club = ClubName::tryFrom((string) $this->argument('club'));
        $origin = SourceOrigin::tryFrom((string) $this->argument('origin'));

        if ($club === null || $origin === null) {
            $this->error('Club u origen desconocido.');

            return self::FAILURE;
        }

        $query = File::query()
            ->active()
            ->where('project', $club->value)
            ->where('origin', $origin->value);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->warn("Dry run: se retirarían {$count} documentos de {$club->value} ({$origin->value}).");

            return self::SUCCESS;
        }

        $query->update(['expired_at' => now()]);
        RemoveExpiredDocs::dispatch($club->value);

        $this->info("Retirados {$count} documentos de {$club->value} ({$origin->value}).");

        return self::SUCCESS;
    }
}
