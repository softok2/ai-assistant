<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Throwable;
use App\Models\File;
use App\Jobs\RemoveExpiredDocs;
use Illuminate\Http\JsonResponse;
use App\Dtos\ManualSourceFileData;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\StoreSourceFileRequest;
use App\Actions\Files\ReindexSourceFileAction;
use App\Actions\Files\StoreManualSourceFileAction;
use App\Http\Requests\ReconcileSourceFilesRequest;
use App\Actions\Files\ReconcileAssistantFilesAction;
use App\Actions\Files\StartAssistantFilesSyncAction;

/**
 * Gestión de las fuentes del asistente: sincronizar, subir, reindexar, borrar
 * y reconciliar contra OpenAI. Solo admin: las rutas viven en el grupo con el
 * middleware `admin`.
 */
final class AssistantSourcesManagementController extends Controller
{
    public function sync(StartAssistantFilesSyncAction $startSync): RedirectResponse
    {
        if (! $startSync->execute()) {
            return back()->with('warning', 'Ya hay una sincronización en curso');
        }

        return back()->with('success', 'Sincronización iniciada en segundo plano.');
    }

    public function reconcileReport(ReconcileSourceFilesRequest $request, ReconcileAssistantFilesAction $reconcile): JsonResponse
    {
        return response()->json($reconcile->report($request->includeUntagged())->toArray());
    }

    public function reconcile(ReconcileSourceFilesRequest $request, ReconcileAssistantFilesAction $reconcile): RedirectResponse
    {
        try {
            $report = $reconcile->report($request->includeUntagged());

            if ($report->isClean()) {
                return back()->with('success', 'Nada que reconciliar');
            }

            $deleted = $reconcile->apply($report);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo reconciliar con OpenAI: '.$e->getMessage());
        }

        return back()->with('success', "Se borraron {$deleted} archivos de OpenAI");
    }

    public function store(StoreSourceFileRequest $request, StoreManualSourceFileAction $storeFile): RedirectResponse
    {
        $file = $storeFile->execute(ManualSourceFileData::fromValidated($request->validated()));

        return back()->with('success', "Documento {$file->name} recibido; se está indexando.");
    }

    public function reindex(File $file, ReindexSourceFileAction $reindex): RedirectResponse
    {
        try {
            if (! $reindex->execute($file)) {
                return back()->with('error', "El documento {$file->name} ya no está en el disco; no se puede reindexar.");
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "No se pudo reindexar {$file->name}: ".$e->getMessage());
        }

        return back()->with('success', "Documento {$file->name} puesto en cola para reindexar.");
    }

    public function destroy(File $file): RedirectResponse
    {
        $name = $file->name;

        $file->remove();

        if ($file->exists) {
            return back()->with('error', "No se pudo eliminar {$name}; se reintentará en la próxima limpieza.");
        }

        return back()->with('success', "Documento {$name} eliminado.");
    }

    public function purgeExpired(): RedirectResponse
    {
        RemoveExpiredDocs::dispatch();

        return back()->with('success', 'Purga de documentos caducados iniciada.');
    }
}
