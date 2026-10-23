<?php

declare(strict_types=1);

use Laravel\Ai\Stores;
use App\Enums\ClubName;
use App\Models\VectorStore;
use App\Ai\Files\ClubVectorStore;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\StoreCreated;
use Laravel\Ai\Events\StoreDeleted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    // Antes de crear se busca en el proyecto un store con el nombre del entorno;
    // por defecto no hay ninguno. `Stores::fake()` no cubre estas llamadas crudas.
    $this->projectStores = [];
    Http::fake(['*/vector_stores?*' => fn () => Http::response(['data' => $this->projectStores, 'has_more' => false])]);
});

it('creates the store on first use and remembers it for the club, environment and provider', function (): void {
    Stores::fake();

    $id = resolve(ClubVectorStore::class)->idFor(ClubName::CCM);

    Stores::assertCreated(fn (string $name, ?string $description): bool => $name === 'ccm-assistant-testing'
        && $description === 'Fuentes del asistente · club=ccm environment=testing provider=openai');
    expect($id)->toBe(Stores::fakeId('ccm-assistant-testing'))
        ->and(VectorStore::query()->where(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai'])->value('store_id'))->toBe($id);
});

it('reuses the stored id without creating anything', function (): void {
    Stores::fake();
    clubVectorStore(ClubName::CCM, 'vs_known');

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toBe('vs_known');
    Stores::assertNothingCreated();
});

it('ignores a row from another environment', function (): void {
    Stores::fake();
    VectorStore::factory()->create(['club' => 'ccm', 'environment' => 'production', 'store_id' => 'vs_prod']);

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->not->toBe('vs_prod');
    Stores::assertCreated('ccm-assistant-testing');
});

it('creates a new store when the club moves to its own OpenAI project', function (): void {
    Stores::fake();
    VectorStore::factory()->create(['club' => 'ccm', 'provider' => 'openai', 'store_id' => 'vs_shared']);
    config(['ai.providers.openai_ccm.key' => 'sk-ccm']);

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->not->toBe('vs_shared')
        ->and(VectorStore::query()->where(['club' => 'ccm', 'provider' => 'openai_ccm'])->exists())->toBeTrue();
});

it('only reads when asked for an existing store', function (): void {
    Stores::fake();

    expect(resolve(ClubVectorStore::class)->existingIdFor(ClubName::VALLEALTO))->toBeNull()
        ->and(resolve(ClubVectorStore::class)->existingStoreFor(ClubName::VALLEALTO))->toBeNull();
    Stores::assertNothingCreated();

    clubVectorStore(ClubName::VALLEALTO, 'vs_va');

    expect(resolve(ClubVectorStore::class)->existingIdFor(ClubName::VALLEALTO))->toBe('vs_va')
        ->and(resolve(ClubVectorStore::class)->existingStoreFor(ClubName::VALLEALTO)?->id)->toBe('vs_va');
});

it('leaves no row behind when OpenAI refuses to create the store', function (): void {
    // Sin Stores::fake(): la guardia global convierte la petición real en excepción.
    config(['ai.providers.openai.key' => 'sk-test']);

    expect(fn () => resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toThrow(Exception::class)
        ->and(VectorStore::query()->count())->toBe(0);
});

it('keeps the row another worker inserted meanwhile and drops the extra store', function (): void {
    Stores::fake();
    // Simula la carrera: la fila aparece después de que este proceso decidió crear.
    Event::listen(StoreCreated::class, fn () => VectorStore::factory()->create(['club' => 'ccm', 'store_id' => 'vs_winner']));

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toBe('vs_winner')
        ->and(VectorStore::query()->where('club', 'ccm')->count())->toBe(1);
    Stores::assertDeleted(Stores::fakeId('ccm-assistant-testing'));
});

it('logs the extra store it could not delete after losing the race', function (): void {
    Stores::fake();
    Log::spy();
    Event::listen(StoreCreated::class, fn () => VectorStore::factory()->create(['club' => 'ccm', 'store_id' => 'vs_winner']));
    // El fake no sabe fallar al borrar; un listener que revienta hace fallar `Stores::delete`.
    Event::listen(StoreDeleted::class, fn () => throw new RuntimeException('OpenAI is down'));

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toBe('vs_winner');

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, Stores::fakeId('ccm-assistant-testing'))
        && $context === [
            'club' => 'ccm',
            'environment' => 'testing',
            'provider' => 'openai',
            'store_id' => Stores::fakeId('ccm-assistant-testing'),
            'error' => 'OpenAI is down',
        ]);
});

it('adopts an existing store with the environment name instead of creating one', function (): void {
    Stores::fake();
    $this->projectStores = [
        ['id' => 'vs_other', 'name' => 'ccm-assistant-stage', 'metadata' => ['description' => 'Fuentes del asistente · club=ccm environment=stage provider=openai']],
        ['id' => 'vs_existing', 'name' => 'ccm-assistant-testing', 'metadata' => ['description' => 'Fuentes del asistente · club=ccm environment=testing provider=openai']],
    ];

    expect(resolve(ClubVectorStore::class)->idFor(ClubName::CCM))->toBe('vs_existing')
        ->and(VectorStore::query()->where(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai'])->value('store_id'))->toBe('vs_existing');
    Stores::assertNothingCreated();
});

/**
 * El store viejo compartido se llama `ccm-assistant-local` pero no lo creó esta
 * versión: sin la descripción exacta no se adopta y se crea uno nuevo.
 */
it('does not adopt a store that matches by name but not by description', function (array $metadata): void {
    Stores::fake();
    // El SDK guarda la descripción en `metadata.description`; OpenAI no tiene campo `description`.
    $this->projectStores = [['id' => 'vs_legacy', 'name' => 'ccm-assistant-testing'] + $metadata];

    $id = resolve(ClubVectorStore::class)->idFor(ClubName::CCM);

    Stores::assertCreated('ccm-assistant-testing');
    expect($id)->toBe(Stores::fakeId('ccm-assistant-testing'))
        ->and(VectorStore::query()->where(['club' => 'ccm', 'environment' => 'testing'])->value('store_id'))->toBe($id);
})->with([
    'no metadata' => [[]],
    'empty metadata' => [['metadata' => []]],
    'another description' => [['metadata' => ['description' => 'otra cosa']]],
]);
