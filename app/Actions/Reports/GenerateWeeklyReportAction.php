<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Ai\Agents\ExecutiveSummaryWriter;
use App\Ai\Agents\ModuleReportAnalyst;
use App\Enums\ClubName;
use App\Enums\ReportFrequency;
use App\Models\File;
use App\Reports\ReportContentParser;
use App\Reports\WeeklyReportData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the weekly executive report by having the AI analyse each indexed
 * module (grounded on its documents) and rendering the result to data the
 * PDF view consumes. No side effects — pure generation.
 */
final class GenerateWeeklyReportAction
{
    public function __construct(
        private readonly ReportContentParser $parser,
    ) {
    }

    public function execute(ClubName $club, ReportFrequency $frequency, ?Carbon $on = null): WeeklyReportData
    {
        $on ??= Carbon::now();

        $sections = [];
        $moduleSummaries = [];

        foreach ($this->indexedGroups() as $group) {
            $section = $this->analyseModule($group);

            if ($section !== null) {
                $sections[] = $section;
                $moduleSummaries[] = $section['title'].': '.strip_tags($section['html']);
            }
        }

        return new WeeklyReportData(
            club: $club,
            periodLabel: $frequency->periodLabel($on),
            generatedAt: $on->translatedFormat('d MMMM Y, H:i'),
            executiveSummaryHtml: $this->executiveSummary($moduleSummaries),
            sections: $sections,
        );
    }

    /**
     * @return array<int, string>
     */
    private function indexedGroups(): array
    {
        return File::query()
            ->where('status', 'completed')
            ->whereNull('expired_at')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->filter()
            ->all();
    }

    /**
     * @return array{title: string, html: string, charts: array<int, string>}|null
     */
    private function analyseModule(string $group): ?array
    {
        try {
            $output = (string) (new ModuleReportAnalyst($group))->prompt(
                "Analiza el módulo \"{$group}\" para el reporte ejecutivo del periodo más reciente."
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (Str::contains($output, 'SIN_DATOS') && mb_strlen(trim($output)) < 40) {
            return null;
        }

        $parsed = $this->parser->parse($output);

        if (trim(strip_tags($parsed['html'])) === '' && $parsed['charts'] === []) {
            return null;
        }

        return [
            'title' => Str::of($group)->replace(['-', '_'], ' ')->title()->value(),
            'html' => $parsed['html'],
            'charts' => $parsed['charts'],
        ];
    }

    /**
     * @param  array<int, string>  $moduleSummaries
     */
    private function executiveSummary(array $moduleSummaries): string
    {
        if ($moduleSummaries === []) {
            return '';
        }

        try {
            $summary = (string) (new ExecutiveSummaryWriter)->prompt(implode("\n\n", $moduleSummaries));
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }

        return $this->parser->parse($summary)['html'];
    }
}
