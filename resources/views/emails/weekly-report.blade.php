<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
    <div style="max-width:560px;margin:0 auto;padding:32px 16px;">
        <div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="background:linear-gradient(135deg,#111827,#4c1d95);padding:28px 32px;color:#fff;">
                <p style="margin:0;text-transform:uppercase;letter-spacing:2px;font-size:11px;opacity:.7;">Reporte Ejecutivo</p>
                <h1 style="margin:6px 0 0;font-size:22px;">{{ $report->club->getName() }}</h1>
            </div>
            <div style="padding:28px 32px;">
                <p style="margin:0 0 12px;">Estimado Director,</p>
                <p style="margin:0 0 12px;line-height:1.6;">
                    Adjunto encontrará el <strong>reporte ejecutivo</strong> del periodo
                    <strong>{{ $report->periodLabel }}</strong>, elaborado por el asistente de IA con base en
                    la información más reciente de cada módulo del club.
                </p>
                <p style="margin:0 0 4px;line-height:1.6;">El documento incluye:</p>
                <ul style="margin:0 0 16px;padding-left:20px;line-height:1.6;">
                    <li>Resumen ejecutivo global</li>
                    <li>Análisis por módulo con indicadores y gráficas</li>
                    <li>Recomendaciones estratégicas</li>
                </ul>
                <p style="margin:0;color:#6b7280;font-size:12px;">Generado automáticamente — {{ $report->generatedAt }}</p>
            </div>
        </div>
    </div>
</body>
</html>
