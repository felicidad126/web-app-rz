@php
    /** @var \App\Models\StockMovement $movement */
    use App\Services\Stock\StockLedger;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7';
    $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid #f4f4f5; vertical-align: top';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $reversal = \App\Models\StockMovement::query()->where('reverses_movement_id', $movement->id)->first();
    $meta = array_filter([
        'Jenis' => $movement->type->getLabel(),
        'Tanggal' => $movement->movement_date->format('d/m/Y'),
        'Supplier' => $movement->supplier?->name,
        'No. faktur' => $movement->supplier_invoice_no,
        'Cabang lawan' => $movement->counterpartBranch?->name,
        'Alasan' => $movement->reason?->getLabel(),
        'Catatan' => $movement->notes,
        'Dibuat oleh' => $movement->creator?->name,
        'Disetujui oleh' => $movement->approver ? $movement->approver->name.' · '.$movement->approved_at?->format('d/m/Y H:i') : null,
        'Alasan ditolak' => $movement->rejected_reason,
        'Membatalkan' => $movement->reverses?->number,
        'Dibatalkan oleh' => $reversal?->number,
    ]);
@endphp
<div style="display: grid; gap: 16px">
    <section style="{{ $card }}">
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 24px; padding: 14px 16px; margin: 0; font-size: 14px">
            @foreach ($meta as $label => $value)
                <div><dt style="color: #52525b; font-size: 12px">{{ $label }}</dt><dd style="margin: 0">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section style="{{ $card }}">
        <div style="overflow-x: auto">
            <table style="width: 100%; border-collapse: collapse; min-width: 640px">
                <thead><tr>
                    <th style="{{ $th }}">Barang</th><th style="{{ $th }}">Batch · ED</th><th style="{{ $th }}; {{ $num }}">Jumlah</th>
                    <th style="{{ $th }}; {{ $num }}">Harga pokok</th><th style="{{ $th }}; {{ $num }}">Nilai</th>
                </tr></thead>
                <tbody>
                    @foreach ($movement->lines as $line)
                        @php $entered = abs((float) $line->entered_qty); $base = (float) $line->qty; @endphp
                        <tr>
                            <td style="{{ $td }}">{{ $line->item->name }}@if ($line->notes)<div style="color: #52525b; font-size: 12px">{{ $line->notes }}</div>@endif</td>
                            <td style="{{ $td }}; font-family: ui-monospace, monospace; font-size: 13px">{{ $line->batch?->label() ?? trim(($line->batch_number ?? '—').($line->expired_at ? ' · ED '.$line->expired_at->format('m/Y') : '')) }}</td>
                            <td style="{{ $td }}; {{ $num }}">
                                {{ ($base < 0 ? '−' : '+').StockLedger::format(abs($base)) }} {{ $line->item->baseUnit->name }}
                                @if ($line->entered_unit_id !== $line->item->base_unit_id)
                                    <div style="color: #52525b; font-size: 12px">{{ StockLedger::format($entered) }} {{ $line->enteredUnit->name }}</div>
                                @endif
                            </td>
                            <td style="{{ $td }}; {{ $num }}">{{ \App\Support\Money::format($line->unit_cost, false) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ \App\Support\Money::format($line->line_cost, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="4" style="{{ $td }}; font-weight: 600">Total</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ \App\Support\Money::format($movement->total_cost, false) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </section>
</div>
