<?php

declare(strict_types=1);

use App\Models\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.openai.vector_store_id' => 'vs_test',
        'ai.providers.openai.key' => 'sk-test',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
    ]);
    Http::preventStrayRequests();
});

function fakeLibraryInventory(): void
{
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 3, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 3]]),
        'https://api.openai.com/v1/vector_stores/vs_test/files?*' => Http::response([
            'data' => [
                ['id' => 'file-keep', 'created_at' => 1_787_767_203],
                ['id' => 'file-dup-old', 'created_at' => 1_785_355_206],
                ['id' => 'file-dup-new', 'created_at' => 1_785_355_207],
                ['id' => 'file-orphan', 'created_at' => 1_785_808_804],
            ],
            'has_more' => false,
        ]),
        'https://api.openai.com/v1/files?*' => Http::response([
            'data' => [
                ['id' => 'file-keep', 'filename' => 'golf-output-1787767203.md', 'bytes' => 31000, 'created_at' => 1_787_767_203],
                ['id' => 'file-dup-old', 'filename' => 'aesthetic-output-1785355206.md', 'bytes' => 18000, 'created_at' => 1_785_355_206],
                ['id' => 'file-dup-new', 'filename' => 'aesthetic-output-1785355206.md', 'bytes' => 18000, 'created_at' => 1_785_355_207],
                ['id' => 'file-orphan', 'filename' => 'golf-output-1785808804.md', 'bytes' => 25000, 'created_at' => 1_785_808_804],
                ['id' => 'file-loose', 'filename' => 'tennis-output-1785348003.md', 'bytes' => 15000, 'created_at' => 1_785_348_003],
            ],
            'has_more' => false,
        ]),
        'https://api.openai.com/v1/vector_stores/vs_test/files/*' => Http::response(['deleted' => true]),
        'https://api.openai.com/v1/files/*' => Http::response(['deleted' => true]),
    ]);

    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);
    File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-new']);
}

it('returns the reconciliation report as json without deleting anything', function () {
    fakeLibraryInventory();

    $this->actingAs(adminUser())
        ->getJson(route('library.reconcile.report'))
        ->assertOk()
        ->assertJsonPath('store_files', 4)
        ->assertJsonPath('account_files', 5)
        ->assertJsonPath('referenced', 2)
        ->assertJsonCount(2, 'orphans')
        ->assertJsonCount(1, 'loose')
        ->assertJsonPath('loose.0.id', 'file-loose')
        ->assertJsonPath('loose.0.filename', 'tennis-output-1785348003.md');

    $report = $this->actingAs(adminUser())->getJson(route('library.reconcile.report'))->json();

    expect(collect($report['orphans'])->pluck('id')->all())
        ->toEqualCanonicalizing(['file-dup-old', 'file-orphan'])
        ->and(collect($report['orphans'])->firstWhere('id', 'file-orphan')['filename'])
        ->toBe('golf-output-1785808804.md');

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('applies the reconciliation and flashes how many files it deleted', function () {
    fakeLibraryInventory();

    $this->actingAs(adminUser())
        ->post(route('library.reconcile.apply'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Se borraron 3 archivos de OpenAI');

    $deleted = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'DELETE')
        ->map(fn ($pair) => $pair[0]->url())
        ->values();

    expect($deleted)->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-orphan')
        ->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-dup-old')
        ->toContain('https://api.openai.com/v1/files/file-loose')
        ->not->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-keep');
});

it('says there is nothing to reconcile when OpenAI matches the database', function () {
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test/files?*' => Http::response(['data' => [['id' => 'file-keep', 'created_at' => 1_787_767_203]], 'has_more' => false]),
        'https://api.openai.com/v1/files?*' => Http::response(['data' => [['id' => 'file-keep', 'filename' => 'golf-output-1787767203.md', 'bytes' => 31000, 'created_at' => 1_787_767_203]], 'has_more' => false]),
    ]);
    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);

    $this->actingAs(adminUser())
        ->post(route('library.reconcile.apply'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Nada que reconciliar');

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('flashes an error when OpenAI does not answer', function () {
    Http::fake(['https://api.openai.com/v1/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    $this->actingAs(adminUser())
        ->post(route('library.reconcile.apply'))
        ->assertRedirect()
        ->assertSessionHas('error');
});
