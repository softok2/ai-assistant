<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\ClubAiProvider;
use Illuminate\Http\Request;
use Laravel\Ai\Transcription;
use Illuminate\Http\JsonResponse;

final class ChatTranscriptionController extends Controller
{
    public function __invoke(Request $request, ClubAiProvider $providers): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|max:15360',
        ]);

        $transcription = Transcription::fromUpload($request->file('audio'))
            ->generate(provider: $providers->nameFor($request->user()?->clubName()));

        return response()->json(['text' => $transcription->text]);
    }
}
