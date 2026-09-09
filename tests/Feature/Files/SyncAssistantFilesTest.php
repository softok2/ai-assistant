<?php

declare(strict_types=1);

use App\Jobs\SyncLock;
use App\Jobs\IngestAssistantDocs;
use App\Jobs\ImportDocsFromPentaho;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

it('chains the import and the ingest and holds the lock while they run', function () {
    Bus::fake();

    $this->artisan('assistant-files:sync')->assertSuccessful();

    Bus::assertChained([ImportDocsFromPentaho::class, IngestAssistantDocs::class]);
    expect(Cache::lock('syncing-assistant-files', 60)->get())->toBeFalse();
});

it('refuses to start a second sync while one is running', function () {
    Bus::fake();
    Cache::lock('syncing-assistant-files', 60)->get();

    $this->artisan('assistant-files:sync')
        ->expectsOutputToContain('en curso')
        ->assertSuccessful();

    Bus::assertNotDispatched(ImportDocsFromPentaho::class);
});

it('holds the lock long enough for a slow ingestion', function () {
    Bus::fake();

    $this->artisan('assistant-files:sync');

    $this->travel(20)->minutes();
    expect(Cache::lock('syncing-assistant-files', 60)->get())->toBeFalse();
});

/**
 * El sondeo del panel toma el candado un instante: con el TTL largo, una
 * petición que muriera antes de soltarlo bloqueaba el sync media hora.
 */
it('does not block the sync when it only probes the lock', function () {
    expect(SyncLock::isHeld())->toBeFalse()
        ->and(SyncLock::acquire())->not->toBeNull();
});

it('reports the lock as held while a sync owns it', function () {
    SyncLock::acquire();

    expect(SyncLock::isHeld())->toBeTrue();
});
