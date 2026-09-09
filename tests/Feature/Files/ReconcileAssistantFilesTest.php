<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\MediaStatus;
use Illuminate\Support\Facades\Http;
use App\Actions\Files\ReconcileAssistantFilesAction;

beforeEach(function () {
    config(['services.openai.vector_store_id' => 'vs_test', 'ai.providers.openai.key' => 'sk-test', 'ai.providers.openai.url' => 'https://api.openai.com/v1']);
});

function fakeOpenAiInventory(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 4, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 4]]),
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
}

it('reports orphans and duplicates without deleting anything in dry run', function () {
    fakeOpenAiInventory();
    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);
    File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-new']);

    $this->artisan('assistant-files:reconcile', ['--dry-run' => true, '--include-untagged' => true])
        ->expectsOutputToContain('file-dup-old')
        ->expectsOutputToContain('file-orphan')
        ->expectsOutputToContain('file-loose')
        ->expectsOutputToContain('file-legacy')
        ->doesntExpectOutputToContain('file-prod')
        ->assertSuccessful();

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('deletes orphans, duplicates and loose account files but keeps referenced ones', function () {
    fakeOpenAiInventory();
    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);
    File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-new']);

    $this->artisan('assistant-files:reconcile', ['--include-untagged' => true])->assertSuccessful();

    $deleted = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'DELETE')
        ->map(fn ($pair) => $pair[0]->url())
        ->values();

    expect($deleted)->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-dup-old')
        ->toContain('https://api.openai.com/v1/files/file-dup-old')
        ->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-orphan')
        ->toContain('https://api.openai.com/v1/files/file-orphan')
        ->toContain('https://api.openai.com/v1/files/file-loose')
        ->not->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-keep')
        ->not->toContain('https://api.openai.com/v1/files/file-keep')
        ->not->toContain('https://api.openai.com/v1/files/file-dup-new')
        ->not->toContain('https://api.openai.com/v1/vector_stores/vs_test/files/file-prod')
        ->not->toContain('https://api.openai.com/v1/files/file-prod');
});

/**
 * Varios entornos comparten cuenta y vector store: lo de otro entorno nunca se
 * toca, y lo que no se puede atribuir (sin etiqueta o suelto en la cuenta)
 * queda fuera salvo que se pida explícitamente.
 */
it('never touches files of another environment and skips untagged and loose files by default', function () {
    fakeOpenAiInventory();
    File::factory()->completed()->create(['name' => 'golf-output-1787767203.md', 'assistant_media_id' => 'file-keep']);
    File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-new']);

    $report = app(ReconcileAssistantFilesAction::class)->report();

    expect($report->foreign)->toBe(1)
        ->and($report->untagged)->toBe(1)
        ->and($report->orphans->pluck('id')->sort()->values()->all())->toBe(['file-dup-old', 'file-orphan'])
        ->and($report->duplicates)->toBeEmpty()
        ->and($report->loose)->toBeEmpty();

    $this->artisan('assistant-files:reconcile')->assertSuccessful();

    $deleted = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'DELETE')
        ->map(fn ($pair) => basename($pair[0]->url()))
        ->unique()->sort()->values()->all();

    expect($deleted)->toBe(['file-dup-old', 'file-orphan']);
});

/**
 * Si borramos en OpenAI un archivo que una fila todavía referencia, esa fila
 * se quedaría diciendo "Indexado" sin documento detrás.
 */
it('sends back to pending the rows that pointed at a deleted file', function () {
    fakeOpenAiInventory();
    $keep = File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-old']);
    $dropped = File::factory()->completed()->create(['name' => 'aesthetic-output-1785355206.md', 'assistant_media_id' => 'file-dup-new']);

    $this->artisan('assistant-files:reconcile')->assertSuccessful();

    $dropped->refresh();

    expect($dropped->assistant_media_id)->toBeNull()
        ->and($dropped->status)->toBe(MediaStatus::PENDING)
        ->and($dropped->synced_at)->toBeNull()
        ->and($keep->fresh()->assistant_media_id)->toBe('file-dup-old');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/vector_stores/vs_test/files/file-dup-new'));
});
