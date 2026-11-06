@php
    /** @var \App\Models\Payslip $payslip */
    use App\Enums\SalaryComponentKind;
    use App\Support\Money;
    $m = fn (int $v): string => Money::format($v, false);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip gaji {{ $payslip->user->name }} {{ $payslip->periodLabel() }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #18181b; }
        h1 { font-size: 12pt; margin: 0; }
        .muted { color: #52525b; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; border-bottom: 1px solid #e4e4e7; }
        .num { text-align: right; white-space: nowrap; }
        .strong { font-weight: bold; }
        .head td { border: 0; padding: 0; }
        .section { margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td><h1>{{ config('app.name') }}</h1><div class="muted">Slip gaji</div></td>
            <td style="text-align: right"><div class="strong">{{ $payslip->user->name }}</div><div>Periode {{ $payslip->periodLabel() }}</div><div class="muted">{{ $payslip->status->getLabel() }}</div></td>
        </tr>
    </table>

    @foreach ([[SalaryComponentKind::Earning, 'Pendapatan'], [SalaryComponentKind::Deduction, 'Potongan']] as [$kind, $title])
        @php $lines = $payslip->lines->where('kind', $kind); @endphp
        <div class="section">{{ $title }}</div>
        <table>
            @forelse ($lines as $line)
                <tr><td>{{ $line->name }}</td><td class="num">{{ $m($line->amount) }}</td></tr>
            @empty
                <tr><td class="muted">—</td><td></td></tr>
            @endforelse
            <tr><td class="strong">Total {{ strtolower($title) }}</td><td class="num strong">{{ $m($kind === SalaryComponentKind::Earning ? $payslip->earning_total : $payslip->deduction_total) }}</td></tr>
        </table>
    @endforeach

    <table style="margin-top: 10px">
        <tr><td class="strong" style="border-top: 1px solid #18181b; font-size: 10.5pt">Diterima</td><td class="num strong" style="border-top: 1px solid #18181b; font-size: 10.5pt">{{ Money::format($payslip->net_total) }}</td></tr>
    </table>

    @if ($payslip->notes)
        <p class="muted">{{ $payslip->notes }}</p>
    @endif

    <table class="head" style="margin-top: 28px; text-align: center">
        <tr>
            <td style="width: 50%">Diterima oleh<div style="border-top: 1px solid #18181b; margin: 40px 20px 0; padding-top: 3px">{{ $payslip->user->name }}</div></td>
            <td style="width: 50%">Disetujui oleh<div style="border-top: 1px solid #18181b; margin: 40px 20px 0; padding-top: 3px">&nbsp;</div></td>
        </tr>
    </table>
    <p class="muted" style="font-size: 7pt; margin-top: 12px">PPh 21 diisi manual. Dicetak {{ now()->format('d/m/Y H:i') }}.</p>
</body>
</html>
