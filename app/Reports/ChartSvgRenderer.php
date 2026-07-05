<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * Renders a chart spec (the same shape the assistant emits in ```chart blocks)
 * to a standalone SVG string for embedding in the PDF. Mirrors ChartBlock.vue.
 */
final class ChartSvgRenderer
{
    private const W = 640;
    private const H = 260;
    private const PAD_TOP = 16;
    private const PAD_RIGHT = 16;
    private const PAD_BOTTOM = 34;
    private const PAD_LEFT = 44;

    /** Categorical palette (dataviz reference, light mode). */
    private const COLORS = ['#2a78d6', '#1baf7a', '#eda100', '#008300'];

    /**
     * @param  array{type?: string, title?: string, labels?: array, series?: mixed}  $spec
     */
    public function render(array $spec): string
    {
        $type = $this->normalizeType($spec['type'] ?? 'bar');
        $labels = array_values($spec['labels'] ?? []);
        $series = array_slice($this->normalizeSeries($spec['series'] ?? []), 0, 4);

        if ($series === []) {
            return '';
        }

        $body = match ($type) {
            'donut' => $this->donut($labels, $series),
            'line' => $this->grid($labels, $series).$this->line($labels, $series),
            default => $this->grid($labels, $series).$this->bars($labels, $series),
        };

        $w = self::W;
        $h = self::H;

        return "<svg viewBox=\"0 0 {$w} {$h}\" xmlns=\"http://www.w3.org/2000/svg\" width=\"100%\">{$body}</svg>";
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower($type);
        if (in_array($type, ['donut', 'doughnut', 'pie'], true)) {
            return 'donut';
        }

        return $type === 'line' ? 'line' : 'bar';
    }

    /**
     * @return array<int, array{name: ?string, data: array<int, float>}>
     */
    private function normalizeSeries(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        if ($raw !== [] && array_reduce($raw, fn ($c, $v) => $c && is_numeric($v), true)) {
            return [['name' => null, 'data' => array_map('floatval', $raw)]];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_array($item) && array_reduce($item, fn ($c, $v) => $c && is_numeric($v), true)) {
                $out[] = ['name' => null, 'data' => array_map('floatval', $item)];
            } elseif (is_array($item) && isset($item['data']) && is_array($item['data'])) {
                $out[] = ['name' => $item['name'] ?? null, 'data' => array_map('floatval', $item['data'])];
            }
        }

        return $out;
    }

    private function maxValue(array $series): float
    {
        $max = 0.0;
        foreach ($series as $s) {
            foreach ($s['data'] as $v) {
                $max = max($max, $v);
            }
        }

        return $max <= 0 ? 1.0 : $max * 1.12;
    }

    private function yPos(float $value, float $max): float
    {
        return self::H - self::PAD_BOTTOM - ((self::H - self::PAD_TOP - self::PAD_BOTTOM) * $value) / $max;
    }

    private function grid(array $labels, array $series): string
    {
        $max = $this->maxValue($series);
        $svg = '';
        for ($i = 0; $i <= 4; $i++) {
            $value = ($max / 4) * $i;
            $y = round($this->yPos($value, $max), 1);
            $svg .= "<line x1=\"".self::PAD_LEFT."\" x2=\"".(self::W - self::PAD_RIGHT)."\" y1=\"{$y}\" y2=\"{$y}\" stroke=\"#e5e5e5\" stroke-width=\"1\" />";
            $svg .= "<text x=\"".(self::PAD_LEFT - 8)."\" y=\"".($y + 4)."\" text-anchor=\"end\" fill=\"#8a8a8a\" font-size=\"11\" font-family=\"sans-serif\">".round($value)."</text>";
        }

        $count = max(count($labels), 1);
        $innerW = self::W - self::PAD_LEFT - self::PAD_RIGHT;
        $step = $innerW / $count;
        foreach ($labels as $i => $label) {
            $x = round(self::PAD_LEFT + $step * $i + $step / 2, 1);
            $y = self::H - self::PAD_BOTTOM + 18;
            $svg .= "<text x=\"{$x}\" y=\"{$y}\" text-anchor=\"middle\" fill=\"#8a8a8a\" font-size=\"11\" font-family=\"sans-serif\">".htmlspecialchars((string) $label)."</text>";
        }

        return $svg;
    }

    private function bars(array $labels, array $series): string
    {
        $max = $this->maxValue($series);
        $groups = count($series);
        $count = max(count($labels), 1);
        $innerW = self::W - self::PAD_LEFT - self::PAD_RIGHT;
        $step = $innerW / $count;
        $showLabels = $groups === 1 && count($labels) <= 8;

        $svg = '';
        foreach ($labels as $i => $label) {
            $bandX = self::PAD_LEFT + $step * $i;
            $slot = ($step * 0.66) / $groups;
            $start = $bandX + $step * 0.17;
            foreach ($series as $si => $s) {
                $value = $s['data'][$i] ?? 0;
                $y = round($this->yPos($value, $max), 1);
                $x = round($start + $slot * $si + 1, 1);
                $width = round(max($slot - 2, 3), 1);
                $height = round(max(self::H - self::PAD_BOTTOM - $y, 0), 1);
                $color = self::COLORS[$si % count(self::COLORS)];
                $svg .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$width}\" height=\"{$height}\" rx=\"4\" fill=\"{$color}\" />";
                if ($showLabels) {
                    $svg .= "<text x=\"".round($x + $width / 2, 1)."\" y=\"".($y - 6)."\" text-anchor=\"middle\" fill=\"#3a3a3a\" font-size=\"11\" font-weight=\"500\" font-family=\"sans-serif\">".round($value)."</text>";
                }
            }
        }

        return $svg.$this->legend($series);
    }

    private function line(array $labels, array $series): string
    {
        $max = $this->maxValue($series);
        $count = max(count($labels), 1);
        $innerW = self::W - self::PAD_LEFT - self::PAD_RIGHT;
        $step = $innerW / $count;

        $svg = '';
        foreach ($series as $si => $s) {
            $color = self::COLORS[$si % count(self::COLORS)];
            $points = [];
            foreach ($labels as $i => $label) {
                $x = round(self::PAD_LEFT + $step * $i + $step / 2, 1);
                $y = round($this->yPos($s['data'][$i] ?? 0, $max), 1);
                $points[] = [$x, $y];
            }
            $d = '';
            foreach ($points as $i => [$x, $y]) {
                $d .= ($i === 0 ? 'M' : 'L')."{$x},{$y} ";
            }
            $svg .= "<path d=\"".trim($d)."\" fill=\"none\" stroke=\"{$color}\" stroke-width=\"2\" />";
            foreach ($points as [$x, $y]) {
                $svg .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"4\" fill=\"{$color}\" stroke=\"#ffffff\" stroke-width=\"2\" />";
            }
        }

        return $svg.$this->legend($series);
    }

    private function donut(array $labels, array $series): string
    {
        $data = array_slice($series[0]['data'] ?? [], 0, 6);
        $total = array_sum($data) ?: 1;
        $cx = self::W / 2;
        $cy = (self::H - 10) / 2;
        $r = 88;
        $angle = -M_PI / 2;

        $svg = '';
        $legend = [];
        foreach ($data as $i => $value) {
            $sweep = ($value / $total) * M_PI * 2;
            $x1 = round($cx + $r * cos($angle), 1);
            $y1 = round($cy + $r * sin($angle), 1);
            $angle += $sweep;
            $x2 = round($cx + $r * cos($angle), 1);
            $y2 = round($cy + $r * sin($angle), 1);
            $largeArc = $sweep > M_PI ? 1 : 0;
            $color = self::COLORS[$i % count(self::COLORS)];
            $svg .= "<path d=\"M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$largeArc} 1 {$x2},{$y2} Z\" fill=\"{$color}\" stroke=\"#ffffff\" stroke-width=\"2\" />";
            $pct = round(($value / $total) * 100);
            $legend[] = [$color, ($labels[$i] ?? '').' · '.$pct.'%'];
        }
        $svg .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"52\" fill=\"#ffffff\" />";

        return $svg.$this->legendItems($legend, self::H - 6);
    }

    private function legend(array $series): string
    {
        $items = [];
        foreach ($series as $si => $s) {
            if (($s['name'] ?? null) !== null && count($series) >= 2) {
                $items[] = [self::COLORS[$si % count(self::COLORS)], $s['name']];
            }
        }

        return $items === [] ? '' : $this->legendItems($items, self::H - 6);
    }

    private function legendItems(array $items, float $y): string
    {
        $svg = '';
        $x = self::PAD_LEFT;
        foreach ($items as [$color, $label]) {
            $svg .= "<circle cx=\"{$x}\" cy=\"".($y - 4)."\" r=\"4\" fill=\"{$color}\" />";
            $svg .= "<text x=\"".($x + 10)."\" y=\"{$y}\" fill=\"#8a8a8a\" font-size=\"11\" font-family=\"sans-serif\">".htmlspecialchars((string) $label)."</text>";
            $x += 12 + mb_strlen((string) $label) * 6.2 + 16;
        }

        return $svg;
    }
}
