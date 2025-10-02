<?php

declare(strict_types=1);

namespace App\AI;

use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;

interface AIAssistant
{
    public function createThread(array $parameters = []): static;

    public function messages(): ThreadMessageListResponse;

    public function write(string $message): static;

    public function send(): ThreadMessageListResponse;

    public function feed(string $file): static;
}
