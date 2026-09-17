<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\User;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;
use App\Ai\Agents\ClubAssistant;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\AudioPrompt;
use App\Ai\Agents\ChatTitleGenerator;
use App\Ai\Agents\ChatFollowUpSuggester;
use Laravel\Ai\Prompts\TranscriptionPrompt;
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

it('prompts the club provider for the follow-up suggester', function (): void {
    config(['ai.providers.openai_vallealto.key' => 'sk-va']);

    $user = User::factory()->create(['club_name' => 'vallealto']);
    $this->actingAs($user);
    $chat = Chat::factory()->for($user)->create();

    ChatFollowUpSuggester::fake([
        ['suggestions' => ['¿Y los KPIs de tenis?', '¿Comparativa semanal?', '¿Detalle de no-shows?']],
    ]);

    $chat->messages()->create(['role' => 'user', 'parts' => ['text' => 'KPIs de golf'], 'attachments' => []]);
    $chat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'Aquí están...'], 'attachments' => []]);

    $this->post(route('chat.suggestions', $chat))->assertOk();

    ChatFollowUpSuggester::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai_vallealto');
});

it('prompts the club provider for the title generator', function (): void {
    config(['ai.providers.openai_vallealto.key' => 'sk-va']);

    $user = User::factory()->create(['club_name' => 'vallealto']);
    $this->actingAs($user);
    $chat = Chat::factory()->for($user)->create(['title' => 'Primer mensaje...', 'visibility' => 'private']);

    ClubAssistant::fake(['Claro, aquí está el resumen.']);
    ChatTitleGenerator::fake(['Resumen ejecutivo del día']);

    $this->post(route('chat.stream', $chat), ['message' => 'Dame un resumen'])
        ->assertOk()
        ->streamedContent();

    ChatTitleGenerator::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai_vallealto');
});

it('opens the library of the user without any attachment yet', function (): void {
    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Library')->where('attachments', []));
});

it('denies updating or deleting chats of other users', function (): void {
    $otherChat = Chat::factory()->for(User::factory()->create())->create(['visibility' => 'private']);

    $this->patch(route('chats.update', $otherChat), ['visibility' => 'public'])->assertForbidden();
    $this->delete(route('chats.destroy', $otherChat))->assertForbidden();

    expect($otherChat->fresh())->not->toBeNull();
    expect($otherChat->fresh()->visibility)->toBe('private');
});

it('reads a message aloud via server-side TTS', function (): void {
    Audio::fake();

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
    Audio::fake();

    $otherChat = Chat::factory()->for(User::factory()->create())->create(['visibility' => 'private']);
    $message = $otherChat->messages()->create(['role' => 'assistant', 'parts' => ['text' => 'secreto'], 'attachments' => []]);

    $this->post(route('chat.speech', $message))->assertForbidden();
});

it('prompts the club provider for the message speech', function (): void {
    config(['ai.providers.openai_vallealto.key' => 'sk-va']);

    $user = User::factory()->create(['club_name' => 'vallealto']);
    $this->actingAs($user);
    $chat = Chat::factory()->for($user)->create();

    Audio::fake();

    $message = $chat->messages()->create([
        'role' => 'assistant',
        'parts' => ['text' => 'Las reservas subieron 12%.'],
        'attachments' => [],
    ]);

    $this->post(route('chat.speech', $message))->assertOk();

    Audio::assertGenerated(fn (AudioPrompt $prompt): bool => $prompt->provider->name() === 'openai_vallealto');
});

it('prompts the club provider for the dictation transcription', function (): void {
    config(['ai.providers.openai_vallealto.key' => 'sk-va']);

    $user = User::factory()->create(['club_name' => 'vallealto']);
    $this->actingAs($user);

    Transcription::fake(['Muéstrame las reservas de golf']);

    $response = $this->post(route('chat.transcribe'), [
        'audio' => UploadedFile::fake()->createWithContent('dictado.webm', 'fake-audio-bytes'),
    ]);

    $response->assertOk();

    Transcription::assertGenerated(fn (TranscriptionPrompt $prompt): bool => $prompt->provider->name() === 'openai_vallealto');
});
