@php
    /** @var \App\Models\StockMovement $movement */
    use App\Enums\StockMovementType;
    use App\Services\Stock\StockLedger;
    use App\Support\Money;

    $title = match ($movement->type) {
        StockMovementType::Receipt => 'BERITA ACARA PENERIMAAN BARANG',
        StockMovementType::Disposal => 'BERITA ACARA PEMUSNAHAN BARANG',
        StockMovementType::Adjustment => 'BERITA ACARA KOREKSI STOK',
        StockMovementType::TransferOut, StockMovementType::TransferIn => 'BERITA ACARA MUTASI ANTAR CABANG',
        default => 'BUKTI MUTASI STOK',
    };
    $meta = array_filter([
        'Nomor' => $movement->number,
        'Jenis' => $movement->type->getLabel(),
        'Tanggal' => $movement->movement_date->format('d/m/Y'),
        'Status' => $movement->status->getLabel(),
        'Supplier' => $movement->supplier?->name,
        'No. faktur' => $movement->supplier_invoice_no,
        $movement->type === StockMovementType::TransferIn ? 'Dari cabang' : 'Ke cabang' => $movement->counterpartBranch?->name,
        'Membatalkan' => $movement->reverses?->number,
        'Alasan' => $movement->reason?->getLabel(),
        'Catatan' => $movement->notes,
    ]);
    $needsApprover = $movement->type->needsApproval();
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $movement->number }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #18181b; }
        h1 { font-size: 13pt; margin: 0; }
        h2 { font-size: 11pt; margin: 14px 0 8px; text-align: center; letter-spacing: 0.5px; }
        .muted { color: #52525b; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: normal; color: #52525b; font-size: 8.5pt; border-bottom: 1px solid #a1a1aa; padding: 4px; }
        td { padding: 4px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .meta td { border: 0; padding: 2px 4px; }
        .strong { font-weight: bold; }
        .sign td { border: 0; text-align: center; padding-top: 4px; }
        .line { border-top: 1px solid #18181b; margin: 52px 24px 0; padding-top: 3px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="border: 0; padding: 0">
                <h1>{{ config('app.name') }}</h1>
                <div>{{ $movement->branch->name }}</div>
                <div class="muted">{{ $movement->branch->address }}</div>
            </td>
        </tr>
    </table>

    <h2>{{ $title }}</h2>

    <table class="meta" style="margin-bottom: 10px">
        @foreach ($meta as $label => $value)
            <tr><td class="muted" style="width: 26%">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <table>
        <thead>
            <tr><th>No</th><th>Barang</th><th>Batch</th><th>ED</th><th class="num">Jumlah</th><th class="num">Harga pokok</th><th class="num">Nilai</th></tr>
        </thead>
        <tbody>
            @foreach ($movement->lines as $i => $line)
                @php $base = (float) $line->qty; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->item->name }}@if ($line->notes)<div class="muted" style="font-size: 8pt">{{ $line->notes }}</div>@endif</td>
                    <td>{{ $line->batch?->batch_number ?? $line->batch_number ?? '—' }}</td>
                    <td>{{ ($line->batch?->expired_at ?? $line->expired_at)?->format('m/Y') ?? '—' }}</td>
                    <td class="num">
                        {{ ($base < 0 ? '−' : '+').StockLedger::format(abs($base)) }} {{ $line->item->baseUnit->name }}
                        @if ($line->entered_unit_id !== $line->item->base_unit_id)
                            <div class="muted" style="font-size: 8pt">{{ StockLedger::format(abs((float) $line->entered_qty)) }} {{ $line->enteredUnit->name }}</div>
                        @endif
                    </td>
                    <td class="num">{{ Money::format($line->unit_cost, false) }}</td>
                    <td class="num">{{ Money::format($line->line_cost, false) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="strong">Total nilai</td><td class="num strong">{{ Money::format($movement->total_cost) }}</td></tr>
        </tfoot>
    </table>

    @if ($movement->rejected_reason)
        <p><span class="strong">Ditolak:</span> {{ $movement->rejected_reason }}</p>
    @endif

    <table class="sign" style="margin-top: 28px">
        <tr>
            <td style="width: 50%">
                <div>Dibuat oleh</div>
                <div class="line">{{ $movement->creator?->name ?? '…' }}</div>
                <div class="muted" style="font-size: 8pt">{{ ($movement->submitted_at ?? $movement->created_at)?->format('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 50%">
                <div>{{ $needsApprover ? 'Disetujui oleh' : 'Mengetahui' }}</div>
                <div class="line">{{ $movement->approver?->name ?? '…' }}</div>
                <div class="muted" style="font-size: 8pt">{{ $movement->approved_at?->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <p class="muted" style="font-size: 8pt; margin-top: 18px">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()?->name }}. Jumlah dalam satuan dasar; harga pokok per satuan dasar.</p>
</body>
</html>
