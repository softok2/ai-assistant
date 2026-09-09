<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\MediaStatus;
use App\Jobs\ImportDocsFromPentaho;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    config([
        'services.softok2mds.base_url' => 'https://mds.test/api/',
        'services.softok2mds.username' => 'u',
        'services.softok2mds.password' => 'p',
        'services.softok2mds.projects' => ['ccm'],
        'services.softok2mds.files' => ['golf-output', 'tennis-output'],
    ]);
    Http::preventStrayRequests();
});

it('creates one pending file per report and stores its content', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('# golf'),
        'https://mds.test/api/ccm/tennis-output.md' => Http::response('# tenis'),
    ]);

    (new ImportDocsFromPentaho)->handle();

    expect(File::count())->toBe(2)
        ->and(File::pending()->count())->toBe(2)
        ->and(File::where('group', 'golf')->first()->status)->toBe(MediaStatus::PENDING);
    Storage::assertExists('docs/'.File::where('group', 'golf')->value('name'));
});

/**
 * Importar no caduca el documento vigente: eso pasa solo cuando el nuevo
 * quedó indexado (File::upload → expireSiblings). Si la subida falla, el
 * store conserva el documento anterior del grupo.
 */
it('keeps the current doc of the group alive until the new one is indexed', function () {
    Http::fake(['https://mds.test/api/*' => Http::response('# nuevo')]);
    $current = File::factory()->completed()->create(['group' => 'golf']);

    (new ImportDocsFromPentaho)->handle();

    expect($current->fresh()->expired_at)->toBeNull();
});

it('logs and continues when one report fails to download', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('', 500),
        'https://mds.test/api/ccm/tennis-output.md' => Http::response('# tenis'),
    ]);

    (new ImportDocsFromPentaho)->handle();

    expect(File::where('group', 'tennis')->exists())->toBeTrue()
        ->and(File::where('group', 'golf')->exists())->toBeFalse();
});
