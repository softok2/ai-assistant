<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Chat;
use Inertia\Inertia;
use Inertia\Response;
use App\Dtos\UpdateChatData;
use App\Dtos\LibrarySnapshot;
use App\Queries\ChatHistoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Queries\LibrarySnapshotQuery;
use Illuminate\Http\RedirectResponse;
use App\Actions\Chats\UpdateChatAction;
use App\Http\Requests\StoreChatRequest;
use App\Actions\Chats\SearchChatsAction;
use App\Http\Requests\UpdateChatRequest;
use App\Http\Requests\SearchChatsRequest;
use App\Actions\Chats\ResolveChatStartersAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ChatController extends Controller
{
    private ?LibrarySnapshot $snapshot = null;

    public function __construct(
        private readonly LibrarySnapshotQuery $library,
        private readonly ChatHistoryQuery $chatHistory,
    ) {
        $this->authorizeResource(Chat::class, 'chat');
    }

    public function index(ResolveChatStartersAction $starters): Response
    {
        return Inertia::render('Chat/Index', [
            'chatHistory' => Inertia::deepMerge($this->history()),
            'dataFreshness' => fn (): ?string => $this->snapshot()->freshness(),
            'sources' => fn (): array => $this->sourcesProp($this->snapshot()),
            // Proponer las preguntas cuesta una llamada al modelo: la pantalla
            // se pinta primero y las sugerencias llegan después.
            'starters' => Inertia::defer(function () use ($starters): array {
                $user = Auth::user();

                return $starters->execute(
                    $user?->clubName(),
                    $user?->primaryRole(),
                    $this->library->execute(withDocuments: true),
                );
            }),
        ]);
    }

    public function store(StoreChatRequest $request): RedirectResponse
    {
        $message = $request->validated()['message'];

        $chat = Auth::user()->chats()->create([
            'title' => str($message)->limit(80)->value(),
            'visibility' => $request->validated()['visibility'],
        ]);

        return to_route('chats.show', ['chat' => $chat])
            ->with('pending_message', $message)
            ->with('pending_attachments', $request->validated()['attachments'] ?? []);
    }

    public function show(Chat $chat): Response
    {
        Gate::authorize('view', $chat);

        return Inertia::render('Chat/Show', [
            'chat' => fn () => $chat->load('messages'),
            'chatHistory' => Inertia::deepMerge($this->history()),
            'dataFreshness' => fn (): ?string => $this->snapshot()->freshness(),
            'sources' => fn (): array => $this->sourcesProp($this->snapshot()),
            'pendingMessage' => session('pending_message'),
            'pendingAttachments' => session('pending_attachments', []),
            'canWrite' => Auth::id() === $chat->user_id,
        ]);
    }

    /**
     * Buscador del diálogo de chats. Devuelve JSON porque el diálogo consulta
     * mientras el usuario escribe, sin recargar la página.
     */
    public function search(SearchChatsRequest $request, SearchChatsAction $action): JsonResponse
    {
        return response()->json(
            $action->execute($request->user(), $request->validated()['q'] ?? null)
        );
    }

    /**
     * Fijar, renombrar o compartir se piden desde la barra lateral de
     * cualquier pantalla, así que la respuesta vuelve a donde estaba el
     * usuario. Redirigir al chat editado abría el chat equivocado.
     */
    public function update(Chat $chat, UpdateChatRequest $request, UpdateChatAction $action): RedirectResponse
    {
        Gate::authorize('update', $chat);

        $action->execute($chat, UpdateChatData::fromRequest($request));

        return back();
    }

    public function destroy(Chat $chat): RedirectResponse
    {
        Gate::authorize('delete', $chat);

        $chat->messages()->delete();
        $chat->delete();

        return to_route('chats.index');
    }

    /**
     * @return LengthAwarePaginator<int, Chat>|null
     */
    private function history(): ?LengthAwarePaginator
    {
        return $this->chatHistory->execute(Auth::user());
    }

    /**
     * Una sola consulta agregada por petición, y solo si la respuesta incluye
     * alguna de las dos props que la usan.
     */
    private function snapshot(): LibrarySnapshot
    {
        return $this->snapshot ??= $this->library->execute();
    }

    /**
     * @return array{count: int, synced_at: ?string}
     */
    private function sourcesProp(LibrarySnapshot $library): array
    {
        return [
            'count' => $library->documentCount,
            'synced_at' => $library->freshness(),
        ];
    }
}
