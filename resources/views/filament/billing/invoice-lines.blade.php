@php
    /** @var \App\Models\Invoice $invoice */
    use App\Support\Money;
    $th = 'padding: 8px 12px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7; white-space: nowrap';
    $td = 'padding: 10px 12px; border-bottom: 1px solid #f4f4f5; font-size: 14px; vertical-align: top';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $rows = [['Subtotal', $invoice->subtotal]];
    if ($invoice->invoice_discount) { $rows[] = ['Diskon tagihan', -$invoice->invoice_discount]; }
    if ($invoice->tax_total) { $rows[] = ['Pajak', $invoice->tax_total]; }
    if ($invoice->rounding) { $rows[] = ['Pembulatan', $invoice->rounding]; }
    $rows[] = ['Total', $invoice->total, true];
    if ($invoice->payer_portion) { $rows[] = ['Ditanggung '.($invoice->payer?->name ?? 'penjamin'), -$invoice->payer_portion]; $rows[] = ['Dibayar pasien', $invoice->patient_portion, true]; }
    if (! $invoice->isDraft()) { $rows[] = ['Sudah dibayar', $invoice->paid_total]; $rows[] = ['Sisa', $invoice->balance(), true]; }
@endphp
<div style="margin: -8px -4px 0">
    {{-- Kolom Gigi & Diskon disembunyikan di HP; total ada di luar tabel supaya tidak ikut tergulir ke samping. --}}
    <div style="overflow-x: auto">
        <table style="width: 100%; border-collapse: collapse">
            <thead>
                <tr>
                    <th style="{{ $th }}">Item</th>
                    <th style="{{ $th }}" class="klinik-hide-sm">Gigi</th>
                    <th style="{{ $th }}; {{ $num }}">Jml</th>
                    <th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Harga</th>
                    <th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Diskon</th>
                    <th style="{{ $th }}; {{ $num }}">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td style="{{ $td }}">{{ $item->description }}</td>
                        <td style="{{ $td }}" class="klinik-hide-sm klinik-mono">{{ trim($item->tooth_number.' '.$item->surfaces) ?: '–' }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $item->qtyLabel() }}</td>
                        <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ Money::format($item->unit_price, false) }}</td>
                        <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ $item->item_discount ? Money::format(-$item->item_discount, false) : '–' }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ Money::format($item->lineSubtotal(), false) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <dl style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0 16px; margin: 0; padding: 6px 12px 4px; font-size: 14px">
        @foreach ($rows as $row)
            <dt style="padding: 4px 0; {{ ! empty($row[2]) ? 'font-weight: 600' : 'color: #52525b' }}">{{ $row[0] }}</dt>
            <dd style="margin: 0; padding: 4px 0; {{ $num }}; {{ ! empty($row[2]) ? 'font-weight: 600' : '' }}">{{ Money::format($row[1], false) }}</dd>
        @endforeach
    </dl>
    @if ($invoice->status === \App\Enums\InvoiceStatus::Void || $invoice->void_requested_at)
        <p style="margin: 12px; padding: 10px 12px; border-radius: 8px; background: #fef3f2; color: #b42318; font-size: 13px">
            {{ $invoice->status === \App\Enums\InvoiceStatus::Void ? 'Void' : 'Diajukan void' }}: {{ $invoice->void_reason }}
            @if ($invoice->voidRequester) · diajukan {{ $invoice->voidRequester->name }} {{ $invoice->void_requested_at?->format('d/m H:i') }} @endif
            @if ($invoice->voider) · disetujui {{ $invoice->voider->name }} {{ $invoice->voided_at?->format('d/m H:i') }} @endif
        </p>
    @endif
</div>
