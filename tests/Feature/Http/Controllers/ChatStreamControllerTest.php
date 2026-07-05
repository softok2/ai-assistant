<?php

declare(strict_types=1);

use App\Ai\Agents\ClubAssistant;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ChatStreamController', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->chat = Chat::factory()->for($this->user)->create();
    });

    it('streams the agent response and persists both messages', function (): void {
        ClubAssistant::fake(['Hola, ¿en qué puedo ayudarte hoy?']);

        $response = $this->post(route('chat.stream', $this->chat), [
            'message' => 'Hola',
        ]);

        $response->assertOk();
        $response->assertStreamed();

        $content = $response->streamedContent();
        expect($content)->toContain('text_delta');
        expect($content)->toContain('[DONE]');

        expect($this->chat->messages()->where('role', 'user')->count())->toBe(1);

        $assistantMessage = $this->chat->messages()->where('role', 'assistant')->first();
        expect($assistantMessage)->not->toBeNull();
        expect($assistantMessage->parts['text'])->toBe('Hola, ¿en qué puedo ayudarte hoy?');
    });

    it('sends prior conversation history to the agent', function (): void {
        $fake = ClubAssistant::fake(['Segunda respuesta']);

        $this->chat->messages()->create([
            'role' => 'user',
            'parts' => ['text' => 'Primer mensaje'],
            'attachments' => '[]',
        ]);
        $this->chat->messages()->create([
            'role' => 'assistant',
            'parts' => ['text' => 'Primera respuesta'],
            'attachments' => '[]',
        ]);

        $this->post(route('chat.stream', $this->chat), [
            'message' => 'Segundo mensaje',
        ])->assertOk()->streamedContent();

        expect($this->chat->messages()->count())->toBe(4);
    });

    it('rejects streaming into a chat owned by another user', function (): void {
        ClubAssistant::fake(['no debería enviarse']);

        $otherChat = Chat::factory()->for(User::factory()->create())->create();

        $this->post(route('chat.stream', $otherChat), [
            'message' => 'Hola',
        ])->assertForbidden();

        expect($otherChat->messages()->count())->toBe(0);
    });

    it('validates the message payload', function (): void {
        $this->post(route('chat.stream', $this->chat), [
            'message' => str_repeat('a', 4001),
        ])->assertSessionHasErrors('message');

        $this->post(route('chat.stream', $this->chat), [
            'message' => 'Hola',
            'model' => 'modelo-inexistente',
        ])->assertSessionHasErrors('model');
    });
});

describe('regenerate', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->chat = Chat::factory()->for($this->user)->create();
    });

    it('replaces the last assistant response without duplicating the user message', function (): void {
        ClubAssistant::fake(['Respuesta regenerada']);

        $this->chat->messages()->create(['role' => 'user', 'parts' => ['text' => 'Pregunta'], 'attachments' => []]);
        $this->chat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'Respuesta original'], 'attachments' => []]);

        $this->post(route('chat.stream', $this->chat), ['regenerate' => true])
            ->assertOk()
            ->streamedContent();

        expect($this->chat->messages()->where('role', 'user')->count())->toBe(1);
        expect($this->chat->messages()->where('role', 'assistant')->count())->toBe(1);
        expect($this->chat->messages()->where('role', 'assistant')->first()->parts['text'])->toBe('Respuesta regenerada');
    });

    it('rejects regeneration on an empty chat', function (): void {
        ClubAssistant::fake(['nada']);

        $this->post(route('chat.stream', $this->chat), ['regenerate' => true])
            ->assertStatus(422);
    });
});
