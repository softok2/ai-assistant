<?php

declare(strict_types=1);

use App\Enums\ClubName;
use App\Ai\Files\ClubVectorStore;
use App\Ai\Files\MissingClubVectorStore;

beforeEach(function () {
    config([
        'services.openai.vector_stores' => [
            'ccm' => 'vs_ccm12345678',
            'vallealto' => 'vs_va123456789',
            'terralta' => null,
        ],
    ]);
});

it('resolves the vector store of a club', function () {
    expect(app(ClubVectorStore::class)->idFor(ClubName::VALLEALTO))->toBe('vs_va123456789');
});

it('lists only the clubs that have a store configured', function () {
    expect(app(ClubVectorStore::class)->configured())->toBe([ClubName::CCM, ClubName::VALLEALTO]);
});

it('fails loudly when a club has no store', function () {
    app(ClubVectorStore::class)->idFor(ClubName::TERRALTA);
})->throws(MissingClubVectorStore::class, 'terralta');
