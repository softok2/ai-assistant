<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Enums\AttachmentKind;

/**
 * Un archivo que el usuario adjuntó a alguno de sus chats, tal como lo lista
 * la Biblioteca.
 */
final readonly class LibraryAttachment
{
    public function __construct(
        public string $path,
        public string $name,
        public ?string $mime,
        public AttachmentKind $kind,
        public ?int $bytes,
        public string $url,
        public string $downloadUrl,
        public string $chatId,
        public string $chatTitle,
        public ?string $createdAt,
    ) {}

    /**
     * @return array{
     *     path: string,
     *     name: string,
     *     mime: ?string,
     *     kind: string,
     *     bytes: ?int,
     *     url: string,
     *     download_url: string,
     *     chat_id: string,
     *     chat_title: string,
     *     created_at: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name,
            'mime' => $this->mime,
            'kind' => $this->kind->value,
            'bytes' => $this->bytes,
            'url' => $this->url,
            'download_url' => $this->downloadUrl,
            'chat_id' => $this->chatId,
            'chat_title' => $this->chatTitle,
            'created_at' => $this->createdAt,
        ];
    }
}
