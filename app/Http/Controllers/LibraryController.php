<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\File;
use Inertia\Inertia;
use Inertia\Response;
use App\Jobs\SyncLock;
use Illuminate\Http\Request;

final class LibraryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Library', [
            'files' => File::active()
                ->orderBy('group')
                ->orderBy('name')
                ->get(['id', 'name', 'group', 'status', 'bytes', 'synced_at']),
            ...$request->user()?->isAdmin() ? $this->managementProps() : [],
        ]);
    }

    /**
     * Lo que solo ve un administrador: los caducados que todavía se pueden
     * purgar, los grupos existentes para la subida manual y si hay una
     * sincronización corriendo.
     *
     * @return array<string, mixed>
     */
    private function managementProps(): array
    {
        return [
            'canManage' => true,
            'expiredFiles' => File::expired()
                ->orderByDesc('expired_at')
                ->get(['id', 'name', 'group', 'status', 'bytes', 'synced_at', 'expired_at', 'assistant_media_id']),
            'groups' => File::active()
                ->distinct()
                ->orderBy('group')
                ->pluck('group'),
            'syncRunning' => SyncLock::isHeld(),
        ];
    }
}
