<?php

declare(strict_types=1);

namespace App\AI;

use OpenAI\Responses\Files\CreateResponse;
use OpenAI\Responses\Files\DeleteResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;
use OpenAI\Responses\VectorStores\VectorStoreResponse;

interface AIAssistant
{
    public function createThread(array $parameters = []): static;

    public function messages(): ThreadMessageListResponse;

    public function write(string $message): static;

    public function send(): ThreadMessageListResponse;

    public function feed(string $file): CreateResponse;

    public function deleteFile(string $fileId): DeleteResponse;
}
