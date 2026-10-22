<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ClubName;

final readonly class WeeklyReportData
{
    /**
     * @param  array<int, array{title: string, html: string, charts: array<int, string>}>  $sections
     */
    public function __construct(
        public ClubName $club,
        public string $periodLabel,
        public string $generatedAt,
        public string $executiveSummaryHtml,
        public array $sections,
    ) {}

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
