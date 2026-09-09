<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Http\Requests\UpdateChatRequest;

/**
 * Cambios que la barra lateral y el encabezado del chat pueden pedir sobre un
 * chat: renombrarlo, fijarlo, compartirlo o votar uno de sus mensajes.
 */
final class UpdateChatData
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $visibility = null,
        public readonly ?bool $pinned = null,
        public readonly ?string $messageId = null,
        public readonly ?bool $isUpvoted = null,
    ) {}

    public static function fromRequest(UpdateChatRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            title: $validated['title'] ?? null,
            visibility: $validated['visibility'] ?? null,
            pinned: isset($validated['pinned']) ? (bool) $validated['pinned'] : null,
            messageId: $validated['message_id'] ?? null,
            isUpvoted: isset($validated['is_upvoted']) ? (bool) $validated['is_upvoted'] : null,
        );
    }
}
