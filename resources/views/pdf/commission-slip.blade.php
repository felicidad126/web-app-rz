@php
    /** @var \App\Models\CommissionPayout $payout */
    use App\Enums\CommissionRole;
    use App\Support\Money;
    $m = fn (int $v): string => Money::format($v, false);
    $lines = $payout->commissions;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip jasa {{ $payout->practitioner->displayName() }} {{ $payout->periodLabel() }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #18181b; }
        h1 { font-size: 13pt; margin: 0; }
        h2 { font-size: 10.5pt; margin: 12px 0 6px; }
        .muted { color: #52525b; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: normal; color: #52525b; font-size: 7.5pt; border-bottom: 1px solid #a1a1aa; padding: 3px; }
        td { padding: 3px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .strong { font-weight: bold; }
        .neg { color: #b91c1c; }
        .totals td { border: 0; padding: 2px 3px; }
        .status { display: inline-block; padding: 1px 6px; border: 1px solid #a1a1aa; border-radius: 3px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="border: 0; padding: 0">
                <h1>{{ config('app.name') }}</h1>
                <div class="muted">Slip jasa dokter</div>
            </td>
            <td style="border: 0; padding: 0; text-align: right">
                <div class="strong" style="font-size: 11pt">{{ $payout->practitioner->displayName() }}</div>
                <div>Periode {{ $payout->periodLabel() }}</div>
                <div><span class="status">{{ $payout->status->getLabel() }}</span></div>
            </td>
        </tr>
    </table>

    <h2>Rincian tindakan</h2>
    <table>
        <thead><tr><th>Tanggal</th><th>Pasien</th><th>Tindakan</th><th class="num">Bruto</th><th class="num">Diskon</th><th class="num">Bahan</th><th class="num">Lab</th><th class="num">Basis</th><th>Aturan</th><th class="num">Jasa</th></tr></thead>
        <tbody>
            @foreach ($lines as $line)
                <tr class="{{ $line->amount < 0 ? 'neg' : '' }}">
                    <td>{{ $line->service_date->format('d/m/Y') }}</td>
                    <td>{{ $line->invoice->patient->name }}</td>
                    <td>{{ $line->invoiceItem->description }}{{ $line->role === CommissionRole::Assistant ? ' (asisten)' : '' }}{{ $line->isReversal() ? ' — pembalik: '.$line->reason : '' }}</td>
                    <td class="num">{{ $m($line->gross_amount) }}</td>
                    <td class="num">{{ $m($line->discount_amount) }}</td>
                    <td class="num">{{ $m($line->material_cost) }}</td>
                    <td class="num">{{ $m($line->lab_cost) }}</td>
                    <td class="num">{{ $m($line->base_amount) }}</td>
                    <td class="muted">{{ $line->rule_snapshot['summary'] ?? '' }}</td>
                    <td class="num">{{ $m($line->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr>
            <td colspan="3" class="strong">Total {{ $lines->whereNull('reverses_commission_id')->count() }} tindakan</td>
            <td class="num strong">{{ $m((int) $lines->sum('gross_amount')) }}</td>
            <td class="num strong">{{ $m((int) $lines->sum('discount_amount')) }}</td>
            <td class="num strong">{{ $m((int) $lines->sum('material_cost')) }}</td>
            <td class="num strong">{{ $m((int) $lines->sum('lab_cost')) }}</td>
            <td class="num strong">{{ $m((int) $lines->sum('base_amount')) }}</td>
            <td></td>
            <td class="num strong">{{ $m($payout->commission_total) }}</td>
        </tr></tfoot>
    </table>

    @if ($payout->attendances->isNotEmpty())
        <h2>Uang hadir & on call</h2>
        <table>
            @foreach ($payout->attendances as $attendance)
                <tr><td>{{ $attendance->date->format('d/m/Y') }}</td><td>{{ $attendance->type->getLabel() }} · {{ $attendance->branch->name }}</td><td class="num">{{ $m($attendance->amount) }}</td></tr>
            @endforeach
        </table>
    @endif

    <table class="totals" style="margin-top: 12px; width: 50%; margin-left: 50%">
        <tr><td>Jasa tindakan</td><td class="num">{{ $m($payout->commission_total) }}</td></tr>
        <tr><td>Uang hadir / on call</td><td class="num">{{ $m($payout->allowance_total) }}</td></tr>
        @foreach ($payout->adjustments as $adjustment)
            <tr><td>{{ $adjustment->kind->getLabel() }}: {{ $adjustment->description }}</td><td class="num">{{ $m($adjustment->signedAmount()) }}</td></tr>
        @endforeach
        <tr><td class="strong" style="border-top: 1px solid #18181b">Diterima</td><td class="num strong" style="border-top: 1px solid #18181b">{{ Money::format($payout->total) }}</td></tr>
    </table>

    <p class="muted" style="font-size: 7.5pt; margin-top: 14px">
        Basis jasa = harga setelah diskon, dikurangi bahan dan/atau biaya lab sesuai aturan jenis jasa. Aturan & harga disimpan per tindakan saat tagihan terbit.
        PPh 21 dihitung bagian keuangan secara terpisah kecuali tercantum sebagai potongan.
        {{ $payout->finalized_at ? 'Final '.$payout->finalized_at->format('d/m/Y').' oleh '.$payout->finalizer?->name.'.' : 'Draf, angka masih bisa berubah.' }}
        Dicetak {{ now()->format('d/m/Y H:i') }}.
    </p>
</body>
</html>
