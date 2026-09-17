<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Queries\ChatHistoryQuery;
use App\Queries\AssistantSourcesQuery;
use App\Queries\AssistantSourcesHealthQuery;
use App\Actions\Files\ResolveSourcesClubAction;

/**
 * Pantalla de las fuentes que el asistente consulta: reportes de Pentaho y
 * documentos que sube un administrador. La ruta vive en el grupo `admin`.
 */
final class AssistantSourcesController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveSourcesClubAction $resolveClub,
        AssistantSourcesQuery $sources,
        AssistantSourcesHealthQuery $health,
        ChatHistoryQuery $chatHistory,
    ): Response {
        $club = $resolveClub->execute($request->user(), $request->query('club'));

        return Inertia::render('Sources', [
            ...$sources->execute($club),
            'club' => $club->value,
            'clubs' => $resolveClub->options(),
            'clubLocked' => $resolveClub->isLocked($request->user()),
            'health' => $health->execute($club)->toArray(),
            'chatHistory' => Inertia::deepMerge($chatHistory->execute($request->user())),
        ]);
    }
}
