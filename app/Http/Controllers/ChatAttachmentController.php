<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreChatAttachmentRequest;
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

    /**
     * Con `?download=1` el archivo se descarga con su nombre original en vez de
     * abrirse en el navegador, que es lo que necesita la Biblioteca.
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        abort_unless(str_starts_with($path, 'chat-attachments/'.$request->user()->id.'/'), 403);
        abort_unless(Storage::exists($path), 404);

        if (! $request->boolean('download')) {
            return Storage::response($path);
        }

        $name = basename((string) $request->query('name', ''));

        return Storage::download($path, $name === '' ? null : $name);
    }
}
