<?php

declare(strict_types=1);

namespace App\Actions\Chats;

use App\Models\Chat;
use App\Dtos\UpdateChatData;

/**
 * Aplica los cambios de un chat: título, visibilidad, fijado y el voto de uno
 * de sus mensajes.
 */
final class UpdateChatAction
{
    public function execute(Chat $chat, UpdateChatData $data): Chat
    {
        $this->vote($chat, $data);

        $updates = array_filter([
            'title' => $data->title,
            'visibility' => $data->visibility,
        ], fn (?string $value): bool => $value !== null);

        if ($data->pinned !== null) {
            $updates['pinned_at'] = $data->pinned ? now() : null;
        }

        if ($updates !== []) {
            $chat->update($updates);
        }

        return $chat;
    }

    private function vote(Chat $chat, UpdateChatData $data): void
    {
        if ($data->messageId === null || $data->isUpvoted === null) {
            return;
        }

        $chat->messages()->find($data->messageId)?->update(['is_upvoted' => $data->isUpvoted]);
    }
}
