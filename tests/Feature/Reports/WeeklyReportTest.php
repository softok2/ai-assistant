<?php

declare(strict_types=1);

use App\Actions\Reports\GenerateWeeklyReportAction;
use App\Actions\Reports\SendWeeklyReportAction;
use App\Ai\Agents\ExecutiveSummaryWriter;
use App\Ai\Agents\ModuleReportAnalyst;
use App\Enums\ClubName;
use App\Enums\ReportFrequency;
use App\Mail\WeeklyReportMail;
use App\Models\File;
use App\Models\ReportSetting;
use App\Reports\ChartSvgRenderer;
use App\Reports\ReportContentParser;
use App\Reports\WeeklyReportPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use League\CommonMark\CommonMarkConverter;

uses(RefreshDatabase::class);

it('renders bar, line and donut charts as svg', function (): void {
    $renderer = new ChartSvgRenderer;

    expect($renderer->render(['type' => 'bar', 'labels' => ['A', 'B'], 'series' => [['name' => 'x', 'data' => [1, 2]]]]))->toContain('<rect');
    expect($renderer->render(['type' => 'line', 'labels' => ['A', 'B'], 'series' => [[1, 2]]]))->toContain('<path');
    expect($renderer->render(['type' => 'donut', 'labels' => ['A', 'B'], 'series' => [304, 101]]))->toContain('<path');
});

it('extracts chart blocks and bare chart json from analyst output', function (): void {
    $parser = new ReportContentParser(new ChartSvgRenderer, new CommonMarkConverter);

    $output = "#### Resumen\n\nBuena semana.\n\n```chart\n{\"type\":\"bar\",\"labels\":[\"A\"],\"series\":[{\"data\":[1]}]}\n```\n\n{\"type\":\"donut\",\"labels\":[\"X\",\"Y\"],\"series\":[1,2]}";

    $parsed = $parser->parse($output);

    expect($parsed['charts'])->toHaveCount(2);
    expect($parsed['html'])->toContain('Buena semana');
    expect($parsed['html'])->not->toContain('chart');
});

it('generates report data from indexed modules, skipping empty ones', function (): void {
    File::create(['name' => 'golf-x.md', 'group' => 'golf', 'status' => 'completed']);
    File::create(['name' => 'restaurant-x.md', 'group' => 'restaurant', 'status' => 'completed']);

    // indexedGroups() is alphabetical: golf then restaurant.
    ModuleReportAnalyst::fake([
        "#### Resumen\nBuena semana de golf.\n```chart\n{\"type\":\"bar\",\"labels\":[\"Lun\"],\"series\":[{\"data\":[10]}]}\n```",
        'SIN_DATOS',
    ]);
    ExecutiveSummaryWriter::fake(['La semana fue positiva.']);

    $report = app(GenerateWeeklyReportAction::class)->execute(ClubName::CCM, ReportFrequency::Weekly);

    expect($report->sections)->toHaveCount(1);
    expect($report->sections[0]['title'])->toBe('Golf');
    expect($report->sections[0]['charts'])->toHaveCount(1);
    expect($report->executiveSummaryHtml)->toContain('positiva');
});

it('sends the report with a pdf attachment to configured recipients', function (): void {
    Mail::fake();
    $this->mock(WeeklyReportPdfRenderer::class)->shouldReceive('render')->andReturn('%PDF-fake');

    File::create(['name' => 'golf-x.md', 'group' => 'golf', 'status' => 'completed']);
    ModuleReportAnalyst::fake(["#### Resumen\nBuena semana."]);
    ExecutiveSummaryWriter::fake(['Resumen global.']);

    $setting = ReportSetting::create([
        'club_name' => 'ccm',
        'enabled' => true,
        'recipients' => ['director@ccm.test', 'invalido'],
        'frequency' => 'weekly',
        'day_of_week' => 1,
        'hour' => 8,
    ]);

    $count = app(SendWeeklyReportAction::class)->execute($setting);

    expect($count)->toBe(1);
    expect($setting->fresh()->last_sent_at)->not->toBeNull();
    Mail::assertSent(WeeklyReportMail::class, fn (WeeklyReportMail $mail) => $mail->hasTo('director@ccm.test'));
});

it('throws when there is no indexed content', function (): void {
    Mail::fake();
    $setting = ReportSetting::create([
        'club_name' => 'ccm', 'enabled' => true, 'recipients' => ['d@ccm.test'],
        'frequency' => 'weekly', 'day_of_week' => 1, 'hour' => 8,
    ]);

    expect(fn () => app(SendWeeklyReportAction::class)->execute($setting))
        ->toThrow(RuntimeException::class);
});

it('computes due state from frequency, day, hour and last send', function (): void {
    $monday8am = \Illuminate\Support\Carbon::now()->startOfWeek()->addHours(8);

    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 8, null))->toBeTrue();
    expect(ReportFrequency::Weekly->isDue($monday8am, 2, 8, null))->toBeFalse();
    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 9, null))->toBeFalse();
    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 8, $monday8am->copy()->subDays(2)))->toBeFalse();
    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 8, $monday8am->copy()->subDays(7)))->toBeTrue();
});
