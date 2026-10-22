<?php

declare(strict_types=1);

use App\Jobs\SyncLock;
use App\Jobs\IngestAssistantDocs;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ImportKnowledgeDocuments;

it('chains the import and the ingest and holds the lock while they run', function (): void {
    Bus::fake();

    $this->artisan('assistant-files:sync')->assertSuccessful();

    Bus::assertChained([ImportKnowledgeDocuments::class, IngestAssistantDocs::class]);
    expect(Cache::lock('syncing-assistant-files', 60)->get())->toBeFalse();
});

it('refuses to start a second sync while one is running', function (): void {
    Bus::fake();
    Cache::lock('syncing-assistant-files', 60)->get();

    $this->artisan('assistant-files:sync')
        ->expectsOutputToContain('en curso')
        ->assertSuccessful();

    Bus::assertNotDispatched(ImportKnowledgeDocuments::class);
});

it('holds the lock long enough for a slow ingestion', function (): void {
    Bus::fake();

    $this->artisan('assistant-files:sync');

    $this->travel(20)->minutes();
    expect(Cache::lock('syncing-assistant-files', 60)->get())->toBeFalse();
});

/**
 * El sondeo del panel toma el candado un instante: con el TTL largo, una
 * petición que muriera antes de soltarlo bloqueaba el sync media hora.
 */
it('does not block the sync when it only probes the lock', function (): void {
    expect(SyncLock::isHeld())->toBeFalse()
        ->and(SyncLock::acquire())->not->toBeNull();
});

it('reports the lock as held while a sync owns it', function (): void {
    SyncLock::acquire();

    expect(SyncLock::isHeld())->toBeTrue();
});
