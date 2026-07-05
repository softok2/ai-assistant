<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1a1a1a;
            margin: 0;
            font-size: 13px;
            line-height: 1.55;
        }
        .cover {
            height: 100vh;
            padding: 64px 56px;
            background: linear-gradient(135deg, #111827 0%, #1f2937 60%, #4c1d95 100%);
            color: #fff;
            page-break-after: always;
            position: relative;
        }
        .cover .eyebrow { text-transform: uppercase; letter-spacing: 3px; font-size: 12px; opacity: .7; }
        .cover h1 { font-size: 40px; margin: 16px 0 8px; font-weight: 700; line-height: 1.1; }
        .cover .club { font-size: 20px; opacity: .9; margin-bottom: 48px; }
        .cover .period { position: absolute; bottom: 64px; left: 56px; font-size: 15px; }
        .cover .period strong { display: block; font-size: 22px; margin-top: 4px; }
        .cover .badge {
            position: absolute; bottom: 64px; right: 56px; font-size: 11px; opacity: .6; text-align: right;
        }
        .page { padding: 40px 48px; }
        .section { page-break-inside: avoid; margin-bottom: 34px; }
        .section-head {
            border-left: 4px solid #4c1d95;
            padding-left: 12px;
            margin-bottom: 14px;
        }
        .section-head h2 { font-size: 22px; margin: 0; font-weight: 700; }
        h1.exec { font-size: 24px; margin: 0 0 12px; }
        .exec-summary {
            background: #f5f3ff;
            border: 1px solid #ede9fe;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 40px;
        }
        .content h4 {
            font-size: 14px; text-transform: uppercase; letter-spacing: .5px;
            color: #4c1d95; margin: 18px 0 6px;
        }
        .content p { margin: 0 0 10px; }
        .content ul { margin: 0 0 10px; padding-left: 20px; }
        .content li { margin-bottom: 4px; }
        .chart { margin: 16px 0; padding: 14px; border: 1px solid #eee; border-radius: 10px; background: #fff; }
        .divider { border: none; border-top: 1px solid #eee; margin: 28px 0; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="eyebrow">Reporte Ejecutivo</div>
        <h1>Resumen del Club</h1>
        <div class="club">{{ $report->club->getName() }}</div>
        <div class="period">
            Periodo
            <strong>{{ $report->periodLabel }}</strong>
        </div>
        <div class="badge">Generado por IA<br>{{ $report->generatedAt }}</div>
    </div>

    <div class="page">
        @if ($report->executiveSummaryHtml)
            <h1 class="exec">Resumen Ejecutivo</h1>
            <div class="exec-summary content">
                {!! $report->executiveSummaryHtml !!}
            </div>
        @endif

        @foreach ($report->sections as $section)
            <div class="section">
                <div class="section-head">
                    <h2>{{ $section['title'] }}</h2>
                </div>
                <div class="content">
                    {!! $section['html'] !!}
                </div>
                @foreach ($section['charts'] as $chart)
                    <div class="chart">{!! $chart !!}</div>
                @endforeach
            </div>
            @if (! $loop->last)
                <hr class="divider">
            @endif
        @endforeach
    </div>
</body>
</html>
