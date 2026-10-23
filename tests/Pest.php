<?php

declare(strict_types=1);

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Enums\ClubName;
use App\Ai\ClubAiProvider;
use App\Models\VectorStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Usuario con el rol de administrador, el único que puede gestionar la
 * biblioteca de documentos del asistente.
 */
function adminUser(): User
{
    $user = User::factory()->create();

    $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));

    return $user;
}

/**
 * Fila de vector store para el club en el entorno de la prueba. Llamarla después
 * de cualquier config de `ai.providers.openai_*.key`: el proveedor forma parte de
 * la clave de la fila.
 */
function clubVectorStore(ClubName $club = ClubName::CCM, string $storeId = 'vs_test'): VectorStore
{
    return VectorStore::query()->create([
        'club' => $club->value,
        'environment' => app()->environment(),
        'provider' => resolve(ClubAiProvider::class)->nameFor($club) ?? 'openai',
        'store_id' => $storeId,
        'name' => "{$club->value}-assistant-".app()->environment(),
    ]);
}
