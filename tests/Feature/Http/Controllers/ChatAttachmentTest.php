<?php

declare(strict_types=1);

use App\Ai\Agents\ClubAssistant;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Transcription;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('uploads an attachment and returns its metadata', function (): void {
    $response = $this->post(route('chat.attachments.store'), [
        'file' => UploadedFile::fake()->image('cancha.png'),
    ]);

    $response->assertOk();
    $payload = $response->json();

    expect($payload['name'])->toBe('cancha.png');
    expect($payload['path'])->toStartWith('chat-attachments/'.$this->user->id.'/');
    Storage::assertExists($payload['path']);
});

it('rejects disallowed file types', function (): void {
    $this->post(route('chat.attachments.store'), [
        'file' => UploadedFile::fake()->create('script.sh', 10, 'application/x-sh'),
    ])->assertSessionHasErrors('file');
});

it('denies downloading attachments of other users', function (): void {
    $other = User::factory()->create();
    Storage::put("chat-attachments/{$other->id}/secreto.pdf", 'contenido');

    $this->get(route('chat.attachments.show', ['path' => "chat-attachments/{$other->id}/secreto.pdf"]))
        ->assertForbidden();
});

it('serves own attachments', function (): void {
    Storage::put("chat-attachments/{$this->user->id}/mio.txt", 'hola');

    $this->get(route('chat.attachments.show', ['path' => "chat-attachments/{$this->user->id}/mio.txt"]))
        ->assertOk();
});

it('transcribes dictated audio', function (): void {
    Transcription::fake(['Muéstrame las reservas de golf']);

    $response = $this->post(route('chat.transcribe'), [
        'audio' => UploadedFile::fake()->createWithContent('dictado.webm', 'fake-audio-bytes'),
    ]);

    $response->assertOk();
    expect($response->json('text'))->toBe('Muéstrame las reservas de golf');
});

it('streams with attachments and persists only owned ones', function (): void {
    ClubAssistant::fake(['Analizado.']);

    $chat = Chat::factory()->for($this->user)->create();
    $other = User::factory()->create();

    Storage::put("chat-attachments/{$this->user->id}/reporte.pdf", 'pdf');
    Storage::put("chat-attachments/{$other->id}/ajeno.pdf", 'pdf');

    $this->post(route('chat.stream', $chat), [
        'message' => 'Analiza este reporte',
        'attachments' => [
            ['path' => "chat-attachments/{$this->user->id}/reporte.pdf", 'name' => 'reporte.pdf', 'mime' => 'application/pdf'],
            ['path' => "chat-attachments/{$other->id}/ajeno.pdf", 'name' => 'ajeno.pdf', 'mime' => 'application/pdf'],
        ],
    ])->assertOk()->streamedContent();

    $userMessage = $chat->messages()->where('role', 'user')->first();

    expect($userMessage->attachments)->toHaveCount(1);
    expect($userMessage->attachments[0]['name'])->toBe('reporte.pdf');
});
