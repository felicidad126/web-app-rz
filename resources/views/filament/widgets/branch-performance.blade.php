@php
    use App\Enums\Kpi;
    use App\Services\Reports\Metrics;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 12px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7; white-space: nowrap';
    $td = 'padding: 10px 12px; font-size: 14px; border-top: 1px solid #f4f4f5';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $colors = ['danger' => '#b91c1c', 'warning' => '#b45309', 'success' => '#047857', 'gray' => '#52525b'];
    $att = fn (?float $r): string => $colors[Kpi::attainmentColor($r)];
    $tip = fn (Kpi $k): string => $k->getLabel().': '.$k->definition();
    $m = fn (?int $v): string => $v === null ? '—' : Money::format($v, false);
@endphp
<x-filament-widgets::widget>
    <section style="{{ $card }}">
        <div style="display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; justify-content: space-between; padding: 14px 16px">
            <div>
                <div style="font-weight: 600">Per cabang</div>
                <div style="font-size: 12px; color: #52525b">{{ $periodLabel }} · nominal dalam rupiah</div>
            </div>
            <div style="display: flex; gap: 6px">
                @foreach ($options as $key => $label)
                    <button type="button" wire:click="$set('period', '{{ $key }}')"
                        style="padding: 4px 10px; border-radius: 8px; font-size: 13px; border: 1px solid {{ $period === $key ? '#0d6e63' : '#e4e4e7' }}; background: {{ $period === $key ? '#0d6e63' : '#fff' }}; color: {{ $period === $key ? '#fff' : '#18181b' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div style="overflow-x: auto">
            <table style="width: 100%; border-collapse: collapse; min-width: 900px">
                <thead><tr>
                    <th style="{{ $th }}">Cabang</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::Production) }}">Produksi ⓘ</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::ProductionVsTarget) }}">Target s.d. kemarin ⓘ</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::ProductionVsTarget) }}">Capaian</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::CashIn) }}">Kas masuk ⓘ</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::CollectionRatio) }}">Kas / produksi ⓘ</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::NewPatients) }}">Pasien baru ⓘ</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::NoShow) }}">Tidak datang ⓘ</th>
                    <th style="{{ $th }}; {{ $num }}" title="{{ $tip(Kpi::UnscheduledTreatment) }}">Rencana belum dijadwalkan ⓘ</th>
                </tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="{{ $td }}">{{ $row['name'] }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($row['production']) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($row['target']) }}</td>
                            <td style="{{ $td }}; {{ $num }}; font-weight: 600; color: {{ $att($row['attainment']) }}">{{ Metrics::percent($row['attainment']) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($row['cash']) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ Metrics::percent($row['cash_ratio']) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $row['new_patients'] }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $row['no_shows'] }} dari {{ $row['appointments'] }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($row['unscheduled']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if (count($rows) > 1)
                    <tfoot><tr style="font-weight: 600">
                        <td style="{{ $td }}">Semua cabang</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($total['production']) }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($total['target']) }}</td>
                        <td style="{{ $td }}; {{ $num }}; color: {{ $att($total['attainment']) }}">{{ Metrics::percent($total['attainment']) }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($total['cash']) }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ Metrics::percent($total['cash_ratio']) }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $total['new_patients'] }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $total['no_shows'] }} dari {{ $total['appointments'] }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($total['unscheduled']) }}</td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
        <div style="padding: 10px 16px; font-size: 12px; color: #52525b; border-top: 1px solid #f4f4f5">
            Produksi = tindakan selesai setelah diskon. Kas masuk = pembayaran diterima, termasuk cicilan paket. Capaian: merah &lt; 85%, kuning 85–95%, hijau &gt; 95%. Arahkan kursor ke judul kolom untuk definisi.
            @php $daily = collect($rows)->whereNotNull('daily_target'); @endphp
            @if ($daily->isNotEmpty())
                <br>Target harian: {{ $daily->map(fn ($r) => $r['name'].' '.Money::format($r['daily_target']))->implode(' · ') }}
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
