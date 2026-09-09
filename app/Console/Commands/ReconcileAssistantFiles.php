<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\Files\ReconcileAssistantFilesAction;

final class ReconcileAssistantFiles extends Command
{
    protected $signature = 'assistant-files:reconcile {--dry-run : Solo mostrar lo que se borraría}';

    protected $description = 'Borra de OpenAI los archivos huérfanos, duplicados y sueltos que no tienen fila en files.';

    public function handle(ReconcileAssistantFilesAction $action): int
    {
        $report = $action->report();

        $this->info("Store: {$report->storeFiles} archivos · Cuenta: {$report->accountFiles} · Referenciados: {$report->referenced}");

        foreach (['orphans' => 'Huérfanos (en el store, sin fila)', 'duplicates' => 'Duplicados por nombre', 'loose' => 'Sueltos en la cuenta (fuera del store)'] as $key => $label) {
            $files = $report->{$key};
            $this->line('');
            $this->line("{$label}: {$files->count()}");
            $this->table(['id', 'nombre', 'bytes', 'creado'], $files->map(fn (array $f) => [
                $f['id'], $f['filename'], $f['bytes'], $f['created_at'] ? date('Y-m-d H:i', $f['created_at']) : '',
            ])->all());
        }

        if ($report->isClean()) {
            $this->info('Nada que reconciliar.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no se borró nada.');

            return self::SUCCESS;
        }

        $deleted = $action->apply($report);
        $this->info("Borrados en OpenAI: {$deleted}.");

        return self::SUCCESS;
    }
}
