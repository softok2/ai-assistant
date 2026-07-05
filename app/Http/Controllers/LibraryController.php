<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\File;
use Inertia\Inertia;
use Inertia\Response;

final class LibraryController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Library', [
            'files' => File::query()
                ->whereNull('expired_at')
                ->orderBy('group')
                ->orderBy('name')
                ->get(['id', 'name', 'group', 'status', 'bytes', 'synced_at']),
        ]);
    }
}
