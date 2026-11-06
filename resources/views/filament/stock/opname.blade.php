@php
    /** @var \App\Models\StockOpname $opname */
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7';
    $td = 'padding: 9px 16px; font-size: 14px; border-top: 1px solid #f4f4f5';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $fmt = fn (?float $v): string => $v === null ? '—' : \App\Services\Stock\StockLedger::format($v);
    $lines = $opname->lines->filter(fn ($l) => $l->counted_qty !== null || (float) $l->system_qty != 0);
    $changed = $lines->filter(fn ($l) => $l->difference() !== null && abs($l->difference()) >= 0.0005);
    $value = $changed->sum(fn ($l) => (int) round($l->difference() * $l->unit_cost));
@endphp
<div style="display: grid; gap: 16px">
    <section style="{{ $card }}; padding: 14px 16px; display: flex; flex-wrap: wrap; gap: 12px 32px; font-size: 14px">
        <div><div style="color: #52525b; font-size: 12px">Baris dihitung</div>{{ $lines->count() }}</div>
        <div><div style="color: #52525b; font-size: 12px">Baris berselisih</div>{{ $changed->count() }}</div>
        <div><div style="color: #52525b; font-size: 12px">Nilai selisih (harga pokok)</div><span style="font-variant-numeric: tabular-nums; color: {{ $value < 0 ? '#b42318' : 'inherit' }}">{{ \App\Support\Money::format($value) }}</span></div>
        @if ($opname->approver)
            <div><div style="color: #52525b; font-size: 12px">Disetujui</div>{{ $opname->approver->name }} · {{ $opname->approved_at?->format('d/m/Y H:i') }}</div>
        @endif
    </section>
    <section style="{{ $card }}">
        <div style="overflow-x: auto">
            <table style="width: 100%; border-collapse: collapse; min-width: 640px">
                <thead><tr>
                    <th style="{{ $th }}">Barang</th><th style="{{ $th }}">Batch · ED</th>
                    <th style="{{ $th }}; {{ $num }}">Sistem</th><th style="{{ $th }}; {{ $num }}">Fisik</th><th style="{{ $th }}; {{ $num }}">Selisih</th>
                </tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        @php $diff = $line->difference(); @endphp
                        <tr style="{{ $diff !== null && abs($diff) >= 0.0005 ? 'background: #fffaeb' : '' }}">
                            <td style="{{ $td }}">{{ $line->item->name }}@if ($line->notes)<div style="color: #52525b; font-size: 12px">{{ $line->notes }}</div>@endif</td>
                            <td style="{{ $td }}; font-family: ui-monospace, monospace; font-size: 13px">{{ ($line->batch_number ?: '—').($line->expired_at ? ' · ED '.$line->expired_at->format('m/Y') : '') }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $fmt((float) $line->system_qty) }} {{ $line->item->baseUnit->name }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $fmt($line->counted_qty === null ? null : (float) $line->counted_qty) }}</td>
                            <td style="{{ $td }}; {{ $num }}; color: {{ ($diff ?? 0) < 0 ? '#b42318' : (($diff ?? 0) > 0 ? '#0d6e63' : '#52525b') }}">{{ $diff === null ? '—' : (($diff > 0 ? '+' : ($diff < 0 ? '−' : '')).$fmt(abs($diff))) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
