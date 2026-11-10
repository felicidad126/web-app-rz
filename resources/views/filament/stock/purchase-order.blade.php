@php
    /** @var \App\Models\PurchaseOrder $order */
    use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
    use App\Services\Stock\StockLedger;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden';
    $head = 'padding: 12px 16px; font-size: 14px; font-weight: 600; border-bottom: 1px solid var(--gray-200)';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: var(--gray-600); text-align: left; border-bottom: 1px solid var(--gray-200)';
    $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid var(--gray-100); vertical-align: baseline';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $muted = 'color: var(--gray-600); font-size: 12px';
    $canSeeMovements = StockMovementResource::canViewAny();
    $meta = array_filter([
        'Supplier' => $order->supplier?->name ?? 'Tanpa supplier',
        'Tanggal pesan' => $order->order_date->format('d/m/Y'),
        'Perkiraan datang' => $order->expected_at?->format('d/m/Y'),
        'Total' => Money::format($order->total),
        'Dibayar' => $order->total > 0 ? Money::format($order->paid_amount).($order->unpaidAmount() > 0 && $order->status !== \App\Enums\PurchaseOrderStatus::Cancelled ? ' · kurang '.Money::format($order->unpaidAmount()) : '') : null,
        'Dibuat oleh' => $order->creator?->name,
        'Catatan' => $order->notes,
        $order->status === \App\Enums\PurchaseOrderStatus::Cancelled ? 'Alasan batal' : 'Ditutup' => $order->cancel_reason
            ? $order->cancel_reason.($order->cancelled_at ? ' · '.$order->cancelled_at->format('d/m/Y H:i') : '')
            : null,
    ]);
@endphp
<style>
    .po-table .po-sm { color: var(--gray-600); font-size: 12px }
    @media (min-width: 48rem) { .po-table .po-sm { display: none } }
    @media (max-width: 47.99rem) { .po-table th, .po-table td { padding-inline: 10px !important } }
</style>
<div style="display: grid; gap: 16px">
    <section style="{{ $card }}">
        <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px 24px; padding: 14px 16px; margin: 0; font-size: 14px">
            @foreach ($meta as $label => $value)
                <div><dt style="{{ $muted }}">{{ $label }}</dt><dd style="margin: 0">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>

    <section style="{{ $card }}">
        <div style="{{ $head }}">Barang</div>
        <div style="overflow-x: auto">
            <table class="po-table" style="width: 100%; border-collapse: collapse">
                <thead><tr>
                    <th style="{{ $th }}">Barang</th>
                    <th style="{{ $th }}; {{ $num }}">Dipesan</th>
                    <th style="{{ $th }}; {{ $num }}">Diterima</th>
                    <th class="klinik-hide-sm" style="{{ $th }}; {{ $num }}">Harga</th>
                    <th style="{{ $th }}; {{ $num }}">Subtotal</th>
                </tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        @php $remaining = $line->remainingQuantity(); @endphp
                        <tr>
                            <td style="{{ $td }}">
                                {{ $line->item->name }}
                                @if ($line->purchaseRequest)
                                    <div style="{{ $muted }}">Permintaan <span class="klinik-mono">{{ $line->purchaseRequest->number }}</span>{{ $line->purchaseRequest->requester ? ' · '.$line->purchaseRequest->requester->name : '' }}</div>
                                @endif
                                <div class="po-sm">@ {{ Money::format($line->unit_price, false) }}</div>
                            </td>
                            <td style="{{ $td }}; {{ $num }}">{{ StockLedger::format((float) $line->quantity) }} {{ $line->unit->name }}</td>
                            <td style="{{ $td }}; {{ $num }}">
                                {{ StockLedger::format((float) $line->received_quantity) }}
                                @if ($remaining > 0 && $order->status->isOpen())
                                    <div style="{{ $muted }}; color: #b54708">sisa {{ StockLedger::format($remaining) }}</div>
                                @endif
                            </td>
                            <td class="klinik-hide-sm" style="{{ $td }}; {{ $num }}">{{ Money::format($line->unit_price, false) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ Money::format($line->line_total, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr>
                    <td style="{{ $td }}; font-weight: 600" colspan="2">Total</td>
                    <td style="{{ $td }}"></td>
                    <td class="klinik-hide-sm" style="{{ $td }}"></td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ Money::format($order->total, false) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </section>

    <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr))">
        <section style="{{ $card }}">
            <div style="{{ $head }}">Penerimaan</div>
            @forelse ($order->receipts as $receipt)
                <div style="{{ $td }}; display: flex; justify-content: space-between; gap: 12px">
                    <div>
                        @if ($canSeeMovements)
                            <a href="{{ StockMovementResource::getUrl('view', ['record' => $receipt]) }}" class="klinik-mono" style="color: var(--primary-700); text-decoration: underline">{{ $receipt->number }}</a>
                        @else
                            <span class="klinik-mono">{{ $receipt->number }}</span>
                        @endif
                        <div style="{{ $muted }}">{{ $receipt->movement_date->format('d/m/Y') }}{{ $receipt->supplier_invoice_no ? ' · faktur '.$receipt->supplier_invoice_no : '' }}{{ $receipt->creator ? ' · '.$receipt->creator->name : '' }}</div>
                    </div>
                    <div style="{{ $num }}">
                        {{ $receipt->lines->count() }} baris
                        @if ($receipt->type === \App\Enums\StockMovementType::Reversal)
                            <div style="{{ $muted }}">pembatalan</div>
                        @endif
                    </div>
                </div>
            @empty
                <p style="padding: 14px 16px; margin: 0; {{ $muted }}; font-size: 14px">Belum ada barang yang diterima.</p>
            @endforelse
        </section>

        <section style="{{ $card }}">
            <div style="{{ $head }}">Pembayaran</div>
            @forelse ($order->cashEntries as $entry)
                <div style="{{ $td }}; display: flex; justify-content: space-between; gap: 12px">
                    <div>
                        {{ $entry->type->getLabel() }}
                        <div style="{{ $muted }}">{{ $entry->entry_date->format('d/m/Y') }}{{ $entry->creator ? ' · '.$entry->creator->name : '' }}</div>
                    </div>
                    <div style="{{ $num }}">{{ Money::format(abs($entry->amount), false) }}</div>
                </div>
            @empty
                <p style="padding: 14px 16px; margin: 0; {{ $muted }}; font-size: 14px">{{ $order->total > 0 ? 'Belum dibayar dari kas pembelian.' : 'Tanpa nilai.' }}</p>
            @endforelse
        </section>
    </div>
</div>
