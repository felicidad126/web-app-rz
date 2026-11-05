@php
    /** @var \App\Models\Invoice $invoice */
    $money = fn (?int $v): string => $v === null ? '' : ($v < 0 ? '-' : '').number_format(abs($v), 0, ',', '.');
    $payments = $invoice->payments->where('status', '!=', \App\Enums\PaymentStatus::Void);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #18181b; }
        h1 { font-size: 13pt; margin: 0; }
        .muted { color: #52525b; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: normal; color: #52525b; font-size: 8.5pt; border-bottom: 1px solid #a1a1aa; padding: 4px 4px; }
        td { padding: 4px 4px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals td { border: 0; padding: 2px 4px; }
        .strong { font-weight: bold; }
    </style>
</head>
<body>
    <table style="margin-bottom: 10px">
        <tr>
            <td style="border: 0; padding: 0">
                <h1>{{ config('app.name') }}</h1>
                <div>{{ $invoice->branch->name }}</div>
                <div class="muted">{{ $invoice->branch->address }}{{ $invoice->branch->phone ? ' · '.$invoice->branch->phone : '' }}</div>
            </td>
            <td style="border: 0; padding: 0; text-align: right">
                <div class="strong" style="font-size: 12pt">NOTA</div>
                <div>{{ $invoice->invoice_number }}</div>
                <div class="muted">{{ $invoice->issued_at?->format('d/m/Y H:i') }}</div>
                <div class="strong">{{ $invoice->status->getLabel() }}</div>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 10px">
        <tr>
            <td style="border: 0; padding: 0; width: 55%">
                <div class="muted">Pasien</div>
                <div class="strong">{{ $invoice->patient->name }}</div>
                <div>No. RM {{ $invoice->patient->rm_number }}</div>
            </td>
            <td style="border: 0; padding: 0">
                @if ($invoice->visit)
                    <div class="muted">Kunjungan</div>
                    <div>{{ $invoice->visit->visit_number }} · {{ $invoice->visit->visit_date->format('d/m/Y') }}</div>
                    <div>{{ $invoice->visit->practitioner->user->name }}</div>
                @endif
                @if ($invoice->payer)
                    <div>Penjamin: {{ $invoice->payer->name }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table>
        <thead><tr><th>Item</th><th>Gigi</th><th class="num">Jml</th><th class="num">Harga</th><th class="num">Diskon</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ trim($item->tooth_number.' '.$item->surfaces) ?: '–' }}</td>
                    <td class="num">{{ $item->qtyLabel() }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $item->item_discount ? $money(-$item->item_discount) : '–' }}</td>
                    <td class="num">{{ $money($item->lineSubtotal()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 6px; width: 55%; margin-left: 45%">
        <tr><td>Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
        @if ($invoice->invoice_discount)<tr><td>Diskon tagihan</td><td class="num">{{ $money(-$invoice->invoice_discount) }}</td></tr>@endif
        @if ($invoice->tax_total)<tr><td>Pajak</td><td class="num">{{ $money($invoice->tax_total) }}</td></tr>@endif
        @if ($invoice->rounding)<tr><td>Pembulatan</td><td class="num">{{ $money($invoice->rounding) }}</td></tr>@endif
        <tr class="strong"><td>Total</td><td class="num">{{ $money($invoice->total) }}</td></tr>
        @if ($invoice->payer_portion)
            <tr><td>Ditanggung penjamin</td><td class="num">{{ $money(-$invoice->payer_portion) }}</td></tr>
            <tr class="strong"><td>Dibayar pasien</td><td class="num">{{ $money($invoice->patient_portion) }}</td></tr>
        @endif
        @foreach ($payments as $payment)
            <tr><td class="muted">{{ $payment->transaction->paid_at->format('d/m/Y') }} · {{ $payment->method->getLabel() }}</td><td class="num">{{ $money(-$payment->amount) }}</td></tr>
        @endforeach
        <tr class="strong"><td>Sisa tagihan</td><td class="num">{{ $money($invoice->balance()) }}</td></tr>
    </table>

    <table style="margin-top: 28px">
        <tr>
            <td style="border: 0; width: 60%" class="muted">Nota ini sah tanpa tanda tangan basah. Tagihan tidak bisa dihapus; koreksi dilakukan dengan void dan persetujuan.</td>
            <td style="border: 0; text-align: center">Kasir<br><br><br>{{ $invoice->issuer?->name ?? '…………………' }}</td>
        </tr>
    </table>
</body>
</html>
