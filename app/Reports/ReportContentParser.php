<?php

declare(strict_types=1);

namespace App\Reports;

use League\CommonMark\CommonMarkConverter;

/**
 * Splits an analyst's markdown output into rendered HTML and chart SVGs,
 * pulling ```chart blocks (and bare chart JSON lines) out of the prose.
 */
final class ReportContentParser
{
    public function __construct(
        private readonly ChartSvgRenderer $charts,
        private readonly CommonMarkConverter $markdown = new CommonMarkConverter(['html_input' => 'strip']),
    ) {
    }

    /**
     * @return array{html: string, charts: array<int, string>}
     */
    public function parse(string $content): array
    {
        $charts = [];

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
        ];
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
