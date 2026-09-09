<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Support\Facades\Gate;
use App\Reports\Contracts\RendersMessageAsPdf;
use Symfony\Component\HttpFoundation\Response;

final class ChatMessagePdfController extends Controller
{
    public function __invoke(Message $message, RendersMessageAsPdf $renderer): Response
    {
        Gate::authorize('view', $message->chat);

        abort_if($message->role !== 'assistant', 404);

        return response($renderer->render($message), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$renderer->filename($message).'"',
        ]);
    }
}
