<?php

declare(strict_types=1);

use App\Enums\SourceOrigin;
use App\Ai\Sources\RemoteDocument;
use Illuminate\Support\Facades\Http;
use App\Ai\Sources\Drivers\PentahoMdsSource;

beforeEach(fn () => Http::preventStrayRequests());

function pentahoSource(): PentahoMdsSource
{
    return new PentahoMdsSource(
        baseUrl: 'https://mds.test/api/',
        username: 'u',
        password: 'p',
        files: ['golf-output', 'tennis-output'],
        project: 'ccm',
    );
}

it('yields one document per report with its group, a timestamped name and a computed checksum', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('# golf'),
        'https://mds.test/api/ccm/tennis-output.md' => Http::response('# tenis'),
    ]);

    $documents = collect(pentahoSource()->documents());

    expect($documents)->toHaveCount(2)
        ->and($documents[0])->toBeInstanceOf(RemoteDocument::class)
        ->and($documents[0]->group)->toBe('golf')
        ->and($documents[0]->name)->toMatch('/^golf-output-\d+\.md$/')
        ->and($documents[0]->origin)->toBe(SourceOrigin::Pentaho)
        ->and($documents[0]->checksum)->toBe(hash('sha256', '# golf'))
        ->and($documents[0]->content())->toBe('# golf');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('u:p')));
});

it('skips a report that fails to download and keeps the others', function () {
    Http::fake([
        'https://mds.test/api/ccm/golf-output.md' => Http::response('', 500),
        'https://mds.test/api/ccm/tennis-output.md' => Http::response('# tenis'),
    ]);

    $documents = collect(pentahoSource()->documents());

    expect($documents)->toHaveCount(1)
        ->and($documents[0]->group)->toBe('tennis');
});
