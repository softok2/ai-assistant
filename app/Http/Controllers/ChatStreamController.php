<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\ClubAssistant;
use App\Jobs\GenerateChatTitle;
use App\Models\Chat;
use App\Http\Requests\ChatStreamRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;

final class ChatStreamController extends Controller
{
    public function __invoke(ChatStreamRequest $request, Chat $chat): StreamableAgentResponse
    {
        Gate::authorize('update', $chat);

        $validated = $request->validated();

        [$userMessage, $attachments, $history] = match (true) {
            (bool) ($validated['regenerate'] ?? false) => $this->prepareRegeneration($chat),
            isset($validated['edit_message_id']) => $this->prepareEdit($chat, $validated, $request->user()->id),
            default => $this->prepareNewMessage($chat, $validated, $request->user()->id),
        };

        return ClubAssistant::make(history: $history)
            ->stream($userMessage, attachments: $this->toAiFiles($attachments), model: $validated['model'] ?? null)
            ->then(function (StreamedAgentResponse $response) use ($chat): void {
                if (trim($response->text) === '') {
                    return;
                }

                $chat->messages()->create([
                    'role' => 'assistant',
                    'parts' => ['text' => $response->text],
                    'attachments' => [],
                ]);

                $chat->touch();

                if ($chat->messages()->count() === 2) {
                    GenerateChatTitle::dispatch($chat);
                }
            });
    }

    /**
     * Persist the incoming user message and return the prompt context.
     *
     * @return array{0: string, 1: array, 2: Collection}
     */
    private function prepareNewMessage(Chat $chat, array $validated, int $userId): array
    {
        $userMessage = trim($validated['message']);
        $attachments = $this->ownedAttachments($userId, $validated['attachments'] ?? []);

        $history = $chat->messages()->orderBy('created_at')->orderBy('id')->get();

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
            ->where('role', 'user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        abort_if($lastUserMessage === null, 422, 'No hay mensaje para regenerar.');

        $chat->messages()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $lastUserMessage->created_at)
            ->delete();

        $history = $chat->messages()
            ->whereKeyNot($lastUserMessage->id)
            ->where('created_at', '<=', $lastUserMessage->created_at)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $attachments = is_array($lastUserMessage->attachments) ? $lastUserMessage->attachments : [];
        $attachments = array_values(array_filter($attachments, fn ($a) => is_array($a) && isset($a['path'])));

        return [$lastUserMessage->parts['text'] ?? '', $attachments, $history];
    }

    /**
     * ChatGPT-style edit: replace a previous user message and drop everything
     * after it, then continue as a fresh exchange from that point.
     *
     * @return array{0: string, 1: array, 2: Collection}
     */
    private function prepareEdit(Chat $chat, array $validated, int $userId): array
    {
        $edited = $chat->messages()
            ->where('role', 'user')
            ->whereKey($validated['edit_message_id'])
            ->first();

        abort_if($edited === null, 422, 'El mensaje a editar no existe.');

        $userMessage = trim($validated['message']);

        $attachments = array_values(array_filter(
            is_array($edited->attachments) ? $edited->attachments : [],
            fn ($a) => is_array($a) && isset($a['path'])
        ));

        $history = $chat->messages()
            ->where('created_at', '<', $edited->created_at)
            ->whereKeyNot($edited->id)
            ->orderBy('created_at')
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
