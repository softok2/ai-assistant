<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Queries\ChatHistoryQuery;
use App\Queries\AssistantSourcesQuery;
use App\Queries\AssistantSourcesHealthQuery;

/**
 * Pantalla de las fuentes que el asistente consulta: reportes de Pentaho y
 * documentos que sube un administrador. La ruta vive en el grupo `admin`.
 */
final class AssistantSourcesController extends Controller
{
    public function __invoke(
        Request $request,
        AssistantSourcesQuery $sources,
        AssistantSourcesHealthQuery $health,
        ChatHistoryQuery $chatHistory,
    ): Response {
        return Inertia::render('Sources', [
            ...$sources->execute(),
            'health' => $health->execute()->toArray(),
            'chatHistory' => Inertia::deepMerge($chatHistory->execute($request->user())),
        ]);
    }
}
