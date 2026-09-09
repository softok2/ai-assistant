<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Historial que pinta la barra lateral: fijados primero y luego por actividad
 * reciente. Lo comparten todas las pantallas que montan el layout del
 * asistente.
 */
final class ChatHistoryQuery
{
    private const PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, Chat>|null
     */
    public function execute(?User $user): ?LengthAwarePaginator
    {
        if (! $user instanceof User) {
            return null;
        }

        return $user->chats()
            ->orderByDesc('pinned_at')
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE);
    }
}
