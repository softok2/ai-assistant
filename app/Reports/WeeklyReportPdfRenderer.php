<?php

declare(strict_types=1);

namespace App\Reports;

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Renders the weekly report to a PDF binary (Chromium via Browsershot).
 */
class WeeklyReportPdfRenderer
{
    public function render(WeeklyReportData $report): string
    {
        $builder = Pdf::view('reports.weekly', ['report' => $report])
            ->format(Format::A4)
            ->margins(0, 0, 0, 0);

        $chromePath = config('services.browsershot.chrome_path');

        if (filled($chromePath)) {
            $builder->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->setChromePath($chromePath));
        }

        return base64_decode($builder->base64());
    }
}
