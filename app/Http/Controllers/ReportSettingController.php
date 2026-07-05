<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reports\SendWeeklyReportAction;
use App\Enums\ReportFrequency;
use App\Http\Requests\UpdateReportSettingRequest;
use App\Models\ReportSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class ReportSettingController extends Controller
{
    public function edit(): Response
    {
        $club = auth()->user()->club_name;
        $setting = ReportSetting::forClub($club);

        return Inertia::render('settings/Report', [
            'setting' => [
                'enabled' => $setting->enabled ?? false,
                'recipients' => $setting->recipients ?? [],
                'frequency' => $setting->frequency?->value ?? ReportFrequency::Weekly->value,
                'day_of_week' => $setting->day_of_week ?? 1,
                'hour' => $setting->hour ?? 8,
                'last_sent_at' => $setting->last_sent_at?->toDateTimeString(),
            ],
            'frequencies' => collect(ReportFrequency::cases())
                ->map(fn (ReportFrequency $f) => ['value' => $f->value, 'label' => $f->label()])
                ->all(),
        ]);
    }

    public function update(UpdateReportSettingRequest $request): RedirectResponse
    {
        $setting = ReportSetting::forClub(auth()->user()->club_name);
        $setting->fill($request->validated())->save();

        return back()->with('success', 'Configuración guardada.');
    }

    public function test(SendWeeklyReportAction $action): RedirectResponse
    {
        $setting = ReportSetting::forClub(auth()->user()->club_name);

        if (! $setting->exists) {
            return back()->with('error', 'Primero guarda la configuración con al menos un destinatario.');
        }

        try {
            $count = $action->execute($setting);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo enviar: '.$exception->getMessage());
        }

        return back()->with('success', "Reporte de prueba enviado a {$count} destinatario(s).");
    }
}
