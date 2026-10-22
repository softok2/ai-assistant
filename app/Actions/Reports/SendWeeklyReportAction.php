<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use RuntimeException;
use App\Enums\ClubName;
use App\Models\ReportSetting;
use App\Mail\WeeklyReportMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Reports\Contracts\RendersWeeklyReportPdf;

/**
 * Generates the report for a club and emails it to the configured recipients.
 */
final readonly class SendWeeklyReportAction
{
    public function __construct(
        private GenerateWeeklyReportAction $generate,
        private RendersWeeklyReportPdf $renderer,
    ) {}

    /**
     * @return int number of recipients the report was sent to
     */
    public function execute(ReportSetting $setting): int
    {
        $recipients = $setting->validRecipients();

        if ($recipients === []) {
            throw new RuntimeException('No hay destinatarios válidos configurados.');
        }

        $club = ClubName::from($setting->club_name);

        $report = $this->generate->execute($club, $setting->frequency, Carbon::now());

        if (! $report->hasContent()) {
            throw new RuntimeException('No hay información indexada para generar el reporte.');
        }

        $pdf = $this->renderer->render($report);

        Mail::to($recipients)->send(new WeeklyReportMail($report, $pdf));

        $setting->forceFill(['last_sent_at' => now()])->save();

        return count($recipients);
    }
}
