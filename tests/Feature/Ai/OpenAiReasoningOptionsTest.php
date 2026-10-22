<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\Agents\ClubAssistant;
use App\Ai\Agents\ModuleReportAnalyst;

function configureOpenAi(string $model, string $effort = 'low'): void
{
    config([
        'services.openai.model' => $model,
        'services.openai.reasoning_effort' => $effort,
    ]);
}

it('asks openai for a low-effort reasoning summary in the assistant', function (): void {
    configureOpenAi('gpt-6-luna');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('openai'))
        ->toBe(['reasoning' => ['effort' => 'low', 'summary' => 'auto']]);
});

it('asks for low-effort reasoning without a summary in report agents', function (): void {
    configureOpenAi('gpt-6-luna');

    expect(new ModuleReportAnalyst(ClubName::CCM, 'golf')->providerOptions('openai'))
        ->toBe(['reasoning' => ['effort' => 'low']]);
});

it('sends no reasoning options for a model without reasoning', function (): void {
    configureOpenAi('gpt-4.1-mini');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('openai'))->toBe([]);
});

it('sends no reasoning options when the effort is empty', function (): void {
    configureOpenAi('gpt-6-luna', '');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('openai'))->toBe([]);
});

it('sends no reasoning options to other providers', function (): void {
    configureOpenAi('gpt-6-luna');

    expect(ClubAssistant::forUser(adminUser())->providerOptions('anthropic'))->toBe([]);
});
