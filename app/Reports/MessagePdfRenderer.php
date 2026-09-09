<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Message;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use App\Reports\Contracts\RendersMessageAsPdf;

/**
 * Exporta una respuesta del asistente a PDF. Reusa el mismo parser del reporte
 * semanal para las gráficas y los KPIs, con una hoja mucho más simple.
 */
final class MessagePdfRenderer implements RendersMessageAsPdf
{
    public function __construct(private readonly ReportContentParser $parser) {}

    public function render(Message $message): string
    {
        $parsed = $this->parser->parse((string) ($message->parts['text'] ?? ''));

        $builder = Pdf::view('reports.message', [
            'title' => $message->chat->title,
            'generatedAt' => $message->created_at?->translatedFormat('d \d\e F \d\e Y') ?? '',
            'html' => $parsed['html'],
            'charts' => $parsed['charts'],
            'kpis' => $parsed['kpis'],
        ])
            ->format(Format::A4)
            ->margins(16, 14, 16, 14);

        $chromePath = config('services.browsershot.chrome_path');

        if (filled($chromePath)) {
            $builder->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->setChromePath($chromePath));
        }

        return base64_decode($builder->base64());
    }

    public function filename(Message $message): string
    {
        $slug = Str::slug($message->chat->title);

        return ($slug === '' ? 'respuesta' : $slug).'.pdf';
    }
}
