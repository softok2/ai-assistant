<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Generator;
use Throwable;
use App\Models\Chat;
use App\Models\Message;
use App\AI\OpenAIAssistant;
use Prism\Prism\Enums\ChunkType;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\ChatStreamRequest;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChatStreamController extends Controller
{
    public function __invoke(ChatStreamRequest $request, Chat $chat): StreamedResponse
    {
        $userMessage = $request->string('message')->trim()->value();

        $chat->messages()->create([
            'role' => 'user',
            'parts' => [
                ChunkType::Text->value => $userMessage,
            ],
            'attachments' => '[]',
        ]);

        $messages = $this->buildConversationHistory($chat);

        $assistant = new OpenAIAssistant(config('services.openai.assistant_id'));

        return Response::stream(function () use ($chat, $assistant, $userMessage, $messages): Generator {
            $parts = [];

            try {

                $response = $assistant
                    ->createThread()
                    ->withMessages($messages)
                    ->write($userMessage)
                    ->stream();

                foreach ($response as $chunk) {
                    if ($chunk->event === 'thread.message.delta') {
                        $chunkMessage = $chunk->response->delta?->content[0]['text']['value'] ?? '';

                        $chunkData = [
                            'chunkType' => ChunkType::Text->value,
                            'content' => $chunkMessage,
                        ];

                        if (! isset($parts[ChunkType::Text->value])) {
                            $parts[ChunkType::Text->value] = '';
                        }

                        $parts[ChunkType::Text->value] .= $chunkMessage;

                        yield json_encode($chunkData)."\n";
                    }

                    if ($chunk->event === 'thread.message.completed') {
                        // Save message to database or perform other actions

                        if ($parts !== []) {
                            $chat->messages()->create([
                                'role' => 'assistant',
                                'parts' => $parts,
                                'attachments' => '[]',
                            ]);
                            $chat->touch();
                        }
                    }
                }

            } catch (Throwable $throwable) {
                Log::error("Chat stream error for chat {$chat->id}: ".$throwable->getMessage());

                yield json_encode([
                    'chunkType' => 'error',
                    'content' => 'Stream failed',
                ])."\n";
            }
        });
    }

    private function buildConversationHistory(Chat $chat): array
    {
        return $chat->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message): array => match ($message->role) {
                'user' => [
                    'content' => $message->parts['text'] ?? '',
                    'role' => 'user',
                ],
                'assistant' => [
                    'content' => $message->parts['text'] ?? '',
                    'role' => 'assistant',
                ],
            })
            ->toArray();
    }
}
