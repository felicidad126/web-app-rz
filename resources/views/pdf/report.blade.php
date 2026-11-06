@php
    use App\Reports\ReportRequest;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report::label() }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #18181b; }
        h1 { font-size: 12pt; margin: 0; }
        .muted { color: #52525b; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; font-weight: normal; color: #52525b; font-size: 7.5pt; border-bottom: 1px solid #a1a1aa; padding: 3px; }
        td { padding: 3px; border-bottom: 1px solid #e4e4e7; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 1px solid #18181b; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} · {{ $report::label() }}</h1>
    <div class="muted">{{ $report::usesPeriod() ? 'Periode '.$report->filters()->periodLabel().' · ' : 'Per '.now()->format('d/m/Y').' · ' }}{{ $branches }}</div>
    <div class="muted" style="font-size: 7pt">{{ $report::description() }}</div>
    <table>
        <thead><tr>@foreach ($columns as $c)<th class="{{ $c['type'] !== 'text' ? 'num' : '' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($columns as $i => $c)<td class="{{ $c['type'] !== 'text' ? 'num' : '' }}">{{ ReportRequest::format($row[$i] ?? null, $c['type']) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
        @if ($totals && $rows !== [])
            <tfoot><tr>@foreach ($columns as $i => $c)<td class="{{ $c['type'] !== 'text' ? 'num' : '' }}">{{ $i === 0 ? $totals[0] : ($totals[$i] === null ? '' : ReportRequest::format($totals[$i], $c['type'])) }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
    <p class="muted" style="font-size: 7pt; margin-top: 10px">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()?->name }}.</p>
</body>
</html>
