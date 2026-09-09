<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\User;
use App\Models\Message;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * @param  array<int, array<string, string>>  $attachments
 */
function messageWithAttachments(Chat $chat, array $attachments, string $role = 'user'): Message
{
    return $chat->messages()->create([
        'role' => $role,
        'parts' => ['text' => 'Revisa esto'],
        'attachments' => $attachments,
    ]);
}

it('lists the attachments of the user, the most recent first', function (): void {
    $chat = Chat::factory()->for($this->user)->create(['title' => 'Ocupación del campo']);

    Storage::put("chat-attachments/{$this->user->id}/tarifas.pdf", 'pdf');
    Storage::put("chat-attachments/{$this->user->id}/foto.jpg", 'jpg');

    messageWithAttachments($chat, [
        ['path' => "chat-attachments/{$this->user->id}/tarifas.pdf", 'name' => 'tarifas.pdf', 'mime' => 'application/pdf'],
    ])->forceFill(['created_at' => now()->subDay()])->save();

    messageWithAttachments($chat, [
        ['path' => "chat-attachments/{$this->user->id}/foto.jpg", 'name' => 'foto.jpg', 'mime' => 'image/jpeg'],
    ]);

    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library')
            ->has('attachments', 2)
            ->where('attachments.0.name', 'foto.jpg')
            ->where('attachments.0.kind', 'image')
            ->where('attachments.0.bytes', 3)
            ->where('attachments.0.chat_title', 'Ocupación del campo')
            ->where('attachments.0.chat_id', $chat->id)
            ->where('attachments.0.url', route('chat.attachments.show', ['path' => "chat-attachments/{$this->user->id}/foto.jpg"]))
            ->where('attachments.0.download_url', route('chat.attachments.show', [
                'path' => "chat-attachments/{$this->user->id}/foto.jpg",
                'download' => 1,
                'name' => 'foto.jpg',
            ]))
            ->where('attachments.1.name', 'tarifas.pdf')
            ->where('attachments.1.kind', 'pdf')
            ->has('kindLabels')
            ->has('chatHistory'));
});

it('ignores the chats of other users and the messages of the assistant', function (): void {
    $other = User::factory()->create();
    $otherChat = Chat::factory()->for($other)->create();
    messageWithAttachments($otherChat, [
        ['path' => "chat-attachments/{$other->id}/ajeno.pdf", 'name' => 'ajeno.pdf', 'mime' => 'application/pdf'],
    ]);

    $chat = Chat::factory()->for($this->user)->create();
    messageWithAttachments($chat, [
        ['path' => "chat-attachments/{$this->user->id}/respuesta.pdf", 'name' => 'respuesta.pdf', 'mime' => 'application/pdf'],
    ], 'assistant');

    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('attachments', []));
});

it('drops paths outside the folder of the user and keeps one row per file', function (): void {
    $other = User::factory()->create();
    $chat = Chat::factory()->for($this->user)->create();

    messageWithAttachments($chat, [
        ['path' => "chat-attachments/{$this->user->id}/reporte.pdf", 'name' => 'reporte.pdf', 'mime' => 'application/pdf'],
        ['path' => "chat-attachments/{$other->id}/ajeno.pdf", 'name' => 'ajeno.pdf', 'mime' => 'application/pdf'],
        ['path' => '../../etc/passwd', 'name' => 'passwd', 'mime' => 'text/plain'],
        'suelto.pdf',
    ])->forceFill(['created_at' => now()->subDay()])->save();

    messageWithAttachments($chat, [
        ['path' => "chat-attachments/{$this->user->id}/reporte.pdf", 'name' => 'reporte.pdf', 'mime' => 'application/pdf'],
    ]);

    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('attachments', 1)
            ->where('attachments.0.name', 'reporte.pdf')
            ->where('attachments.0.bytes', null));
});

it('falls back to the extension when the attachment has no mime', function (): void {
    $chat = Chat::factory()->for($this->user)->create();

    messageWithAttachments($chat, [
        ['path' => "chat-attachments/{$this->user->id}/ocupacion.xlsx", 'name' => 'ocupacion.xlsx', 'mime' => ''],
    ]);

    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attachments.0.kind', 'spreadsheet')
            ->where('kindLabels.spreadsheet.label', 'Hoja de cálculo')
            ->where('kindLabels.spreadsheet.plural', 'Hojas de cálculo'));
});

it('renders an empty library when the user never attached anything', function (): void {
    Chat::factory()->for($this->user)->create();

    $this->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library')
            ->where('attachments', []));
});

it('downloads an attachment with its original name', function (): void {
    Storage::put("chat-attachments/{$this->user->id}/foto.jpg", 'jpg');

    $this->get(route('chat.attachments.show', [
        'path' => "chat-attachments/{$this->user->id}/foto.jpg",
        'download' => 1,
        'name' => 'foto.jpg',
    ]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=foto.jpg');
});
