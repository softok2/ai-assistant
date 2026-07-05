<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\ChatFollowUpSuggester;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class ChatSuggestionsController extends Controller
{
    public function __invoke(Chat $chat): JsonResponse
    {
        Gate::authorize('update', $chat);

        $exchange = $chat->messages()
            ->orderByDesc('created_at')
            ->limit(2)
            ->get()
            ->reverse()
            ->map(fn ($message) => ($message->role === 'user' ? 'Usuario: ' : 'Asistente: ').($message->parts['text'] ?? ''))
            ->join("\n\n");

        if (trim($exchange) === '') {
            return response()->json(['suggestions' => []]);
        }

        try {
            $response = (new ChatFollowUpSuggester)->prompt($exchange);
            $suggestions = collect($response->structured['suggestions'] ?? [])
                ->filter(fn ($s) => is_string($s) && trim($s) !== '')
                ->take(3)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);
            $suggestions = [];
        }

        return response()->json(['suggestions' => $suggestions]);
    }
}
