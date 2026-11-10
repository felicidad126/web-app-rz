@php
    /** @var \App\Models\StockMovement $movement */
    use App\Services\Stock\StockLedger;
    $card = 'background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: var(--gray-600); text-align: left; border-bottom: 1px solid var(--gray-200)';
    $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid var(--gray-100); vertical-align: baseline';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $reversal = \App\Models\StockMovement::query()->where('reverses_movement_id', $movement->id)->first();
    $meta = array_filter([
        'Jenis' => $movement->type->getLabel(),
        'Tanggal' => $movement->movement_date->format('d/m/Y'),
        'Supplier' => $movement->supplier?->name,
        'No. faktur' => $movement->supplier_invoice_no,
        'Dari pesanan' => $movement->source instanceof \App\Models\PurchaseOrder ? $movement->source->number : null,
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
{{-- Di HP kolom batch & harga pokok disembunyikan (.klinik-hide-sm); batch tampil di bawah nama barang. --}}
<style>
    .stock-lines .stock-batch-sm { color: var(--gray-600); font-size: 12px }
    @media (min-width: 48rem) { .stock-lines .stock-batch-sm { display: none } }
    @media (max-width: 47.99rem) { .stock-lines th, .stock-lines td { padding-inline: 10px !important } }
</style>
<div style="display: grid; gap: 16px">
    <section style="{{ $card }}">
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 24px; padding: 14px 16px; margin: 0; font-size: 14px">
            @foreach ($meta as $label => $value)
                <div><dt style="color: var(--gray-600); font-size: 12px">{{ $label }}</dt><dd style="margin: 0">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section style="{{ $card }}">
        <div style="overflow-x: auto">
            <table class="stock-lines" style="width: 100%; border-collapse: collapse">
                <thead><tr>
                    <th style="{{ $th }}">Barang</th><th class="klinik-hide-sm" style="{{ $th }}">Batch · ED</th><th style="{{ $th }}; {{ $num }}">Jumlah</th>
                    <th class="klinik-hide-sm" style="{{ $th }}; {{ $num }}">Harga pokok</th><th style="{{ $th }}; {{ $num }}">Nilai</th>
                </tr></thead>
                <tbody>
                    @foreach ($movement->lines as $line)
                        @php
                            $entered = abs((float) $line->entered_qty);
                            $base = (float) $line->qty;
                            $batch = $line->batch?->label() ?? trim(($line->batch_number ?? '—').($line->expired_at ? ' · ED '.$line->expired_at->format('m/Y') : ''));
                        @endphp
                        <tr>
                            <td style="{{ $td }}">
                                {{ $line->item->name }}
                                <div class="stock-batch-sm klinik-mono">{{ $batch }}</div>
                                @if ($line->notes)<div style="color: var(--gray-600); font-size: 12px">{{ $line->notes }}</div>@endif
                            </td>
                            <td class="klinik-hide-sm klinik-mono" style="{{ $td }}">{{ $batch }}</td>
                            <td style="{{ $td }}; {{ $num }}">
                                {{ ($base < 0 ? '−' : '+').StockLedger::format(abs($base)) }} {{ $line->item->baseUnit->name }}
                                @if ($line->entered_unit_id !== $line->item->base_unit_id)
                                    <div style="color: var(--gray-600); font-size: 12px">{{ StockLedger::format($entered) }} {{ $line->enteredUnit->name }}</div>
                                @endif
                            </td>
                            <td class="klinik-hide-sm" style="{{ $td }}; {{ $num }}">{{ \App\Support\Money::format($line->unit_cost, false) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ \App\Support\Money::format($line->line_cost, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr>
                    <td style="{{ $td }}; font-weight: 600">Total</td>
                    <td class="klinik-hide-sm" style="{{ $td }}"></td>
                    <td style="{{ $td }}"></td>
                    <td class="klinik-hide-sm" style="{{ $td }}"></td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ \App\Support\Money::format($movement->total_cost, false) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </section>
</div>
