<?php

declare(strict_types=1);

namespace App\Reports\Contracts;

use App\Reports\WeeklyReportData;

interface RendersWeeklyReportPdf
{
    public function render(WeeklyReportData $report): string;
}
