<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Throwable;
use App\Models\Chat;
use App\Ai\ClubAiProvider;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Files\Document;
use App\Jobs\GenerateChatTitle;
use App\Ai\Agents\ClubAssistant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Streaming\Events\Error;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ChatStreamRequest;
use Laravel\Ai\Exceptions\StreamErrorException;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use App\Actions\Chats\ResolveStreamInsightsAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChatStreamController extends Controller
{
    public function __construct(private readonly ResolveStreamInsightsAction $insights) {}

    public function __invoke(ChatStreamRequest $request, Chat $chat, ClubAiProvider $providers): StreamedResponse
    {
        Gate::authorize('update', $chat);

        $validated = $request->validated();

        [$userMessage, $attachments, $history] = match (true) {
            (bool) ($validated['regenerate'] ?? false) => $this->prepareRegeneration($chat),
            isset($validated['edit_message_id']) => $this->prepareEdit($chat, $validated),
            default => $this->prepareNewMessage($chat, $validated, $request->user()->id),
        };

        $stream = ClubAssistant::forUser($request->user(), $history)
            ->stream(
                $userMessage,
                attachments: $this->toAiFiles($attachments),
                provider: $providers->nameFor($request->user()->clubName()),
                model: $validated['model'] ?? null,
            )
            ->then(function (StreamedAgentResponse $response) use ($chat): void {
                if (mb_trim($response->text) === '') {
                    return;
                }

                $chat->messages()->create([
                    'role' => 'assistant',
                    'parts' => [
                        'text' => $response->text,
                        ...$this->insights->execute($response->events)->toParts(),
                    ],
                    'attachments' => [],
                ]);

                $chat->touch();

                if ($chat->messages()->count() === 2) {
                    GenerateChatTitle::dispatch($chat);
                }
            });

        return $this->toEventStream($stream);
    }

    /**
     * Serialise the agent stream as SSE exactly like StreamableAgentResponse::toResponse()
     * (`data: {json}` frames ending in `data: [DONE]`), but end with a terminal error event
     * instead of throwing once the headers are already sent.
     */
    private function toEventStream(StreamableAgentResponse $stream): StreamedResponse
    {
        return response()->stream(function () use ($stream) {
            $errored = false;

            try {
                foreach ($stream as $event) {
                    $errored = $errored || $event instanceof Error;

                    yield 'data: '.$event."\n\n";
                }
            } catch (Throwable $throwable) {
                // A stream error exception follows a provider error event the client already got...
                report_unless($throwable instanceof StreamErrorException, $throwable);

                if (! $errored) {
                    yield 'data: '.json_encode(['type' => 'error', 'message' => 'stream_failed', 'recoverable' => false])."\n\n";
                }
            }

            yield "data: [DONE]\n\n";
        }, headers: [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Persist the incoming user message and return the prompt context.
     *
     * @return array{0: string, 1: array, 2: Collection}
     */
    private function prepareNewMessage(Chat $chat, array $validated, int $userId): array
    {
        $userMessage = mb_trim($validated['message']);
        $attachments = $this->ownedAttachments($userId, $validated['attachments'] ?? []);

        $history = $chat->messages()->oldest()->orderBy('id')->get();

        $chat->messages()->create([
            'role' => 'user',
            'parts' => ['text' => $userMessage],
            'attachments' => $attachments,
        ]);

        return [$userMessage, $attachments, $history];
    }

    /**
     * ChatGPT-style regenerate: replace the trailing assistant response by
     * re-prompting with the last user message; nothing new is persisted here.
     *
     * @return array{0: string, 1: array, 2: Collection}
     */
    private function prepareRegeneration(Chat $chat): array
    {
        $lastUserMessage = $chat->messages()
            ->where('role', 'user')->latest()
            ->orderByDesc('id')
            ->first();

        abort_if($lastUserMessage === null, 422, 'No hay mensaje para regenerar.');

        $chat->messages()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $lastUserMessage->created_at)
            ->delete();

        $history = $chat->messages()
            ->whereKeyNot($lastUserMessage->id)
            ->where('created_at', '<=', $lastUserMessage->created_at)->oldest()
            ->orderBy('id')
            ->get();

        $attachments = is_array($lastUserMessage->attachments) ? $lastUserMessage->attachments : [];
        $attachments = array_values(array_filter($attachments, fn ($a): bool => is_array($a) && isset($a['path'])));

        return [$lastUserMessage->parts['text'] ?? '', $attachments, $history];
    }

    /**
     * ChatGPT-style edit: replace a previous user message and drop everything
     * after it, then continue as a fresh exchange from that point.
     *
     * @return array{0: string, 1: array, 2: Collection}
     */
    private function prepareEdit(Chat $chat, array $validated): array
    {
        $edited = $chat->messages()
            ->where('role', 'user')
            ->whereKey($validated['edit_message_id'])
            ->first();

        abort_if($edited === null, 422, 'El mensaje a editar no existe.');

        $userMessage = mb_trim($validated['message']);

        $attachments = array_values(array_filter(
            is_array($edited->attachments) ? $edited->attachments : [],
            fn ($a): bool => is_array($a) && isset($a['path'])
        ));

        $history = $chat->messages()
            ->where('created_at', '<', $edited->created_at)
            ->whereKeyNot($edited->id)->oldest()
            ->orderBy('id')
            ->get();

        $chat->messages()
            ->where('created_at', '>=', $edited->created_at)
            ->delete();

        $chat->messages()->create([
            'role' => 'user',
            'parts' => ['text' => $userMessage],
            'attachments' => $attachments,
        ]);

        return [$userMessage, $attachments, $history];
    }

    /**
     * Keep only attachments that belong to the authenticated user and still exist.
     *
     * @return array<int, array{path: string, name: string, mime: string}>
     */
    private function ownedAttachments(int $userId, array $attachments): array
    {
        $prefix = 'chat-attachments/'.$userId.'/';

        return collect($attachments)
            ->filter(fn (array $attachment): bool => str_starts_with($attachment['path'], $prefix)
                && Storage::exists($attachment['path']))
            ->map(fn (array $attachment): array => [
                'path' => $attachment['path'],
                'name' => $attachment['name'],
                'mime' => $attachment['mime'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{path: string, name: string, mime: string}>  $attachments
     * @return array<int, Document|Image>
     */
    private function toAiFiles(array $attachments): array
    {
        return collect($attachments)
            ->filter(fn (array $attachment): bool => Storage::exists($attachment['path']))
            ->map(fn (array $attachment): Document|Image => str_starts_with($attachment['mime'], 'image/')
                ? Image::fromStorage($attachment['path'])
                : Document::fromStorage($attachment['path']))
            ->all();
    }
}
