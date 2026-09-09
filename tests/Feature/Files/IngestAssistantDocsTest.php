<?php

declare(strict_types=1);

use App\Models\File;
use App\Jobs\RemoveExpiredDocs;
use App\Jobs\UploadAssistantDoc;
use App\Jobs\IngestAssistantDocs;
use Illuminate\Support\Facades\Bus;

it('dispatches one upload job per pending file as a batch', function () {
    Bus::fake();
    $pending = File::factory()->count(2)->create();
    File::factory()->completed()->create();
    File::factory()->create(['expired_at' => now()]);

    (new IngestAssistantDocs)->handle();

    Bus::assertBatched(function ($batch) use ($pending) {
        $ids = collect($batch->jobs)->map(fn (UploadAssistantDoc $job) => $job->fileId)->sort()->values()->all();

        return $batch->jobs->count() === 2
            && $ids === $pending->pluck('id')->sort()->values()->all()
            && $batch->name === 'assistant-docs-upload';
    });
    Bus::assertNotDispatched(RemoveExpiredDocs::class);
});

it('removes expired docs and releases the sync lock right away when nothing is pending', function () {
    Bus::fake();
    File::factory()->completed()->create();
    $lock = Cache::lock('syncing-assistant-files', 60);
    $lock->get();

    (new IngestAssistantDocs)->handle();

    Bus::assertDispatched(RemoveExpiredDocs::class);
    Bus::assertNothingBatched();
    expect(Cache::lock('syncing-assistant-files', 60)->get())->toBeTrue();
});
