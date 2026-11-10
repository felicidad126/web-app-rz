@php
    /** @var \App\Models\StockOpname $opname */
    $card = 'background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: var(--gray-600); text-align: left; border-bottom: 1px solid var(--gray-200)';
    $td = 'padding: 9px 16px; font-size: 14px; border-top: 1px solid var(--gray-100)';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $fmt = fn (?float $v): string => $v === null ? '—' : \App\Services\Stock\StockLedger::format($v);
    $lines = $opname->lines->filter(fn ($l) => $l->counted_qty !== null || (float) $l->system_qty != 0);
    $changed = $lines->filter(fn ($l) => $l->difference() !== null && abs($l->difference()) >= 0.0005);
    $value = $changed->sum(fn ($l) => (int) round($l->difference() * $l->unit_cost));
@endphp
{{-- Di HP kolom batch disembunyikan (.klinik-hide-sm) dan batch tampil di bawah nama barang. --}}
<style>
    .stock-lines .stock-batch-sm { color: var(--gray-600); font-size: 12px }
    @media (min-width: 48rem) { .stock-lines .stock-batch-sm { display: none } }
    @media (max-width: 47.99rem) { .stock-lines th, .stock-lines td { padding-inline: 10px !important } }
</style>
<div style="display: grid; gap: 16px">
    <section style="{{ $card }}; padding: 14px 16px; display: flex; flex-wrap: wrap; gap: 12px 32px; font-size: 14px">
        <div><div style="color: var(--gray-600); font-size: 12px">Baris dihitung</div>{{ $lines->count() }}</div>
        <div><div style="color: var(--gray-600); font-size: 12px">Baris berselisih</div>{{ $changed->count() }}</div>
        <div><div style="color: var(--gray-600); font-size: 12px">Nilai selisih (harga pokok)</div><span style="font-variant-numeric: tabular-nums; color: {{ $value < 0 ? '#b42318' : 'inherit' }}">{{ \App\Support\Money::format($value) }}</span></div>
        @if ($opname->approver)
            <div><div style="color: var(--gray-600); font-size: 12px">Disetujui</div>{{ $opname->approver->name }} · {{ $opname->approved_at?->format('d/m/Y H:i') }}</div>
        @endif
    </section>
    <section style="{{ $card }}">
        <div style="overflow-x: auto">
            <table class="stock-lines" style="width: 100%; border-collapse: collapse">
                <thead><tr>
                    <th style="{{ $th }}">Barang</th><th class="klinik-hide-sm" style="{{ $th }}">Batch · ED</th>
                    <th style="{{ $th }}; {{ $num }}">Sistem</th><th style="{{ $th }}; {{ $num }}">Fisik</th><th style="{{ $th }}; {{ $num }}">Selisih</th>
                </tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        @php
                            $diff = $line->difference();
                            $batch = ($line->batch_number ?: '—').($line->expired_at ? ' · ED '.$line->expired_at->format('m/Y') : '');
                        @endphp
                        <tr>
                            <td style="{{ $td }}">
                                {{ $line->item->name }}
                                <div class="stock-batch-sm klinik-mono">{{ $batch }}</div>
                                @if ($line->notes)<div style="color: var(--gray-600); font-size: 12px">{{ $line->notes }}</div>@endif
                            </td>
                            <td class="klinik-hide-sm klinik-mono" style="{{ $td }}">{{ $batch }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $fmt((float) $line->system_qty) }} {{ $line->item->baseUnit->name }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $fmt($line->counted_qty === null ? null : (float) $line->counted_qty) }}</td>
                            <td style="{{ $td }}; {{ $num }}; font-weight: 500; color: {{ ($diff ?? 0) < 0 ? '#b42318' : (($diff ?? 0) > 0 ? 'inherit' : 'var(--gray-600)') }}">{{ $diff === null ? '—' : (($diff > 0 ? '+' : ($diff < 0 ? '−' : '')).$fmt(abs($diff))) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
