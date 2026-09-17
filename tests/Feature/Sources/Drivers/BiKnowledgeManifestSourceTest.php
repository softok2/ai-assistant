<?php

declare(strict_types=1);

use App\Enums\SourceOrigin;
use Illuminate\Support\Facades\Http;
use App\Ai\Sources\Drivers\BiKnowledgeManifestSource;

beforeEach(fn () => Http::preventStrayRequests());

function manifestSource(): BiKnowledgeManifestSource
{
    return new BiKnowledgeManifestSource(baseUrl: 'https://va.test/bi/knowledge', username: 'bi', password: 'secret');
}

function fakeManifest(): void
{
    Http::fake([
        'https://va.test/bi/knowledge/manifest.json' => Http::response([
            'generated_at' => '2026-09-16T03:15:00-06:00',
            'documents' => [
                ['name' => 'golf-annual.md', 'group' => 'golf', 'title' => 'Golf anual', 'bytes' => 4500, 'checksum' => 'aaa', 'generated_at' => '2026-09-16T03:15:00-06:00'],
                ['name' => 'golf-live.md', 'group' => 'golf', 'title' => 'Golf vivo', 'bytes' => 8000, 'checksum' => 'bbb', 'generated_at' => '2026-09-16T03:15:00-06:00'],
            ],
        ]),
        'https://va.test/bi/knowledge/golf-live.md' => Http::response('# golf vivo'),
    ]);
}

it('describes every manifest document without downloading it', function () {
    fakeManifest();

    $documents = collect(manifestSource()->documents());

    expect($documents)->toHaveCount(2)
        ->and($documents[1]->name)->toBe('golf-live.md')
        ->and($documents[1]->group)->toBe('golf')
        ->and($documents[1]->checksum)->toBe('bbb')
        ->and($documents[1]->origin)->toBe(SourceOrigin::BiKnowledge);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/manifest.json') && $request->hasHeader('Authorization', 'Basic '.base64_encode('bi:secret')));
});

it('downloads the body only when asked', function () {
    fakeManifest();

    $live = collect(manifestSource()->documents())[1];

    expect($live->content())->toBe('# golf vivo');
    Http::assertSentCount(2);
});

it('yields nothing and logs when the manifest is down', function () {
    Http::fake(['https://va.test/bi/knowledge/manifest.json' => Http::response('', 503)]);

    expect(collect(manifestSource()->documents()))->toHaveCount(0);
});

/**
 * `name` termina en un `Storage::put('docs/{club}/{name}')`: sin filtrar,
 * `../x.md` o una ruta absoluta apuntarían fuera de la carpeta del club.
 */
it('skips a manifest entry whose name tries to escape the club folder', function () {
    Http::fake([
        'https://va.test/bi/knowledge/manifest.json' => Http::response([
            'documents' => [
                ['name' => '../x.md', 'group' => 'golf', 'checksum' => 'aaa'],
                ['name' => '/etc/passwd.md', 'group' => 'golf', 'checksum' => 'bbb'],
                ['name' => 'sub/dir.md', 'group' => 'golf', 'checksum' => 'ccc'],
                ['name' => 'golf-live.md', 'group' => 'golf', 'checksum' => 'ddd'],
            ],
        ]),
    ]);

    $documents = collect(manifestSource()->documents());

    expect($documents)->toHaveCount(1)
        ->and($documents[0]->name)->toBe('golf-live.md');
});
