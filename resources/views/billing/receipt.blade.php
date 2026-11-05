@php
    /**
     * Struk thermal. Lebar kertas 80 mm (area cetak ±72 mm) atau 58 mm (±48 mm).
     * Dicetak lewat window.print(); di tablet Android pakai aplikasi bridge (mis. RawBT).
     */
    $paper = $width === 58 ? 58 : 80;
    $printable = $paper === 58 ? 48 : 72;
    $money = fn (?int $v): string => $v === null ? '' : ($v < 0 ? '-' : '').number_format(abs($v), 0, ',', '.');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Struk · {{ $meta[0][1] ?? '' }}</title>
    <style>
        @page { size: {{ $paper }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #e4e4e7; color: #000; }
        body { font: 12px/1.35 ui-monospace, "DejaVu Sans Mono", Menlo, Consolas, monospace; }
        .toolbar { display: flex; gap: 8px; justify-content: center; align-items: center; padding: 12px; font-family: system-ui, sans-serif; font-size: 14px; flex-wrap: wrap; }
        .toolbar a, .toolbar button { border: 1px solid #d4d4d8; background: #fff; border-radius: 8px; padding: 8px 14px; font: inherit; color: #18181b; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #0d6e63; border-color: #0d6e63; color: #fff; font-weight: 600; }
        .toolbar .active { background: #e6f2f0; border-color: #0d6e63; }
        .paper { width: {{ $printable }}mm; margin: 0 auto 24px; background: #fff; padding: 4mm {{ $paper === 58 ? '2mm' : '3mm' }}; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        .center { text-align: center; }
        .strong { font-weight: 700; }
        .rule { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .row span:last-child { text-align: right; white-space: nowrap; }
        .row span:first-child { overflow-wrap: anywhere; }
        .indent { padding-left: 2ch; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .paper { box-shadow: none; margin: 0; width: {{ $printable }}mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Cetak</button>
        <a href="?w=80" class="{{ $paper === 80 ? 'active' : '' }}">80 mm</a>
        <a href="?w=58" class="{{ $paper === 58 ? 'active' : '' }}">58 mm</a>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>

    <main class="paper">
        <div class="center strong">{{ mb_strtoupper(config('app.name')) }}</div>
        <div class="center">{{ $branch->name }}</div>
        @if ($branch->address)<div class="center">{{ $branch->address }}</div>@endif
        @if ($branch->phone)<div class="center">{{ $branch->phone }}</div>@endif
        <hr class="rule">
        <div class="center strong">{{ $title }}</div>
        @foreach ($meta as [$label, $value])
            <div class="row"><span>{{ $label }}</span><span>{{ $value }}</span></div>
        @endforeach

        @foreach ($sections as $section)
            <hr class="rule">
            @foreach ($section as $line)
                <div class="row {{ ! empty($line['indent']) ? 'indent' : '' }} {{ ! empty($line['strong']) ? 'strong' : '' }}">
                    <span>{{ $line['label'] }}</span><span>{{ $money($line['amount']) }}</span>
                </div>
            @endforeach
        @endforeach

        @if ($next)
            <hr class="rule">
            <div class="strong">Janji berikutnya</div>
            <div>{{ $next->start_at->translatedFormat('D, d M Y H:i') }}</div>
            @if ($next->reason)<div>{{ $next->reason }}</div>@endif
            <div>{{ $next->practitioner->user->name }}{{ $next->chair ? ' · '.$next->chair->name : '' }}</div>
        @endif

        <hr class="rule">
        <div class="center">Terima kasih.</div>
        <div class="center">Simpan struk ini sebagai bukti bayar.</div>
    </main>
</body>
</html>
