<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatAttachmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChatAttachmentController extends Controller
{
    public function store(StoreChatAttachmentRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $path = $file->store('chat-attachments/'.$request->user()->id);

        return response()->json([
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'url' => route('chat.attachments.show', ['path' => $path]),
        ]);
    }

    public function show(Request $request, string $path): StreamedResponse
    {
        abort_unless(str_starts_with($path, 'chat-attachments/'.$request->user()->id.'/'), 403);
        abort_unless(Storage::exists($path), 404);

        return Storage::response($path);
    }
}
