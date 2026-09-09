<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use App\Enums\MediaStatus;
use App\Jobs\UploadAssistantDoc;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldBeUnique;

beforeEach(function () {
    Storage::fake();
    Stores::fake();
    config(['services.openai.vector_store_id' => 'vs_test']);
});

function pendingDoc(array $attributes = []): File
{
    $file = File::factory()->create($attributes);
    Storage::put('docs/'.$file->name, '# reporte');

    return $file;
}

it('uploads a pending file and marks it completed', function () {
    $file = pendingDoc();

    (new UploadAssistantDoc($file->id))->handle();

    $file->refresh();

    expect($file->status)->toBe(MediaStatus::COMPLETED)
        ->and($file->assistant_media_id)->not->toBeNull()
        ->and($file->synced_at)->not->toBeNull();

    Files::assertStored(fn ($stored) => true);
});

/**
 * El duplicado en OpenAI: dos workers procesando el mismo pendiente (el job
 * viejo superaba retry_after y la cola lo reasignaba). Un archivo ya reclamado
 * por otro worker no se vuelve a subir.
 */
it('does not upload a file another worker already claimed', function () {
    $file = pendingDoc(['status' => MediaStatus::IN_PROGRESS]);

    (new UploadAssistantDoc($file->id))->handle();

    Files::assertNothingStored();
    expect($file->fresh()->status)->toBe(MediaStatus::IN_PROGRESS);
});

it('does not upload twice when the job runs twice for the same file', function () {
    $file = pendingDoc();

    (new UploadAssistantDoc($file->id))->handle();
    $firstMediaId = $file->fresh()->assistant_media_id;

    (new UploadAssistantDoc($file->id))->handle();

    expect($file->fresh()->assistant_media_id)->toBe($firstMediaId);
    Files::assertStored(fn ($stored) => true);
    expect(Files::isFaked())->toBeTrue();
});

it('skips files that were expired before being uploaded', function () {
    $file = pendingDoc(['expired_at' => now()]);

    (new UploadAssistantDoc($file->id))->handle();

    Files::assertNothingStored();
    expect($file->fresh()->status)->toBe(MediaStatus::PENDING);
});

it('expires the older docs of the same group only after a successful upload', function () {
    $older = File::factory()->completed()->create(['group' => 'golf', 'created_at' => now()->subDay()]);
    $olderFailed = File::factory()->failed()->create(['group' => 'golf', 'created_at' => now()->subDays(2)]);
    $otherGroup = File::factory()->completed()->create(['group' => 'tennis']);
    $new = pendingDoc(['group' => 'golf']);

    expect($older->fresh()->expired_at)->toBeNull();

    (new UploadAssistantDoc($new->id))->handle();

    expect($older->fresh()->expired_at)->not->toBeNull()
        ->and($olderFailed->fresh()->expired_at)->not->toBeNull()
        ->and($otherGroup->fresh()->expired_at)->toBeNull()
        ->and($new->fresh()->expired_at)->toBeNull();
});

it('marks the file as failed when the provider throws and keeps the older doc alive', function () {
    Stores::fake(fn () => throw new RuntimeException('OpenAI is down'));
    $older = File::factory()->completed()->create(['group' => 'golf', 'created_at' => now()->subDay()]);
    $new = pendingDoc(['group' => 'golf']);

    (new UploadAssistantDoc($new->id))->handle();

    expect($new->fresh()->status)->toBe(MediaStatus::FAILED)
        ->and($older->fresh()->expired_at)->toBeNull();
});

it('is unique per file and has a generous timeout', function () {
    $job = new UploadAssistantDoc(42);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('42')
        ->and($job->timeout)->toBeGreaterThanOrEqual(300)
        ->and($job->tries)->toBe(1);
});

/**
 * Dos pendientes del mismo grupo en el mismo lote (subida manual + sync, o un
 * sync que quedó a medias): el que termina primero no debe caducar al más
 * nuevo, o el grupo se queda sin documento y RemoveExpiredDocs borra ambos.
 */
it('never expires a newer sibling that is being uploaded at the same time', function () {
    $older = pendingDoc(['group' => 'golf']);
    $newer = pendingDoc(['group' => 'golf']);

    (new UploadAssistantDoc($older->id))->handle();
    (new UploadAssistantDoc($newer->id))->handle();

    expect($older->fresh()->expired_at)->not->toBeNull()
        ->and($newer->fresh()->expired_at)->toBeNull()
        ->and($newer->fresh()->status)->toBe(MediaStatus::COMPLETED);
});

it('does not expire a newer sibling even if the older one finishes last', function () {
    $older = pendingDoc(['group' => 'golf']);
    $newer = pendingDoc(['group' => 'golf']);

    (new UploadAssistantDoc($newer->id))->handle();
    (new UploadAssistantDoc($older->id))->handle();

    expect($newer->fresh()->expired_at)->toBeNull()
        ->and($older->fresh()->expired_at)->not->toBeNull();
});

/**
 * Si la cola reasigna el job antes de que termine, el lote lo cuenta como
 * hecho y el `finally` suelta el candado del sync antes de tiempo.
 */
it('gives the queue more time than the upload job takes', function () {
    expect(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan((new UploadAssistantDoc(1))->timeout);
});

/**
 * Un timeout o un `queue:restart` a media subida dejaba la fila en
 * "Procesando" para siempre, y ahí la UI no deja reindexar.
 */
it('marks a file as failed when the job dies mid upload', function () {
    $file = File::factory()->create(['status' => MediaStatus::IN_PROGRESS]);

    (new UploadAssistantDoc($file->id))->failed(new RuntimeException('worker killed'));

    expect($file->fresh()->status)->toBe(MediaStatus::FAILED);
});

it('leaves an already completed file alone when a stale job fails', function () {
    $file = File::factory()->completed()->create();

    (new UploadAssistantDoc($file->id))->failed(null);

    expect($file->fresh()->status)->toBe(MediaStatus::COMPLETED);
});

it('never treats an expired row as pending', function () {
    File::factory()->create();
    File::factory()->expired()->create();

    expect(File::pending()->count())->toBe(1);
});
