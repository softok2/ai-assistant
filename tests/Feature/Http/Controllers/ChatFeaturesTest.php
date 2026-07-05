<?php

declare(strict_types=1);

use App\Ai\Agents\ChatFollowUpSuggester;
use App\Ai\Agents\ChatTitleGenerator;
use App\Ai\Agents\ClubAssistant;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->chat = Chat::factory()->for($this->user)->create(['title' => 'Primer mensaje...', 'visibility' => 'private']);
});

it('generates a title after the first exchange', function (): void {
    ClubAssistant::fake(['Claro, aquí está el resumen.']);
    ChatTitleGenerator::fake(['Resumen ejecutivo del día']);

    $this->post(route('chat.stream', $this->chat), ['message' => 'Dame un resumen'])
        ->assertOk()
        ->streamedContent();

    expect($this->chat->fresh()->title)->toBe('Resumen ejecutivo del día');
});

it('does not retitle on later exchanges', function (): void {
    ClubAssistant::fake(['Segunda respuesta']);
    ChatTitleGenerator::fake(['No debería usarse']);

    $this->chat->messages()->create(['role' => 'user', 'parts' => ['text' => 'a'], 'attachments' => []]);
    $this->chat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'b'], 'attachments' => []]);

    $this->post(route('chat.stream', $this->chat), ['message' => 'otra'])
        ->assertOk()
        ->streamedContent();

    expect($this->chat->fresh()->title)->toBe('Primer mensaje...');
});

it('edits a user message and drops everything after it', function (): void {
    ClubAssistant::fake(['Respuesta a la edición']);

    $first = $this->chat->messages()->create(['role' => 'user', 'parts' => ['text' => 'Pregunta original'], 'attachments' => []]);
    sleep(0);
    $this->chat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'Respuesta vieja'], 'attachments' => [], 'created_at' => now()->addSecond()]);

    $this->post(route('chat.stream', $this->chat), [
        'message' => 'Pregunta corregida',
        'edit_message_id' => $first->id,
    ])->assertOk()->streamedContent();

    $messages = $this->chat->messages()->orderBy('created_at')->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->parts['text'])->toBe('Pregunta corregida');
    expect($messages[1]->parts['text'])->toBe('Respuesta a la edición');
});

it('allows guests to view public chats read-only', function (): void {
    $this->chat->update(['visibility' => 'public']);
    $this->post(route('logout'));

    $this->get(route('chats.show', $this->chat))
        ->assertOk();
});

it('denies guests on private chats', function (): void {
    $this->post(route('logout'));

    $this->get(route('chats.show', $this->chat))->assertForbidden();
});

it('returns follow-up suggestions for the owner', function (): void {
    ChatFollowUpSuggester::fake([
        ['suggestions' => ['¿Y los KPIs de tenis?', '¿Comparativa semanal?', '¿Detalle de no-shows?']],
    ]);

    $this->chat->messages()->create(['role' => 'user', 'parts' => ['text' => 'KPIs de golf'], 'attachments' => []]);
    $this->chat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'Aquí están...'], 'attachments' => []]);

    $response = $this->post(route('chat.suggestions', $this->chat));

    $response->assertOk();
    expect($response->json('suggestions'))->toHaveCount(3);
});

it('denies suggestions on chats of other users', function (): void {
    $otherChat = Chat::factory()->for(User::factory()->create())->create();

    $this->post(route('chat.suggestions', $otherChat))->assertForbidden();
});

it('lists library files', function (): void {
    App\Models\File::create(['name' => 'golf-reporte.md', 'group' => 'golf', 'status' => 'completed']);

    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Library')->has('files', 1));
});

it('denies updating or deleting chats of other users', function (): void {
    $otherChat = Chat::factory()->for(User::factory()->create())->create(['visibility' => 'private']);

    $this->patch(route('chats.update', $otherChat), ['visibility' => 'public'])->assertForbidden();
    $this->delete(route('chats.destroy', $otherChat))->assertForbidden();

    expect($otherChat->fresh())->not->toBeNull();
    expect($otherChat->fresh()->visibility)->toBe('private');
});

it('reads a message aloud via server-side TTS', function (): void {
    Laravel\Ai\Audio::fake();

    $message = $this->chat->messages()->create([
        'role' => 'assistant',
        'parts' => ['text' => '#### Análisis
Las reservas subieron 12%.
```chart
{"type":"bar","labels":["a"],"series":[{"data":[1]}]}
```'],
        'attachments' => [],
    ]);

    $response = $this->post(route('chat.speech', $message));

    $response->assertOk();
    expect($response->json('audio'))->not->toBeEmpty();
    expect($response->json('mime'))->not->toBeEmpty();
});

it('denies reading messages from chats of other users', function (): void {
    Laravel\Ai\Audio::fake();

    $otherChat = Chat::factory()->for(User::factory()->create())->create(['visibility' => 'private']);
    $message = $otherChat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'secreto'], 'attachments' => []]);

    $this->post(route('chat.speech', $message))->assertForbidden();
});
