<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Inertia\Middleware;
use App\Enums\ModelName;
use Tighten\Ziggy\Ziggy;
use Illuminate\Http\Request;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'is_admin' => (bool) $request->user()?->isAdmin(),
                'club' => $request->user()?->getAttributes()['club_name'] ?? null,
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => $this->flash($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'availableModels' => ModelName::getAvailableModels(),
        ];
    }

    /**
     * Mensajes de una acción para el toast de la página siguiente. Sin esto
     * las páginas leían `flash` y nunca llegaba nada.
     *
     * @return array<string, string|null>
     */
    private function flash(Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : null;

        return [
            'success' => $session?->get('success'),
            'warning' => $session?->get('warning'),
            'error' => $session?->get('error'),
        ];
    }
}
