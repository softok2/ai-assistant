<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Audio;

final class ChatSpeechController extends Controller
{
    public function __invoke(Message $message): JsonResponse
    {
        Gate::authorize('view', $message->chat);

        $text = $this->speakableText($message->parts['text'] ?? '');

        abort_if($text === '', 422, 'El mensaje no tiene texto para leer.');

        $response = Audio::of($text)->generate();

        return response()->json([
            'audio' => $response->audio,
            'mime' => $response->mimeType() ?? 'audio/mpeg',
        ]);
    }

    /**
     * Markdown y bloques de gráficas no se leen bien en voz alta.
     */
    private function speakableText(string $text): string
    {
        $text = preg_replace('/```chart\s*\n[\s\S]*?```/', '', $text);
        $text = preg_replace('/^\s*\{"type"[^\n]*\}\s*$/m', '', $text);
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        $text = preg_replace('/[#*_`>-]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim(mb_substr($text, 0, 4000));
    }
}
