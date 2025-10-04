<?php

declare(strict_types=1);

namespace App\AI;

use OpenAI\Responses\Files\CreateResponse;
use OpenAI\Responses\Files\DeleteResponse;
use OpenAI\Responses\Threads\ThreadResponse;
use OpenAI\Responses\Assistants\AssistantResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponse;
use OpenAI\Responses\VectorStores\Files\VectorStoreFileDeleteResponse;
use OpenAI\Responses\VectorStores\Files\VectorStoreFileResponse;
use OpenAI\Responses\VectorStores\VectorStoreResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;

interface AIClient
{
    public function retrieveAssistant(string $assistantId): AssistantResponse;

    public function createThread(array $parameters): ThreadResponse;

    public function retrieveThread(string $threadId): ThreadResponse;

    public function createMessage(string $threadId, string $message): ThreadMessageResponse;

    public function messages(string $threadId): ThreadMessageListResponse;

    public function run(string $threadId, AssistantResponse $assistant): ThreadMessageListResponse;

    public function runStatus(ThreadRunResponse $run): bool;

    public function feed(string $file, VectorStoreResponse $vectorStore): CreateResponse;

    public function retrieveVectorStore(string $vectorId): VectorStoreResponse;

    public function removeFile(string $fileId, VectorStoreResponse $vectorStore): DeleteResponse;
}
