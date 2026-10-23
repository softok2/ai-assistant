<?php

declare(strict_types=1);

use App\Enums\ClubName;
use Illuminate\Support\Facades\Http;
use App\Ai\Files\OpenAiStoreDirectory;

/**
 * El SDK crea el store con la descripción en `metadata.description` (OpenAI no
 * tiene un campo `description`), y la búsqueda para adoptarlo tiene que hablar
 * con el proyecto del club, con su propia clave.
 */
it('finds a store by name and metadata description with the club key', function (): void {
    config(['ai.providers.openai_ccm.key' => 'sk-ccm', 'ai.providers.openai_ccm.url' => 'https://api.openai.com/v1']);
    Http::fake(['https://api.openai.com/v1/vector_stores?*' => Http::response(['data' => [
        ['id' => 'vs_legacy', 'name' => 'ccm-assistant-local', 'metadata' => []],
        ['id' => 'vs_mine', 'name' => 'ccm-assistant-local', 'metadata' => ['description' => 'Fuentes del asistente · club=ccm environment=local provider=openai_ccm']],
    ], 'has_more' => false])]);

    $directory = resolve(OpenAiStoreDirectory::class);

    expect($directory->findId(ClubName::CCM, 'ccm-assistant-local', 'Fuentes del asistente · club=ccm environment=local provider=openai_ccm'))->toBe('vs_mine')
        ->and($directory->findId(ClubName::CCM, 'ccm-assistant-local', 'otra cosa'))->toBeNull();
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-ccm'));
    Http::assertNotSent(fn ($request): bool => ! $request->hasHeader('Authorization', 'Bearer sk-ccm'));
});
