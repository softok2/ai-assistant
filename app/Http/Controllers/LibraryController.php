<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Enums\AttachmentKind;
use App\Dtos\LibraryAttachment;
use App\Queries\ChatHistoryQuery;
use App\Queries\UserAttachmentsQuery;

/**
 * Biblioteca del usuario: los archivos que él mismo adjuntó en sus chats.
 */
final class LibraryController extends Controller
{
    public function __invoke(
        Request $request,
        UserAttachmentsQuery $attachments,
        ChatHistoryQuery $chatHistory,
    ): Response {
        $user = $request->user();

        return Inertia::render('Library', [
            'attachments' => $attachments->execute($user)
                ->map(fn (LibraryAttachment $attachment): array => $attachment->toArray())
                ->all(),
            'kindLabels' => $this->kindLabels(),
            'chatHistory' => Inertia::deepMerge($chatHistory->execute($user)),
        ]);
    }

    /**
     * Las etiquetas de cada tipo salen del enum para que la página no tenga
     * que repetir las traducciones.
     *
     * @return array<string, array{label: string, plural: string}>
     */
    private function kindLabels(): array
    {
        return collect(AttachmentKind::cases())
            ->mapWithKeys(fn (AttachmentKind $kind): array => [
                $kind->value => ['label' => $kind->label(), 'plural' => $kind->pluralLabel()],
            ])
            ->all();
    }
}
