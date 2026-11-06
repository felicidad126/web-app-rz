@php
    use App\Services\Reports\Metrics;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 16px';
    $label = 'font-size: 12px; color: #52525b';
    $big = 'font-size: 18px; font-weight: 600; font-variant-numeric: tabular-nums';
    $badge = ['info' => '#1d4ed8;background:#eff6ff', 'warning' => '#92400e;background:#fffbeb', 'danger' => '#b91c1c;background:#fef2f2', 'success' => '#047857;background:#ecfdf5', 'gray' => '#3f3f46;background:#f4f4f5'];
    $pct = fn (?int $value, ?int $target): string => $target ? Metrics::percent(Metrics::ratio((int) $value, $target)) : '—';
@endphp
<x-filament-panels::page>
    @if ($branchId === null)
        <p>Anda belum ditugaskan ke cabang mana pun.</p>
    @else
        <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: start">
            {{-- Hari ini --}}
            <section style="{{ $card }}; grid-column: 1 / -1">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px">
                    <div style="font-weight: 600; font-size: 16px">Hari ini</div>
                    <div style="font-size: 14px">
                        {{ $today['count'] }} janji · {{ $today['confirmed'] }} dikonfirmasi ·
                        <span title="Perkiraan: jumlah janji × rata-rata produksi per kunjungan 90 hari terakhir ({{ Money::format($average) }})">perkiraan {{ Money::format($today['scheduled_value']) }}</span>
                        @if ($today['target'])
                            ({{ $pct($today['scheduled_value'], $today['target']) }} target {{ Money::format($today['target']) }})
                        @endif
                    </div>
                </div>
                <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-top: 12px">
                    @forelse ($today['groups'] as $group => $rows)
                        <div style="border: 1px solid #f4f4f5; border-radius: 10px; padding: 10px 12px">
                            <div style="{{ $label }}; margin-bottom: 6px">{{ $group }}</div>
                            @foreach ($rows as $row)
                                @php $a = $row['appointment']; @endphp
                                <div style="display: grid; grid-template-columns: 48px 1fr; gap: 8px; padding: 6px 0; border-top: 1px solid #f4f4f5">
                                    <div style="font-variant-numeric: tabular-nums; font-weight: 600">{{ $a->start_at->format('H:i') }}</div>
                                    <div>
                                        <div>{{ $a->patient->name }}</div>
                                        @if ($a->reason)<div style="font-size: 12px; color: #52525b">{{ $a->reason }}</div>@endif
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px">
                                            @foreach ($row['flags'] as $flag)
                                                <span style="font-size: 11px; padding: 1px 8px; border-radius: 999px; color: {{ $badge[$flag['color']] }}">{{ $flag['label'] }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p style="color: #71717a">Tidak ada janji hari ini.</p>
                    @endforelse
                </div>
                <div style="margin-top: 12px">
                    <div style="{{ $label }}">Slot kosong hari ini</div>
                    <div style="font-size: 14px">
                        {{ collect($today['slots'])->map(fn ($s) => $s['start'].'–'.$s['end'].' '.($s['chair'] ? $s['chair'].' · ' : '').$s['practitioner'])->implode(' · ') ?: 'Tidak ada slot kosong ≥ 30 menit' }}
                    </div>
                </div>
            </section>

            {{-- Kemarin --}}
            <section style="{{ $card }}">
                <div style="font-weight: 600; font-size: 16px">Kemarin <span style="{{ $label }}">{{ $yesterday['date']->locale('id')->translatedFormat('D, j M') }}</span></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px">
                    <div title="{{ \App\Enums\Kpi::Production->definition() }}">
                        <div style="{{ $label }}">Produksi</div>
                        <div style="{{ $big }}">{{ Money::format($yesterday['production']) }}</div>
                        <div style="{{ $label }}">{{ $yesterday['target'] ? 'Target harian '.Money::format($yesterday['target']).' · '.$pct($yesterday['production'], $yesterday['target']) : 'Belum ada target' }}</div>
                    </div>
                    <div title="{{ \App\Enums\Kpi::CashIn->definition() }}">
                        <div style="{{ $label }}">Kas masuk</div>
                        <div style="{{ $big }}">{{ Money::format($yesterday['cash']) }}</div>
                    </div>
                    <div>
                        <div style="{{ $label }}">Pasien</div>
                        <div style="{{ $big }}">{{ $yesterday['visits'] }} · {{ $yesterday['new'] }} baru</div>
                    </div>
                    <div title="{{ \App\Enums\Kpi::NoShow->definition() }}">
                        <div style="{{ $label }}">Tidak datang</div>
                        <div style="{{ $big }}">{{ $yesterday['no_shows'] }} · {{ Money::format($yesterday['no_show_value']) }}</div>
                    </div>
                </div>
                <div style="margin-top: 12px">
                    <div style="{{ $label }}">Pulang tanpa janji berikutnya</div>
                    @forelse ($yesterday['left_without_next'] as $row)
                        <div style="font-size: 14px; padding: 4px 0; border-top: 1px solid #f4f4f5">{{ $row['name'] }} <span style="color: {{ $row['late'] ? '#b91c1c' : '#52525b' }}; font-size: 13px">· {{ $row['note'] }}{{ $row['late'] ? ' · terlambat' : '' }}</span></div>
                    @empty
                        <div style="font-size: 14px; color: #71717a">Tidak ada.</div>
                    @endforelse
                </div>
            </section>

            {{-- Besok --}}
            <section style="{{ $card }}">
                <div style="font-weight: 600; font-size: 16px">Besok <span style="{{ $label }}">{{ $tomorrow['date']->locale('id')->translatedFormat('D, j M') }}</span></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px">
                    <div><div style="{{ $label }}">Janji</div><div style="{{ $big }}">{{ $tomorrow['count'] }} · {{ $tomorrow['confirmed'] }} dikonfirmasi</div></div>
                    <div><div style="{{ $label }}">Slot kosong</div><div style="{{ $big }}">{{ count($tomorrow['slots']) }}</div></div>
                </div>
                <div style="font-size: 13px; margin-top: 6px">
                    @foreach ($tomorrow['slots'] as $slot)
                        <div>{{ $slot['start'] }}–{{ $slot['end'] }} <span style="color: #52525b">{{ $slot['chair'] ? $slot['chair'].' · ' : '' }}{{ $slot['practitioner'] }}</span></div>
                    @endforeach
                </div>
                <div style="margin-top: 12px">
                    <div style="{{ $label }}">Hubungi untuk mengisi slot</div>
                    @forelse ($tomorrow['contacts'] as $row)
                        <div style="font-size: 14px; padding: 4px 0; border-top: 1px solid #f4f4f5">
                            {{ $row['patient']->name }} <span style="color: #52525b; font-size: 13px">· {{ $row['reason'] }}</span>
                            <div style="font-size: 12px; color: #71717a">{{ $row['patient']->phone ?? 'No. HP belum ada' }}</div>
                        </div>
                    @empty
                        <div style="font-size: 14px; color: #71717a">Tidak ada.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</x-filament-panels::page>
