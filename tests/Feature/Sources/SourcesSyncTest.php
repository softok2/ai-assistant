<?php

declare(strict_types=1);

use App\Jobs\SyncLock;
use App\Jobs\IngestAssistantDocs;
use App\Jobs\ImportDocsFromPentaho;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

it('starts the sync chain and keeps the lock while it runs', function () {
    Bus::fake();

    $this->actingAs(adminUser())
        ->post(route('sources.sync'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Bus::assertChained([ImportDocsFromPentaho::class, IngestAssistantDocs::class]);
    expect(Cache::lock(SyncLock::KEY, 60)->get())->toBeFalse();
});

it('warns instead of starting a second sync', function () {
    Bus::fake();
    Cache::lock(SyncLock::KEY, 60)->get();

    $this->actingAs(adminUser())
        ->post(route('sources.sync'))
        ->assertRedirect()
        ->assertSessionHas('warning', 'Ya hay una sincronización en curso');

    Bus::assertNotDispatched(ImportDocsFromPentaho::class);
});
