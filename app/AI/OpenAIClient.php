<?php

declare(strict_types=1);

namespace App\AI;

use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Threads\ThreadResponse;
use OpenAI\Responses\Assistants\AssistantResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;

final class OpenAIClient implements AIClient
{
    public function retrieveAssistant(string $assistantId): AssistantResponse
    {
        return OpenAI::assistants()->retrieve($assistantId);
    }

    public function createThread(array $parameters): ThreadResponse
    {
        return OpenAI::threads()->create($parameters);
    }

    public function retrieveThread(string $threadId): ThreadResponse
    {
        return OpenAI::threads()->retrieve($threadId);
    }

    public function createMessage(string $threadId, string $message): ThreadMessageResponse
    {
        return OpenAI::threads()->messages()->create($threadId, [
            'content' => $message,
            'role' => 'user',
        ]);
    }

    public function messages(string $threadId): ThreadMessageListResponse
    {
        return OpenAI::threads()->messages()->list($threadId);
    }

    public function run(string $threadId, AssistantResponse $assistant): ThreadMessageListResponse
    {
        $run = OpenAI::threads()->runs()->create($threadId, [
            'assistant_id' => $assistant->id,
        ]);

        while ($this->runStatus($run)) {
            sleep(1);
        }

        return $this->messages($threadId);
    }

    public function runStatus(ThreadRunResponse $run): bool
    {
        $run = OpenAI::threads()->runs()->retrieve(
            threadId: $run->threadId,
            runId: $run->id
        );

        return $run->status !== 'completed';
    }

    public function createStream(string $threadId, AssistantResponse $assistant)
    {
        return OpenAI::threads()->runs()->createStreamed($threadId, [
            'assistant_id' => $assistant->id,
        ]);
    }
}
