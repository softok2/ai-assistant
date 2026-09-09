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
        .head { border-bottom: 1px solid #ededed; padding-bottom: 14px; margin-bottom: 22px; }
        .head h1 { font-size: 22px; margin: 0 0 4px; font-weight: 600; }
        .head .meta { font-size: 11px; color: #6b7280; }
        .kpis { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .kpi {
            border: 1px solid #ededed; border-radius: 10px; padding: 12px 14px;
            min-width: 150px; flex: 1;
        }
        .kpi .label { font-size: 11px; color: #6b7280; }
        .kpi .value { font-size: 22px; font-weight: 600; margin-top: 2px; }
        .kpi .delta { font-size: 11px; margin-top: 2px; }
        .kpi .delta.good { color: #047857; }
        .kpi .delta.bad { color: #b91c1c; }
        .kpi .delta.neutral { color: #6b7280; }
        .content h4 { font-size: 14px; margin: 18px 0 6px; }
        .content h5 { font-size: 13px; margin: 14px 0 4px; }
        .content p { margin: 0 0 10px; }
        .content ul, .content ol { margin: 0 0 10px; padding-left: 20px; }
        .content li { margin-bottom: 4px; }
        .chart { margin: 16px 0; padding: 14px; border: 1px solid #ededed; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $title }}</h1>
        <div class="meta">Respuesta del asistente · {{ $generatedAt }}</div>
    </div>

    @if (count($kpis))
        <div class="kpis">
            @foreach ($kpis as $kpi)
                <div class="kpi">
                    <div class="label">{{ $kpi['label'] }}</div>
                    <div class="value">{{ $kpi['value'] }}</div>
                    @if ($kpi['delta'])
                        <div class="delta {{ $kpi['tone'] }}">{{ $kpi['delta'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="content">
        {!! $html !!}
    </div>

    @foreach ($charts as $chart)
        <div class="chart">{!! $chart !!}</div>
    @endforeach
</body>
</html>
