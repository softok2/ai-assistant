<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Transcription;

final class ChatTranscriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|max:15360',
        ]);

        $transcription = Transcription::fromUpload($request->file('audio'))->generate();

        return response()->json(['text' => $transcription->text]);
    }
}
