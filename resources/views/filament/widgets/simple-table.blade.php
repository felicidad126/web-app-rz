{{--
    Tabel widget sederhana. Data: heading, description?, columns: list<array{label, align?, tip?}>,
    rows: list<list<array{text, color?, sub?, bold?, mono?}|string>>, footer?: list<cell>, note?, empty?
--}}
@php
    $card = 'background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 12px; font-size: 12px; font-weight: 500; color: var(--gray-600); text-align: left; border-bottom: 1px solid var(--gray-200); white-space: nowrap';
    $td = 'padding: 9px 12px; font-size: 14px; border-top: 1px solid var(--gray-100); vertical-align: top';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $colors = ['danger' => '#b91c1c', 'warning' => '#b45309', 'success' => '#047857', 'gray' => 'var(--gray-500)'];
    $cell = fn ($c): array => is_array($c) ? $c : ['text' => $c];
    // Di HP tabel digeser ke samping (bukan diperas sampai nama terpotong 3 baris); kolom pertama tetap terlihat.
    $minWidth = count($columns) > 3 ? count($columns) * 110 : 0;
    $sticky = 'position: sticky; left: 0; z-index: 1; background: #fff';
@endphp
<x-filament-widgets::widget>
    <section style="{{ $card }}">
        <div style="padding: 14px 16px">
            <div style="font-weight: 600">{{ $heading }}</div>
            @if (! empty($description))<div style="font-size: 12px; color: var(--gray-600)">{{ $description }}</div>@endif
        </div>
        @if (empty($rows))
            <div style="padding: 12px 16px 16px; font-size: 14px; color: var(--gray-500)">{{ $empty ?? 'Tidak ada data.' }}</div>
        @else
            <div style="overflow-x: auto">
                <table style="width: 100%; border-collapse: collapse; min-width: {{ $minWidth }}px">
                    <thead><tr>
                        @foreach ($columns as $column)
                            <th style="{{ $th }}; {{ ($column['align'] ?? '') === 'end' ? $num : '' }}; {{ $loop->first ? $sticky : '' }}" @if (! empty($column['tip'])) title="{{ $column['tip'] }}" @endif>{{ $column['label'] }}{{ ! empty($column['tip']) ? ' ⓘ' : '' }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $i => $raw)
                                    @php $c = $cell($raw); @endphp
                                    <td style="{{ $td }}; {{ ($columns[$i]['align'] ?? '') === 'end' ? $num : '' }}; {{ $i === 0 ? $sticky.';' : '' }} {{ isset($c['color']) ? 'color: '.$colors[$c['color']].';' : '' }} {{ ! empty($c['bold']) ? 'font-weight: 600;' : '' }}">
                                        @if (! empty($c['mono']))<span class="klinik-mono">{{ $c['text'] }}</span>@else{{ $c['text'] }}@endif
                                        @if (! empty($c['sub']))<div style="font-size: 12px; color: var(--gray-500); font-weight: 400">{{ $c['sub'] }}</div>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (! empty($footer))
                        <tfoot><tr>
                            @foreach ($footer as $i => $raw)
                                @php $c = $cell($raw); @endphp
                                <td style="{{ $td }}; font-weight: 600; {{ ($columns[$i]['align'] ?? '') === 'end' ? $num : '' }}; {{ $i === 0 ? $sticky : '' }}">{{ $c['text'] }}</td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        @endif
        @if (! empty($note))
            <div style="padding: 10px 16px; font-size: 12px; color: var(--gray-600); border-top: 1px solid var(--gray-100)">{{ $note }}</div>
        @endif
    </section>
</x-filament-widgets::widget>
