<?php

declare(strict_types=1);

namespace App\Reports;

use League\CommonMark\CommonMarkConverter;

/**
 * Splits an analyst's markdown output into rendered HTML, chart SVGs and KPI
 * tiles, pulling ```chart and ```kpi blocks (and bare chart JSON lines) out of
 * the prose.
 */
final class ReportContentParser
{
    public function __construct(
        private readonly ChartSvgRenderer $charts,
        private readonly CommonMarkConverter $markdown = new CommonMarkConverter(['html_input' => 'strip']),
    ) {}

    /**
     * @return array{html: string, charts: array<int, string>, kpis: array<int, array{label: string, value: string, delta: ?string, tone: string}>}
     */
    public function parse(string $content): array
    {
        $charts = [];
        $kpis = [];

        // Fenced ```kpi blocks.
        $content = preg_replace_callback('/```kpi\s*\n([\s\S]*?)```/', function (array $m) use (&$kpis): string {
            foreach ($this->toKpis($m[1]) as $kpi) {
                $kpis[] = $kpi;
            }

            return '';
        }, $content) ?? $content;

        // Fenced ```chart blocks.
        $content = preg_replace_callback('/```chart\s*\n([\s\S]*?)```/', function (array $m) use (&$charts): string {
            $svg = $this->toSvg($m[1]);
            if ($svg !== '') {
                $charts[] = $svg;
            }

            return '';
        }, $content) ?? $content;

        // Bare chart JSON lines.
        $content = preg_replace_callback('/^\s*(\{"type".*\})\s*$/m', function (array $m) use (&$charts): string {
            $svg = $this->toSvg($m[1]);
            if ($svg !== '') {
                $charts[] = $svg;

                return '';
            }

            return $m[0];
        }, $content) ?? $content;

        return [
            'html' => $this->markdown->convert(trim($content))->getContent(),
            'charts' => $charts,
            'kpis' => $kpis,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, delta: ?string, tone: string}>
     */
    private function toKpis(string $json): array
    {
        $block = json_decode(trim($json), true);

        if (! is_array($block) || ! is_array($block['items'] ?? null)) {
            return [];
        }

        return collect($block['items'])
            ->filter(fn ($item): bool => is_array($item) && isset($item['label'], $item['value']))
            ->map(fn (array $item): array => [
                'label' => (string) $item['label'],
                'value' => (string) $item['value'],
                'delta' => isset($item['delta']) ? (string) $item['delta'] : null,
                'tone' => in_array($item['tone'] ?? 'neutral', ['good', 'bad', 'neutral'], true)
                    ? (string) $item['tone']
                    : 'neutral',
            ])
            ->take(4)
            ->values()
            ->all();
    }

    private function toSvg(string $json): string
    {
        $spec = json_decode(trim($json), true);

        if (! is_array($spec) || ! isset($spec['labels'])) {
            return '';
        }

        return $this->charts->render($spec);
    }
}
