<?php

declare(strict_types=1);

namespace App\Mail;

use App\Reports\WeeklyReportData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

final class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly WeeklyReportData $report,
        public readonly string $pdf,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Reporte Ejecutivo — '.$this->report->club->getName().' · '.$this->report->periodLabel)
            ->view('emails.weekly-report', ['report' => $this->report])
            ->attachData($this->pdf, $this->report->filename(), ['mime' => 'application/pdf']);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
