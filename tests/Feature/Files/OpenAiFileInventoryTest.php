<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use App\Ai\Files\OpenAiFileInventory;

beforeEach(function () {
    config([
        'services.openai.vector_store_id' => 'vs_test',
        'ai.providers.openai.key' => 'sk-test',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
    ]);
    Http::preventStrayRequests();
});

it('follows the pagination of the account files until the last page', function () {
    Http::fake([
        'https://api.openai.com/v1/files*' => Http::sequence()
            ->push([
                'data' => [
                    ['id' => 'file-a1', 'filename' => 'golf-output-1.md', 'bytes' => 100, 'created_at' => 1_785_000_001],
                    ['id' => 'file-a2', 'filename' => 'golf-output-2.md', 'bytes' => 200, 'created_at' => 1_785_000_002],
                ],
                'has_more' => true,
                'last_id' => 'file-a2',
            ])
            ->push([
                'data' => [
                    ['id' => 'file-b1', 'filename' => 'tennis-output-1.md', 'bytes' => 300, 'created_at' => 1_785_000_003],
                ],
                'has_more' => false,
            ]),
    ]);

    $files = (new OpenAiFileInventory)->accountFiles();

    expect($files->pluck('id')->all())->toBe(['file-a1', 'file-a2', 'file-b1'])
        ->and($files->firstWhere('id', 'file-b1')['bytes'])->toBe(300);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'after=file-a2'));
});

it('stops after one page when the store says there is no more', function () {
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test/files*' => Http::response([
            'data' => [['id' => 'file-s1', 'created_at' => 1_785_000_001, 'status' => 'completed']],
            'has_more' => false,
        ]),
    ]);

    expect((new OpenAiFileInventory)->storeFiles()->pluck('id')->all())->toBe(['file-s1']);

    Http::assertSentCount(1);
});
