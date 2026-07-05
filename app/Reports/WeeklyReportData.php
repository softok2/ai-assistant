<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ClubName;

final class WeeklyReportData
{
    /**
     * @param  array<int, array{title: string, html: string, charts: array<int, string>}>  $sections
     */
    public function __construct(
        public readonly ClubName $club,
        public readonly string $periodLabel,
        public readonly string $generatedAt,
        public readonly string $executiveSummaryHtml,
        public readonly array $sections,
    ) {
    }

    public function hasContent(): bool
    {
        return $this->sections !== [];
    }

    public function filename(): string
    {
        return 'Reporte-'.$this->club->value.'-'.str(str_replace(' ', '', $this->periodLabel))
            ->slug()->value().'.pdf';
    }
}
