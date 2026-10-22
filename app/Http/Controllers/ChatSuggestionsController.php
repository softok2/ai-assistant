<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Throwable;
use App\Models\Chat;
use App\Ai\ClubAiProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Ai\Agents\ChatFollowUpSuggester;

final class ChatSuggestionsController extends Controller
{
    public function __invoke(Request $request, Chat $chat, ClubAiProvider $providers): JsonResponse
    {
        Gate::authorize('update', $chat);

        $exchange = $chat->messages()->latest()
            ->limit(2)
            ->get()
            ->reverse()
            ->map(fn ($message): string => ($message->role === 'user' ? 'Usuario: ' : 'Asistente: ').($message->parts['text'] ?? ''))
            ->join("\n\n");

        if (mb_trim($exchange) === '') {
            return response()->json(['suggestions' => []]);
        }

        try {
            $response = ChatFollowUpSuggester::forUser($request->user())
                ->prompt($exchange, provider: $providers->nameFor($request->user()?->clubName()));
            $suggestions = collect($response->structured['suggestions'] ?? [])
                ->filter(fn ($s): bool => is_string($s) && mb_trim($s) !== '')
                ->take(3)
                ->values()
                ->all();
        } catch (Throwable $throwable) {
            report($throwable);
            $suggestions = [];
        }

        return response()->json(['suggestions' => $suggestions]);
    }
}
