<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('fijar chats', function (): void {
    it('pins a chat and unpins it again', function (): void {
        $chat = Chat::factory()->for($this->user)->create();

        $this->from(route('chats.index'))
            ->patch(route('chats.update', $chat), ['pinned' => true])
            ->assertRedirect(route('chats.index'));

        expect($chat->refresh()->pinned_at)->not->toBeNull();

        $this->patch(route('chats.update', $chat), ['pinned' => false]);

        expect($chat->refresh()->pinned_at)->toBeNull();
    });

    it('sends the user back to the chat they were reading, not to the edited one', function (): void {
        $reading = Chat::factory()->for($this->user)->create();
        $edited = Chat::factory()->for($this->user)->create();

        $this->from(route('chats.show', $reading))
            ->patch(route('chats.update', $edited), ['pinned' => true])
            ->assertRedirect(route('chats.show', $reading));

        expect($edited->refresh()->pinned_at)->not->toBeNull();
    });

    it('rejects pinning a chat owned by someone else', function (): void {
        $chat = Chat::factory()->for(User::factory()->create())->create();

        $this->patch(route('chats.update', $chat), ['pinned' => true])->assertForbidden();

        expect($chat->refresh()->pinned_at)->toBeNull();
    });

    it('lists pinned chats first', function (): void {
        $recent = Chat::factory()->for($this->user)->create(['updated_at' => now()]);
        $pinned = Chat::factory()->for($this->user)->create([
            'updated_at' => now()->subWeek(),
            'pinned_at' => now(),
        ]);

        $this->get(route('chats.index'))
            ->assertInertia(fn ($page) => $page
                ->where('chatHistory.data.0.id', $pinned->id)
                ->where('chatHistory.data.1.id', $recent->id));
    });
});

describe('renombrar chats', function (): void {
    it('renames a chat', function (): void {
        $chat = Chat::factory()->for($this->user)->create(['title' => 'Sin título']);

        $this->patch(route('chats.update', $chat), ['title' => 'Reservas de golf']);

        expect($chat->refresh()->title)->toBe('Reservas de golf');
    });

    it('rejects titles longer than 120 characters', function (): void {
        $chat = Chat::factory()->for($this->user)->create();

        $this->patch(route('chats.update', $chat), ['title' => str_repeat('a', 121)])
            ->assertSessionHasErrors('title');
    });

    it('never blanks a title', function (): void {
        $chat = Chat::factory()->for($this->user)->create(['title' => 'Sin título']);

        // Laravel recorta la cadena y la vuelve null, así que la petición pasa
        // pero no cambia nada; `min:1` cubre el resto de los orígenes.
        $this->from(route('chats.index'))
            ->patch(route('chats.update', $chat), ['title' => '   ']);

        expect($chat->refresh()->title)->toBe('Sin título');

        $this->from(route('chats.index'))
            ->patch(route('chats.update', $chat), ['title' => str_repeat(' ', 5).'Renombrado'])
            ->assertSessionHasNoErrors();

        expect($chat->refresh()->title)->toBe('Renombrado');
    });

    it('rejects a pinned value that is not a boolean', function (): void {
        $chat = Chat::factory()->for($this->user)->create();

        $this->patch(route('chats.update', $chat), ['pinned' => 'quizá'])
            ->assertSessionHasErrors('pinned');
    });
});

describe('buscar chats', function (): void {
    it('returns only the chats of the signed-in user that match the query', function (): void {
        $match = Chat::factory()->for($this->user)->create(['title' => 'Ocupación de restaurantes']);
        Chat::factory()->for($this->user)->create(['title' => 'Torneo de golf']);
        Chat::factory()->for(User::factory()->create())->create(['title' => 'Ocupación ajena']);

        $response = $this->getJson(route('chats.search', ['q' => 'ocupación']));

        $response->assertOk();
        expect($response->json())->toHaveCount(1)
            ->and($response->json('0.id'))->toBe($match->id)
            ->and($response->json('0.title'))->toBe('Ocupación de restaurantes')
            ->and($response->json('0'))->toHaveKey('updated_at');
    });

    it('returns the most recent chats when the query is empty', function (): void {
        Chat::factory()->for($this->user)->count(3)->create();

        expect($this->getJson(route('chats.search'))->assertOk()->json())->toHaveCount(3);
    });

    it('caps the results at twenty chats', function (): void {
        Chat::factory()->for($this->user)->count(25)->create(['title' => 'Reporte semanal']);

        expect($this->getJson(route('chats.search', ['q' => 'reporte']))->json())->toHaveCount(20);
    });

    it('requires authentication', function (): void {
        $this->post(route('logout'));

        $this->get(route('chats.search'))->assertRedirect();
    });
});

describe('paginación del historial', function (): void {
    it('sends the next page as a deep-merged partial reload', function (): void {
        Chat::factory()->for($this->user)->count(30)->create();

        $response = $this->get(route('chats.index', ['page' => 2]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()),
            'X-Inertia-Partial-Data' => 'chatHistory',
            'X-Inertia-Partial-Component' => 'Chat/Index',
        ]);

        $response->assertOk();

        $page = $response->json();

        expect($page['props']['chatHistory']['current_page'])->toBe(2)
            ->and($page['props']['chatHistory']['data'])->toHaveCount(5)
            ->and($page['deepMergeProps'])->toContain('chatHistory');
    });
});
