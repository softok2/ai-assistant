<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\ClubName;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * El store del club solo lo ve la clave de SU proyecto de OpenAI: aquí se
 * comprueba sobre HTTP real (sin `Stores::fake()`) que la subida sale firmada
 * con la clave del club y nunca con la de por defecto.
 */
beforeEach(function () {
    Storage::fake();

    config([
        'services.openai.vector_stores' => ['ccm' => 'vs_ccm', 'vallealto' => 'vs_va'],
        'ai.providers.openai.key' => 'sk-default',
        'ai.providers.openai_vallealto.key' => 'sk-va',
    ]);

    Http::preventStrayRequests();
});

it('uploads with the key of the club project, never with the default one', function () {
    Http::fake([
        '*/vector_stores/vs_va/files' => Http::response(['id' => 'doc-new']),
        '*/vector_stores/vs_va' => Http::response([
            'id' => 'vs_va',
            'name' => 'vallealto',
            'status' => 'completed',
            'file_counts' => ['completed' => 0, 'in_progress' => 0, 'failed' => 0],
        ]),
        '*/files' => Http::response(['id' => 'file-new']),
    ]);

    $file = File::factory()->forClub(ClubName::VALLEALTO)->fromManifest('golf-live.md')->create();
    Storage::put('docs/'.$file->name, '# golf');

    $file->upload();

    expect($file->fresh()->status->value)->toBe('completed')
        ->and($file->fresh()->assistant_media_id)->toBe('file-new');

    Http::assertSentCount(3);

    Http::assertNotSent(fn ($request) => ! $request->hasHeader('Authorization', 'Bearer sk-va'));
});
