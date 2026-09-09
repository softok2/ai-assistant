<?php

declare(strict_types=1);

use App\Models\File;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use App\Enums\MediaStatus;
use App\Jobs\UploadAssistantDoc;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Stores::fake();
    Files::fake();
    Bus::fake();
    config(['services.openai.vector_store_id' => 'vs_test']);
});

it('drops the OpenAI copy and queues a fresh upload', function () {
    $file = File::factory()->completed()->create();
    Storage::put('docs/'.$file->name, '# reporte');

    $this->actingAs(adminUser())
        ->post(route('sources.files.reindex', $file))
        ->assertRedirect()
        ->assertSessionHas('success');

    $file->refresh();

    expect($file->status)->toBe(MediaStatus::PENDING)
        ->and($file->assistant_media_id)->toBeNull()
        ->and($file->synced_at)->toBeNull()
        ->and($file->expired_at)->toBeNull();

    Bus::assertDispatched(UploadAssistantDoc::class, fn (UploadAssistantDoc $job) => $job->fileId === $file->id);
});

it('brings an expired document back into the queue', function () {
    $file = File::factory()->completed()->expired()->create();
    Storage::put('docs/'.$file->name, '# reporte viejo');

    $this->actingAs(adminUser())
        ->post(route('sources.files.reindex', $file))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($file->fresh()->expired_at)->toBeNull();
});

it('refuses to reindex a document that is no longer on disk', function () {
    $file = File::factory()->completed()->create();

    $this->actingAs(adminUser())
        ->post(route('sources.files.reindex', $file))
        ->assertRedirect()
        ->assertSessionHas('error');

    $fresh = $file->fresh();

    expect($fresh->status)->toBe(MediaStatus::COMPLETED)
        ->and($fresh->assistant_media_id)->toBe($file->assistant_media_id);

    Bus::assertNotDispatched(UploadAssistantDoc::class);
});

it('keeps the row untouched when OpenAI refuses to drop the old copy', function () {
    Stores::fake(fn () => throw new RuntimeException('OpenAI is down'));
    $file = File::factory()->completed()->create();
    Storage::put('docs/'.$file->name, '# reporte');

    $this->actingAs(adminUser())
        ->post(route('sources.files.reindex', $file))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($file->fresh()->status)->toBe(MediaStatus::COMPLETED);
    Bus::assertNotDispatched(UploadAssistantDoc::class);
});

/**
 * Una fila que se quedó en "Procesando" porque el worker murió tiene que
 * poder reindexarse; si no, no hay forma de sacarla de ese estado.
 */
it('reindexes a row stuck in progress', function () {
    $file = File::factory()->create(['status' => MediaStatus::IN_PROGRESS]);
    Storage::put('docs/'.$file->name, '# reporte');

    $this->actingAs(adminUser())
        ->post(route('sources.files.reindex', $file))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($file->fresh()->status)->toBe(MediaStatus::PENDING);
    Bus::assertDispatched(UploadAssistantDoc::class);
});
