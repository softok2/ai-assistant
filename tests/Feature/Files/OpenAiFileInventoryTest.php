<?php

declare(strict_types=1);

use App\Enums\ClubName;
use Illuminate\Support\Facades\Http;
use App\Ai\Files\OpenAiFileInventory;

beforeEach(function (): void {
    config([
        'ai.providers.openai.key' => 'sk-test',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
    ]);
    clubVectorStore(ClubName::CCM, 'vs_test');
    Http::preventStrayRequests();
});

it('follows the pagination of the account files until the last page', function (): void {
    $userDataPages = [
        [
            'data' => [
                ['id' => 'file-a1', 'filename' => 'golf-output-1.md', 'bytes' => 100, 'created_at' => 1_785_000_001],
                ['id' => 'file-a2', 'filename' => 'golf-output-2.md', 'bytes' => 200, 'created_at' => 1_785_000_002],
            ],
            'has_more' => true,
            'last_id' => 'file-a2',
        ],
        [
            'data' => [
                ['id' => 'file-b1', 'filename' => 'tennis-output-1.md', 'bytes' => 300, 'created_at' => 1_785_000_003],
            ],
            'has_more' => false,
        ],
    ];

    Http::fake(function ($request) use ($userDataPages) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if (($query['purpose'] ?? null) === 'assistants') {
            return Http::response(['data' => [], 'has_more' => false]);
        }

        return Http::response(isset($query['after']) ? $userDataPages[1] : $userDataPages[0]);
    });

    $files = resolve(OpenAiFileInventory::class)->accountFiles(ClubName::CCM);

    expect($files->pluck('id')->all())->toBe(['file-a1', 'file-a2', 'file-b1'])
        ->and($files->firstWhere('id', 'file-b1')['bytes'])->toBe(300);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'after=file-a2'));
});

it('stops after one page when the store says there is no more', function (): void {
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test/files*' => Http::response([
            'data' => [['id' => 'file-s1', 'created_at' => 1_785_000_001, 'status' => 'completed']],
            'has_more' => false,
        ]),
    ]);

    expect(resolve(OpenAiFileInventory::class)->storeFiles(ClubName::CCM)->pluck('id')->all())->toBe(['file-s1']);

    Http::assertSentCount(1);
});

it('talks to the vector store and account of the club with its own key', function (): void {
    config(['ai.providers.openai_vallealto.key' => 'sk-va']);
    clubVectorStore(ClubName::VALLEALTO, 'vs_va');
    Http::fake([
        '*/vector_stores/vs_va/files?*' => Http::response(['data' => [], 'has_more' => false]),
        '*/files?*' => Http::response(['data' => [], 'has_more' => false]),
    ]);

    resolve(OpenAiFileInventory::class)->storeFiles(ClubName::VALLEALTO);
    resolve(OpenAiFileInventory::class)->accountFiles(ClubName::VALLEALTO);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-va'));
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-test'));
});

it('collects the files attached to every vector store of the project across pages', function (): void {
    Http::fake(function ($request) {
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $after = $query['after'] ?? null;

        return match (true) {
            str_contains($url, '/vector_stores/vs_a/files') => Http::response($after === null
                ? ['data' => [['id' => 'file-a1'], ['id' => 'file-a2']], 'has_more' => true, 'last_id' => 'file-a2']
                : ['data' => [['id' => 'file-a3']], 'has_more' => false]),
            str_contains($url, '/vector_stores/vs_b/files') => Http::response(['data' => [['id' => 'file-b1'], ['id' => 'file-a1']], 'has_more' => false]),
            str_contains($url, '/vector_stores') => Http::response($after === null
                ? ['data' => [['id' => 'vs_a', 'name' => 'ccm-assistant-local']], 'has_more' => true, 'last_id' => 'vs_a']
                : ['data' => [['id' => 'vs_b', 'name' => 'ccm-assistant-stage']], 'has_more' => false]),
        };
    });

    $ids = resolve(OpenAiFileInventory::class)->attachedFileIds(ClubName::CCM);

    expect($ids->keys()->sort()->values()->all())->toBe(['file-a1', 'file-a2', 'file-a3', 'file-b1']);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/vector_stores?') && str_contains($request->url(), 'after=vs_a'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/vector_stores/vs_a/files?') && str_contains($request->url(), 'after=file-a2'));
});
