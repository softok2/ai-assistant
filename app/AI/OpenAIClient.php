<?php

declare(strict_types=1);

namespace App\AI;

use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\StreamResponse;
use OpenAI\Responses\Files\CreateResponse;
use OpenAI\Responses\Files\DeleteResponse;
use OpenAI\Responses\Threads\ThreadResponse;
use OpenAI\Responses\Assistants\AssistantResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponse;
use OpenAI\Responses\VectorStores\VectorStoreResponse;
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

    public function createStream(string $threadId, AssistantResponse $assistant, array $messages = []): StreamResponse
    {
        return OpenAI::threads()->runs()->createStreamed($threadId, [
            'assistant_id' => $assistant->id,
            'additional_messages' => $messages,
        ]);
    }

    public function feed(string $file, VectorStoreResponse $vectorStore): CreateResponse
    {
        $file = OpenAI::files()->upload([
            'file' => fopen($file, 'rb'),
            'purpose' => 'assistants',
        ]);

        OpenAI::vectorStores()->files()->create(
            vectorStoreId: $vectorStore->id,
            parameters: [
                'file_id' => $file->id,
            ],
        );

        // Wait for the file to be processed and indexed in the vector store
        while ($this->checkFileStatus($vectorStore->id, $file->id)) {
            sleep(1);
        }

        return $file;
    }

    public function retrieveVectorStore(string $vectorId): VectorStoreResponse
    {
        return OpenAI::vectorStores()->retrieve($vectorId);
    }

    public function removeFile(string $fileId, VectorStoreResponse $vectorStore): DeleteResponse
    {
        OpenAI::vectorStores()->files()->delete(
            vectorStoreId: $vectorStore->id,
            fileId: $fileId
        );

        return OpenAI::files()->delete($fileId);
    }

    private function checkFileStatus(string $vectorStoreId, string $fieldId): bool
    {
        $vectorStoreFileResponse = OpenAI::vectorStores()->files()->retrieve(
            vectorStoreId: $vectorStoreId,
            fileId: $fieldId
        );

        return $vectorStoreFileResponse->status !== 'completed';
    }
}
