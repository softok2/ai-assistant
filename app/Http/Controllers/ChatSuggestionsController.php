<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Throwable;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Ai\Agents\ChatFollowUpSuggester;

final class ChatSuggestionsController extends Controller
{
    public function __invoke(Request $request, Chat $chat): JsonResponse
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
            $response = ChatFollowUpSuggester::forUser($request->user())->prompt($exchange);
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
