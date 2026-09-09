<?php

declare(strict_types=1);

namespace App\Actions\Chats;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Busca entre los chats del usuario por título. Sin término devuelve los más
 * recientes, que es lo que el diálogo muestra al abrirse.
 */
final class SearchChatsAction
{
    private const LIMIT = 20;

    /**
     * @return Collection<int, array{id: string, title: string, updated_at: ?string}>
     */
    public function execute(User $user, ?string $term): Collection
    {
        $term = trim((string) $term);

        return $user->chats()
            ->when($term !== '', fn ($query) => $query->where('title', 'like', '%'.$term.'%'))
            ->orderByDesc('pinned_at')
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (Chat $chat): array => [
                'id' => $chat->id,
                'title' => $chat->title,
                'updated_at' => $chat->updated_at?->toISOString(),
            ]);
    }
}
