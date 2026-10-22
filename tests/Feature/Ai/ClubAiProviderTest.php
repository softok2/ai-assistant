<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\ClubAiProvider;
use App\Ai\MissingOpenAiKey;

beforeEach(function (): void {
    config([
        'ai.providers.openai.key' => 'sk-default',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
        'ai.providers.openai_ccm.key' => 'sk-ccm',
        'ai.providers.openai_vallealto.key' => 'sk-va',
        'ai.providers.openai_vallealto.url' => 'https://va.openai.test/v1',
        'ai.providers.openai_terralta' => null,
    ]);
});

it('names the provider of a club that has its own key', function (): void {
    expect(resolve(ClubAiProvider::class)->nameFor(ClubName::VALLEALTO))->toBe('openai_vallealto')
        ->and(resolve(ClubAiProvider::class)->keyFor(ClubName::VALLEALTO))->toBe('sk-va');
});

it('falls back to the default provider without a club or without a club key', function (): void {
    config(['ai.providers.openai_ccm.key' => '']);

    expect(resolve(ClubAiProvider::class)->nameFor(null))->toBeNull()
        ->and(resolve(ClubAiProvider::class)->nameFor(ClubName::TERRALTA))->toBeNull()
        ->and(resolve(ClubAiProvider::class)->nameFor(ClubName::CCM))->toBeNull()
        ->and(resolve(ClubAiProvider::class)->keyFor(ClubName::CCM))->toBe('sk-default')
        ->and(resolve(ClubAiProvider::class)->keyFor(null))->toBe('sk-default');
});

it('trims the resolved key', function (): void {
    config(['ai.providers.openai_vallealto.key' => "  sk-va\n"]);

    expect(resolve(ClubAiProvider::class)->keyFor(ClubName::VALLEALTO))->toBe('sk-va');
});

it('fails loudly when the resolved provider has no key', function (): void {
    config(['ai.providers.openai.key' => '   ']);

    resolve(ClubAiProvider::class)->keyFor(null);
})->throws(MissingOpenAiKey::class, 'openai');

it('takes the base url from the same provider as the key', function (): void {
    config(['ai.providers.openai_ccm.url' => null]);

    expect(resolve(ClubAiProvider::class)->urlFor(ClubName::VALLEALTO))->toBe('https://va.openai.test/v1')
        ->and(resolve(ClubAiProvider::class)->urlFor(null))->toBe('https://api.openai.com/v1')
        ->and(resolve(ClubAiProvider::class)->urlFor(ClubName::CCM))->toBe('https://api.openai.com/v1');
});
