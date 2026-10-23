<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use App\Models\VectorStore;
use App\Jobs\RemoveExpiredDocs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    config(['ai.providers.openai.key' => 'sk-test']);
    Http::preventStrayRequests();
});

it('removes an expired doc from the store, the disk and the database', function (): void {
    clubVectorStore(ClubName::CCM, 'vs_test');
    Http::fake([
        '*/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 1, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 1]]),
        '*/vector_stores/vs_test/files/file-abc' => Http::response(['deleted' => true]),
        '*/files/file-abc' => Http::response(['deleted' => true]),
    ]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-abc']);
    Storage::put('docs/'.$file->name, '# viejo');

    (new RemoveExpiredDocs)->handle();

    expect(File::query()->find($file->id))->toBeNull();
    Storage::assertMissing('docs/'.$file->name);
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/vector_stores/vs_test/files/file-abc'));
});

/**
 * Un archivo que ya no existe en OpenAI (borrado a mano o por una
 * reconciliación) no debe dejar la fila caducada reintentándose para siempre.
 */
it('treats a 404 from OpenAI as already removed', function (): void {
    clubVectorStore(ClubName::CCM, 'vs_test');
    Http::fake([
        '*/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 1, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 1]]),
        '*/vector_stores/vs_test/files/file-gone' => Http::response(['error' => ['message' => 'No such file']], 404),
        '*/files/file-gone' => Http::response(['error' => ['message' => 'No such file']], 404),
    ]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-gone']);
    Storage::put('docs/'.$file->name, '# viejo');

    (new RemoveExpiredDocs)->handle();

    expect(File::query()->find($file->id))->toBeNull();
    Storage::assertMissing('docs/'.$file->name);
});

it('removes expired docs that never reached OpenAI without calling the API', function (): void {
    Http::fake();
    $file = File::factory()->expired()->create(['assistant_media_id' => null]);
    Storage::put('docs/'.$file->name, '# nunca subido');

    (new RemoveExpiredDocs)->handle();

    expect(File::query()->find($file->id))->toBeNull();
    Http::assertNothingSent();
});

it('keeps the row when OpenAI fails for another reason', function (): void {
    clubVectorStore(ClubName::CCM, 'vs_test');
    Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-err']);

    (new RemoveExpiredDocs)->handle();

    expect(File::query()->find($file->id))->not->toBeNull();
});

/**
 * Sin `uniqueId()` propio, `ShouldBeUniqueUntilProcessing` usa la clase como
 * llave: una purga de ccm en curso bloquearía la de vallealto.
 */
it('keys uniqueness by project so two clubs can purge at the same time', function (): void {
    expect(new RemoveExpiredDocs('ccm')->uniqueId())->toBe('ccm')
        ->and(new RemoveExpiredDocs('vallealto')->uniqueId())->toBe('vallealto')
        ->and((new RemoveExpiredDocs)->uniqueId())->toBe('all');
});

it('talks to OpenAI with the key of the club that owns the document', function (): void {
    config(['ai.providers.openai_ccm.key' => 'sk-ccm']);
    clubVectorStore(ClubName::CCM, 'vs_test');
    Http::fake([
        '*/vector_stores/vs_test' => Http::response(['id' => 'vs_test', 'name' => 'ccm', 'status' => 'completed', 'file_counts' => ['completed' => 1, 'in_progress' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 1]]),
        '*/vector_stores/vs_test/files/file-abc' => Http::response(['deleted' => true]),
        '*/files/file-abc' => Http::response(['deleted' => true]),
    ]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-abc']);
    Storage::put('docs/'.$file->name, '# viejo');

    (new RemoveExpiredDocs)->handle();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->hasHeader('Authorization', 'Bearer sk-ccm'));
});

it('removes an expired file whose club has no store yet by deleting only the account file and the row', function (): void {
    // Sin Stores::fake(): así la petición DELETE sale por HTTP y se puede inspeccionar.
    Http::fake(['*/files/file-abc' => Http::response(['deleted' => true])]);
    $file = File::factory()->completed()->expired()->create(['assistant_media_id' => 'file-abc']);

    (new RemoveExpiredDocs)->handle();

    expect(File::query()->whereKey($file->id)->exists())->toBeFalse()
        ->and(VectorStore::query()->count())->toBe(0);
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/files/file-abc'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/vector_stores'));
});
