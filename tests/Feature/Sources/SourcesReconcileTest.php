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

/**
 * El entorno de los tests es "testing": `file-prod` pertenece a otro entorno y
 * `file-legacy` viene de antes de que se etiquetaran las subidas.
 */
function fakeSourcesInventory(): void
{
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 6, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 6]]),
        'https://api.openai.com/v1/vector_stores/vs_test/files?*' => Http::response([
            'data' => [
                ['id' => 'file-keep', 'created_at' => 1_787_767_203, 'attributes' => ['environment' => 'testing']],
                ['id' => 'file-dup-old', 'created_at' => 1_785_355_206, 'attributes' => ['environment' => 'testing']],
                ['id' => 'file-dup-new', 'created_at' => 1_785_355_207, 'attributes' => ['environment' => 'testing']],
                ['id' => 'file-orphan', 'created_at' => 1_785_808_804, 'attributes' => ['environment' => 'testing']],
                ['id' => 'file-prod', 'created_at' => 1_788_962_405, 'attributes' => ['environment' => 'production']],
                ['id' => 'file-legacy', 'created_at' => 1_780_000_000],
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
                ['id' => 'file-prod', 'filename' => 'golf-output-1788962405.md', 'bytes' => 28000, 'created_at' => 1_788_962_405],
                ['id' => 'file-legacy', 'filename' => 'services-output-1780000000.md', 'bytes' => 13000, 'created_at' => 1_780_000_000],
            ],
            'has_more' => false,
        ]),
        'https://api.openai.com/v1/vector_stores/vs_test/files/*' => Http::response(['deleted' => true]),
        'https://api.openai.com/v1/files/*' => Http::response(['deleted' => true]),
    ]);

    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);
    File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-new']);
}

/**
 * @return array<int, string>
 */
function deletedOpenAiIds(): array
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'DELETE')
        ->map(fn ($pair) => basename($pair[0]->url()))
        ->unique()->sort()->values()->all();
}

it('returns the reconciliation report as json without deleting anything', function () {
    fakeSourcesInventory();

    $report = $this->actingAs(adminUser())
        ->getJson(route('sources.reconcile.report'))
        ->assertOk()
        ->assertJsonPath('store_files', 6)
        ->assertJsonPath('account_files', 7)
        ->assertJsonPath('referenced', 2)
        ->assertJsonPath('foreign', 1)
        ->assertJsonPath('untagged', 1)
        ->assertJsonCount(0, 'loose')
        ->json();

    expect(collect($report['orphans'])->pluck('id')->all())
        ->toEqualCanonicalizing(['file-dup-old', 'file-orphan'])
        ->and(collect($report['orphans'])->firstWhere('id', 'file-orphan')['filename'])
        ->toBe('golf-output-1785808804.md');

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('leaves the untagged and loose files out of the report unless they are asked for', function () {
    fakeSourcesInventory();

    $withUntagged = $this->actingAs(adminUser())
        ->getJson(route('sources.reconcile.report', ['include_untagged' => 1]))
        ->assertOk()
        ->assertJsonPath('foreign', 1)
        ->assertJsonPath('untagged', 1)
        ->json();

    expect(collect($withUntagged['orphans'])->pluck('id')->all())
        ->toEqualCanonicalizing(['file-dup-old', 'file-orphan', 'file-legacy'])
        ->and(collect($withUntagged['loose'])->pluck('id')->all())
        ->toBe(['file-loose']);
});

it('applies the reconciliation and flashes how many files it deleted', function () {
    fakeSourcesInventory();

    $this->actingAs(adminUser())
        ->post(route('sources.reconcile.apply'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Se borraron 2 archivos de OpenAI');

    expect(deletedOpenAiIds())->toBe(['file-dup-old', 'file-orphan']);
});

it('never deletes a file that belongs to another environment', function () {
    fakeSourcesInventory();

    $this->actingAs(adminUser())
        ->post(route('sources.reconcile.apply'), ['include_untagged' => true])
        ->assertRedirect()
        ->assertSessionHas('success', 'Se borraron 4 archivos de OpenAI');

    expect(deletedOpenAiIds())
        ->toContain('file-legacy')
        ->toContain('file-loose')
        ->not->toContain('file-prod')
        ->not->toContain('file-keep');
});

it('says there is nothing to reconcile when OpenAI matches the database', function () {
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test/files?*' => Http::response(['data' => [['id' => 'file-keep', 'created_at' => 1_787_767_203, 'attributes' => ['environment' => 'testing']]], 'has_more' => false]),
        'https://api.openai.com/v1/files?*' => Http::response(['data' => [['id' => 'file-keep', 'filename' => 'golf-output-1787767203.md', 'bytes' => 31000, 'created_at' => 1_787_767_203]], 'has_more' => false]),
    ]);
    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);

    $this->actingAs(adminUser())
        ->post(route('sources.reconcile.apply'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Nada que reconciliar');

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('rejects a request that does not carry a boolean flag', function () {
    $this->actingAs(adminUser())
        ->postJson(route('sources.reconcile.apply'), ['include_untagged' => 'quizá'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('include_untagged');
});

it('flashes an error when OpenAI does not answer', function () {
    Http::fake(['https://api.openai.com/v1/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    $this->actingAs(adminUser())
        ->post(route('sources.reconcile.apply'))
        ->assertRedirect()
        ->assertSessionHas('error');
});
