<?php

declare(strict_types=1);

namespace App\AI;

use OpenAI\Responses\Files\CreateResponse;
use OpenAI\Responses\Files\DeleteResponse;
use OpenAI\Responses\Assistants\AssistantResponse;
use OpenAI\Responses\VectorStores\VectorStoreResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;

final class OpenAIAssistant implements AIAssistant
{
    private AssistantResponse $assistant;

    private VectorStoreResponse $vectorStore;

    private string $threadId;

    private AIClient $client;

    public function __construct(string $assistantId, string $vectorStoreId, ?AiClient $client = null)
    {
        $this->client = $client ?: new OpenAIClient;

        $this->assistant = $this->client->retrieveAssistant($assistantId);
        $this->vectorStore = $this->client->retrieveVectorStore($vectorStoreId);
    }

    public function createThread(array $parameters = []): static
    {
        $thread = $this->client->createThread($parameters);
        $this->threadId = $thread->id;

        return $this;
    }

    public function messages(): ThreadMessageListResponse
    {
        return $this->client->messages($this->threadId);
    }

    public function write(string $message): static
    {
        $this->client->createMessage(
            threadId: $this->threadId,
            message: $message
        );

        return $this;
    }

    public function send(): ThreadMessageListResponse
    {
        return $this->client->run(
            threadId: $this->threadId,
            assistant: $this->assistant
        );
    }

    public function stream()
    {
        return $this->client->createStream($this->threadId, $this->assistant);
    }

    public function feed(string $file): CreateResponse
    {
        return $this->client->feed($file, $this->vectorStore);
    }

    public function deleteFile(string $fileId): DeleteResponse
    {
        return $this->client->removeFile($fileId, $this->vectorStore);
    }
}
