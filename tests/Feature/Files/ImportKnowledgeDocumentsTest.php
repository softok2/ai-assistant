<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use App\Enums\MediaStatus;
use App\Enums\SourceOrigin;
use Illuminate\Support\Facades\Http;
use App\Jobs\ImportKnowledgeDocuments;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Http::preventStrayRequests();
    config(['knowledge.sources' => [
        'ccm' => ['driver' => 'pentaho', 'base_url' => 'https://mds.test/api/', 'username' => 'u', 'password' => 'p', 'files' => ['golf-output']],
        'vallealto' => ['driver' => 'bi_knowledge', 'base_url' => 'https://va.test/bi/knowledge', 'username' => 'bi', 'password' => 's'],
    ]]);
});

function fakeSources(string $liveChecksum = 'live-1', string $golfBody = '# golf ccm'): void
{
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response($golfBody),
        'https://va.test/bi/knowledge/manifest.json' => Http::response(['documents' => [
            ['name' => 'golf-live.md', 'group' => 'golf', 'checksum' => $liveChecksum],
            ['name' => 'golf-annual.md', 'group' => 'golf', 'checksum' => 'annual-1'],
        ]]),
        'https://va.test/bi/knowledge/golf-live.md' => Http::response('# vivo'),
        'https://va.test/bi/knowledge/golf-annual.md' => Http::response('# anual'),
    ]);
}

it('creates one pending row per document of every club, under the club folder', function () {
    fakeSources();

    (new ImportKnowledgeDocuments)->handle();

    $live = File::where('project', 'vallealto')->where('name', 'vallealto/golf-live.md')->sole();
    $ccm = File::where('project', 'ccm')->sole();

    expect(File::pending()->count())->toBe(3)
        ->and(File::where('project', 'vallealto')->pluck('name')->sort()->values()->all())->toBe(['vallealto/golf-annual.md', 'vallealto/golf-live.md'])
        ->and($live->checksum)->toBe('live-1')
        ->and($live->origin)->toBe(SourceOrigin::BiKnowledge)
        ->and($live->status)->toBe(MediaStatus::PENDING)
        ->and($ccm->name)->toStartWith('ccm/golf-output-')
        ->and($ccm->checksum)->toBe(hash('sha256', '# golf ccm'));

    Storage::assertExists('docs/vallealto/golf-live.md');
});

it('does nothing for a manifest document whose checksum did not change', function () {
    fakeSources();
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create(['checksum' => 'live-1']);
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-annual.md')->completed()->create(['checksum' => 'annual-1']);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('project', 'vallealto')->count())->toBe(2);
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/golf-live.md'));
});

it('creates a new pending row when the manifest checksum changed and keeps the current one alive', function () {
    fakeSources(liveChecksum: 'live-2');
    $current = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->completed()->create(['checksum' => 'live-1']);
    File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-annual.md')->completed()->create(['checksum' => 'annual-1']);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('name', 'vallealto/golf-live.md')->count())->toBe(2)
        ->and(File::pending()->where('name', 'vallealto/golf-live.md')->sole()->checksum)->toBe('live-2')
        ->and($current->fresh()->expired_at)->toBeNull();
});

it('skips a pentaho report whose content did not change since the current one', function () {
    fakeSources();
    File::factory()->forClub(ClubName::CCM)->completed()->create(['group' => 'golf', 'name' => 'ccm/golf-output-100.md', 'checksum' => hash('sha256', '# golf ccm')]);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('project', 'ccm')->count())->toBe(1);
});

/**
 * Si `Storage::put` falla (disco lleno, permisos) no debe quedar una fila
 * "pendiente" que apunta a un archivo que nunca se guardó.
 */
it('creates no row when writing the downloaded document to disk fails', function () {
    fakeSources();
    Storage::shouldReceive('put')->andReturn(false);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::count())->toBe(0);
});

it('keeps importing the other club when one source is down', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('# golf ccm'),
        'https://va.test/bi/knowledge/manifest.json' => Http::response('', 503),
    ]);

    (new ImportKnowledgeDocuments)->handle();

    expect(File::where('project', 'ccm')->count())->toBe(1)
        ->and(File::where('project', 'vallealto')->count())->toBe(0);
});
