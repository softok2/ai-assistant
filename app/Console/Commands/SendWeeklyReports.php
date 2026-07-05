<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\SendWeeklyReportAction;
use App\Models\ReportSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

final class SendWeeklyReports extends Command
{
    protected $signature = 'reports:send-due {--club= : Force-send for a specific club, ignoring the schedule}';

    protected $description = 'Send the AI executive report to clubs whose schedule is due';

    public function handle(SendWeeklyReportAction $action): int
    {
        $forcedClub = $this->option('club');

        $settings = ReportSetting::query()
            ->when($forcedClub, fn ($q) => $q->where('club_name', $forcedClub))
            ->when(! $forcedClub, fn ($q) => $q->where('enabled', true))
            ->get();

        $now = Carbon::now();
        $sent = 0;

        foreach ($settings as $setting) {
            $due = $forcedClub || $setting->frequency->isDue($now, $setting->day_of_week, $setting->hour, $setting->last_sent_at);

            if (! $due) {
                continue;
            }

            try {
                $count = $action->execute($setting);
                $this->info("[{$setting->club_name}] enviado a {$count} destinatario(s).");
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $this->error("[{$setting->club_name}] error: {$exception->getMessage()}");
            }
        }

        $this->info("{$sent} reporte(s) enviado(s).");

        return self::SUCCESS;
    }
}
