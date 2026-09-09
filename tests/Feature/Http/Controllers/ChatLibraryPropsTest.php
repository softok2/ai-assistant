<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\File;
use App\Models\User;
use App\Ai\Agents\ChatStarterSuggester;
use App\Http\Middleware\HandleInertiaRequests;

function inertiaVersion(): string
{
    return (new HandleInertiaRequests)->version(request());
}

beforeEach(function (): void {
    $this->user = User::factory()->create(['club_name' => 'ccm']);
    $this->actingAs($this->user);
    ChatStarterSuggester::fake([['starters' => [
        ['area' => 'Golf', 'question' => 'Uno'],
        ['area' => 'Tenis', 'question' => 'Dos'],
        ['area' => 'Pádel', 'question' => 'Tres'],
        ['area' => 'Restaurantes', 'question' => 'Cuatro'],
    ]]]);
});

it('shares the freshest library sync date with the start screen', function (): void {
    File::create(['name' => 'golf-a.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()->subDays(3)]);
    File::create(['name' => 'golf-b.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()->subDay()]);

    $this->get(route('chats.index'))
        ->assertInertia(fn ($page) => $page
            ->where('library.count', 2)
            ->has('dataFreshness'));
});

it('defers the starters so the start screen paints without waiting for the model', function (): void {
    File::create(['name' => 'golf-a.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()]);

    $first = $this->get(route('chats.index'), ['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])->json();

    expect($first['props'])->not->toHaveKey('starters')
        ->and(collect($first['deferredProps'])->flatten()->all())->toContain('starters');

    $deferred = $this->get(route('chats.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaVersion(),
        'X-Inertia-Partial-Data' => 'starters',
        'X-Inertia-Partial-Component' => 'Chat/Index',
    ])->json('props.starters');

    expect($deferred)->toHaveCount(4)
        ->and($deferred[0]['question'])->toBe('Uno');
});

it('does not recompute the starters when the sidebar loads another page of history', function (): void {
    File::create(['name' => 'golf-a.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()]);
    Chat::factory()->for($this->user)->count(30)->create();

    $page = $this->get(route('chats.index', ['page' => 2]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaVersion(),
        'X-Inertia-Partial-Data' => 'chatHistory',
        'X-Inertia-Partial-Component' => 'Chat/Index',
    ])->json('props');

    expect($page)->not->toHaveKey('starters')
        ->and($page)->not->toHaveKey('library')
        ->and($page)->toHaveKey('chatHistory');
});

it('reports no freshness when nothing is indexed', function (): void {
    $this->get(route('chats.index'))
        ->assertInertia(fn ($page) => $page
            ->where('dataFreshness', null)
            ->where('library.count', 0));
});

it('shares the freshness with the chat screen too', function (): void {
    File::create(['name' => 'golf-a.md', 'group' => 'golf', 'status' => 'completed', 'synced_at' => now()->subDay()]);
    $chat = Chat::factory()->for($this->user)->create();

    $this->get(route('chats.show', $chat))
        ->assertInertia(fn ($page) => $page->has('dataFreshness')->where('library.count', 1));
});
