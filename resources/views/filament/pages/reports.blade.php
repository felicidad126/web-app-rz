@php
    use App\Reports\ReportRequest;
    $report = $this->report();
    $card = 'background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 12px; font-size: 12px; font-weight: 500; color: var(--gray-600); text-align: left; border-bottom: 1px solid var(--gray-200); white-space: nowrap';
    $td = 'padding: 8px 12px; font-size: 13px; border-top: 1px solid var(--gray-100)';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    // Kolom pertama (nama kelompok) tetap terlihat saat tabel digulir ke samping di HP.
    $sticky = 'position: sticky; left: 0; z-index: 1; background: #fff; min-width: 140px';
@endphp
<x-filament-panels::page>
    <div style="{{ $card }}; padding: 16px; overflow: visible">{{ $this->form }}</div>

    @if ($report)
        @php $columns = $report->columns(); $rows = $report->rows(); $totals = $report->totals(); @endphp
        <section style="{{ $card }}">
            <div style="padding: 14px 16px">
                <div style="font-weight: 600">{{ $report::label() }}</div>
                <div style="font-size: 12px; color: var(--gray-600)">{{ $report::description() }}</div>
            </div>
            <div style="overflow-x: auto">
                <table style="width: 100%; border-collapse: collapse">
                    <thead><tr>
                        @foreach ($columns as $column)
                            <th style="{{ $th }}; {{ $column['type'] !== 'text' ? $num : '' }}; {{ $loop->first ? $sticky : '' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($columns as $i => $column)
                                    <td style="{{ $td }}; {{ $column['type'] !== 'text' ? $num : '' }}; {{ $loop->first ? $sticky : '' }}; {{ in_array($column['type'], ['money', 'number'], true) && is_numeric($row[$i] ?? null) && $row[$i] < 0 ? 'color: #b91c1c' : '' }}">{{ ReportRequest::format($row[$i] ?? null, $column['type']) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" style="{{ $td }}; color: var(--gray-500)">Tidak ada data untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($totals && $rows !== [])
                        <tfoot><tr>
                            @foreach ($columns as $i => $column)
                                <td style="{{ $td }}; font-weight: 600; {{ $column['type'] !== 'text' ? $num : '' }}; {{ $loop->first ? $sticky : '' }}">{{ $i === 0 ? $totals[0] : ($totals[$i] === null ? '' : ReportRequest::format($totals[$i], $column['type'])) }}</td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </section>
    @endif
</x-filament-panels::page>
