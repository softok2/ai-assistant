<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use App\Models\Message;
use App\Enums\AttachmentKind;
use App\Dtos\LibraryAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Los archivos que un usuario adjuntó en sus propios chats, del más reciente
 * al más antiguo. Es la lista que pinta la Biblioteca.
 */
final class UserAttachmentsQuery
{
    /**
     * @return Collection<int, LibraryAttachment>
     */
    public function execute(User $user, int $limit = 300): Collection
    {
        $prefix = 'chat-attachments/'.$user->id.'/';

        $messages = Message::query()
            ->join('chats', 'chats.id', '=', 'messages.chat_id')
            ->where('chats.user_id', $user->id)
            ->where('messages.role', 'user')
            ->whereNotNull('messages.attachments')
            ->whereNotIn('messages.attachments', ['[]', ''])
            ->orderByDesc('messages.created_at')
            ->orderByDesc('messages.id')
            ->limit($limit)
            ->get(['messages.id', 'messages.attachments', 'messages.created_at', 'chats.id as chat_id', 'chats.title as chat_title']);

        return $messages
            ->flatMap(fn (Message $message): array => $this->attachmentsOf($message, $prefix))
            ->unique(fn (LibraryAttachment $attachment): string => $attachment->path)
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, LibraryAttachment>
     */
    private function attachmentsOf(Message $message, string $prefix): array
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            return [];
        }

        $chatId = (string) $message->getAttribute('chat_id');
        $chatTitle = (string) $message->getAttribute('chat_title');
        $createdAt = $message->created_at?->toISOString();

        return collect($attachments)
            ->filter(fn (mixed $attachment): bool => is_array($attachment)
                && is_string($attachment['path'] ?? null)
                && str_starts_with($attachment['path'], $prefix))
            ->map(fn (array $attachment): LibraryAttachment => $this->toDto($attachment, $chatId, $chatTitle, $createdAt))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function toDto(array $attachment, string $chatId, string $chatTitle, ?string $createdAt): LibraryAttachment
    {
        $path = (string) $attachment['path'];
        $name = is_string($attachment['name'] ?? null) && $attachment['name'] !== ''
            ? $attachment['name']
            : basename($path);
        $mime = is_string($attachment['mime'] ?? null) ? $attachment['mime'] : null;

        return new LibraryAttachment(
            path: $path,
            name: $name,
            mime: $mime,
            kind: AttachmentKind::fromMime($mime, $name),
            bytes: Storage::exists($path) ? Storage::size($path) : null,
            url: route('chat.attachments.show', ['path' => $path]),
            downloadUrl: route('chat.attachments.show', ['path' => $path, 'download' => 1, 'name' => $name]),
            chatId: $chatId,
            chatTitle: $chatTitle,
            createdAt: $createdAt,
        );
    }
}
