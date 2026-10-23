<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Models\ReportSetting;
use App\Enums\ReportFrequency;
use App\Mail\WeeklyReportMail;
use Illuminate\Support\Carbon;
use App\Reports\ChartSvgRenderer;
use Laravel\Ai\Prompts\AgentPrompt;
use App\Reports\ReportContentParser;
use Illuminate\Support\Facades\Mail;
use App\Ai\Agents\ModuleReportAnalyst;
use App\Ai\Agents\ExecutiveSummaryWriter;
use League\CommonMark\CommonMarkConverter;
use App\Actions\Reports\SendWeeklyReportAction;
use App\Reports\Contracts\RendersWeeklyReportPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\Reports\GenerateWeeklyReportAction;

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
    config(['ai.providers.openai_ccm.key' => 'sk-ccm']);
    clubVectorStore(ClubName::CCM, 'vs_ccm');

    File::query()->create(['name' => 'golf-x.md', 'group' => 'golf', 'status' => 'completed']);
    File::query()->create(['name' => 'restaurant-x.md', 'group' => 'restaurant', 'status' => 'completed']);
    File::query()->create(['name' => 'vallealto/golf-live.md', 'group' => 'golf', 'status' => 'completed', 'project' => 'vallealto']);

    // indexedGroups() is alphabetical: golf then restaurant.
    ModuleReportAnalyst::fake([
        "#### Resumen\nBuena semana de golf.\n```chart\n{\"type\":\"bar\",\"labels\":[\"Lun\"],\"series\":[{\"data\":[10]}]}\n```",
        'SIN_DATOS',
    ]);
    ExecutiveSummaryWriter::fake(['La semana fue positiva.']);

    $report = resolve(GenerateWeeklyReportAction::class)->execute(ClubName::CCM, ReportFrequency::Weekly);

    expect($report->sections)->toHaveCount(1);
    expect($report->sections[0]['title'])->toBe('Golf');
    expect($report->sections[0]['charts'])->toHaveCount(1);
    expect($report->executiveSummaryHtml)->toContain('positiva');

    ModuleReportAnalyst::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai_ccm');
    ExecutiveSummaryWriter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai_ccm');
});

it('sends the report with a pdf attachment to configured recipients', function (): void {
    clubVectorStore(ClubName::CCM, 'vs_ccm');
    Mail::fake();
    $this->mock(RendersWeeklyReportPdf::class)->shouldReceive('render')->andReturn('%PDF-fake');

    File::query()->create(['name' => 'golf-x.md', 'group' => 'golf', 'status' => 'completed']);
    ModuleReportAnalyst::fake(["#### Resumen\nBuena semana."]);
    ExecutiveSummaryWriter::fake(['Resumen global.']);

    $setting = ReportSetting::query()->create([
        'club_name' => 'ccm',
        'enabled' => true,
        'recipients' => ['director@ccm.test', 'invalido'],
        'frequency' => 'weekly',
        'day_of_week' => 1,
        'hour' => 8,
    ]);

    $count = resolve(SendWeeklyReportAction::class)->execute($setting);

    expect($count)->toBe(1);
    expect($setting->fresh()->last_sent_at)->not->toBeNull();
    Mail::assertSent(WeeklyReportMail::class, fn (WeeklyReportMail $mail) => $mail->hasTo('director@ccm.test'));
});

it('grounds each module on the store of the report club', function (): void {
    clubVectorStore(ClubName::VALLEALTO, 'vs_va');

    $search = collect(new ModuleReportAnalyst(ClubName::VALLEALTO, 'golf')->tools())->first();

    expect($search->ids())->toBe(['vs_va'])
        ->and($search->filters)->toBe([['type' => 'eq', 'key' => 'group', 'value' => 'golf']]);
});

it('throws when there is no indexed content', function (): void {
    Mail::fake();
    $setting = ReportSetting::query()->create([
        'club_name' => 'ccm', 'enabled' => true, 'recipients' => ['d@ccm.test'],
        'frequency' => 'weekly', 'day_of_week' => 1, 'hour' => 8,
    ]);

    expect(fn () => resolve(SendWeeklyReportAction::class)->execute($setting))
        ->toThrow(RuntimeException::class);
});

it('computes due state from frequency, day, hour and last send', function (): void {
    $monday8am = Carbon::now()->startOfWeek()->addHours(8);

    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 8, null))->toBeTrue();
    expect(ReportFrequency::Weekly->isDue($monday8am, 2, 8, null))->toBeFalse();
    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 9, null))->toBeFalse();
    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 8, $monday8am->copy()->subDays(2)))->toBeFalse();
    expect(ReportFrequency::Weekly->isDue($monday8am, 1, 8, $monday8am->copy()->subDays(7)))->toBeTrue();
});

it('runs the module analyst without file search when the club has no store yet', function (): void {
    Stores::fake();

    expect(iterator_to_array(new ModuleReportAnalyst(ClubName::VALLEALTO, 'golf')->tools()))->toBe([]);
    Stores::assertNothingCreated();
});
