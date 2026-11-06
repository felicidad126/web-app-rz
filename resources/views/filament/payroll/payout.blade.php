@php
    /** @var \App\Models\CommissionPayout $payout */
    use App\Enums\CommissionRole;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 12px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7; white-space: nowrap';
    $td = 'padding: 8px 12px; font-size: 13px; border-top: 1px solid #f4f4f5; vertical-align: top';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $m = fn (int $v): string => Money::format($v, false);
    $lines = $payout->commissions;
    $stats = [
        ['Jasa tindakan', $payout->commission_total, $lines->whereNull('reverses_commission_id')->count().' tindakan'],
        ['Uang hadir / on call', $payout->allowance_total, $payout->attendances->count().' hari'],
        ['Tambahan / potongan', $payout->adjustment_total, $payout->adjustments->count().' baris'],
        ['Diterima', $payout->total, 'sebelum PPh 21 bila belum dipotong'],
    ];
@endphp
<div style="display: grid; gap: 16px">
    <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))">
        @foreach ($stats as [$label, $value, $note])
            <div style="{{ $card }}; padding: 14px 16px">
                <div style="font-size: 12px; color: #52525b">{{ $label }}</div>
                <div style="font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; margin-top: 4px; color: {{ $value < 0 ? '#b91c1c' : '#18181b' }}">{{ Money::format($value) }}</div>
                <div style="font-size: 12px; color: #71717a">{{ $note }}</div>
            </div>
        @endforeach
    </div>

    <section style="{{ $card }}">
        <div style="padding: 12px 16px; font-weight: 600; font-size: 14px">Rincian tindakan</div>
        <div style="overflow-x: auto">
            <table style="width: 100%; border-collapse: collapse; min-width: 860px">
                <thead><tr>
                    <th style="{{ $th }}">Tanggal</th><th style="{{ $th }}">Pasien</th><th style="{{ $th }}">Tindakan</th>
                    <th style="{{ $th }}; {{ $num }}">Bruto</th><th style="{{ $th }}; {{ $num }}">Diskon</th><th style="{{ $th }}; {{ $num }}">Bahan</th>
                    <th style="{{ $th }}; {{ $num }}">Lab</th><th style="{{ $th }}; {{ $num }}">Basis</th><th style="{{ $th }}">Aturan</th><th style="{{ $th }}; {{ $num }}">Jasa</th>
                </tr></thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr style="{{ $line->amount < 0 ? 'color: #b91c1c' : '' }}">
                            <td style="{{ $td }}; white-space: nowrap">{{ $line->service_date->format('d/m/Y') }}</td>
                            <td style="{{ $td }}">{{ $line->invoice->patient->name }}<div style="font-size: 12px; color: #71717a">{{ $line->invoice->label() }}</div></td>
                            <td style="{{ $td }}">{{ $line->invoiceItem->description }}{{ $line->invoiceItem->tooth_number ? ' · '.$line->invoiceItem->tooth_number : '' }}
                                @if ($line->role === CommissionRole::Assistant)<div style="font-size: 12px; color: #71717a">Asisten</div>@endif
                                @if ($line->isReversal())<div style="font-size: 12px">Pembalik: {{ $line->reason }}</div>@endif
                            </td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($line->gross_amount) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($line->discount_amount) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($line->material_cost) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($line->lab_cost) }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $m($line->base_amount) }}</td>
                            <td style="{{ $td }}; font-size: 12px; color: #52525b">{{ $line->rule_snapshot['summary'] ?? '' }}</td>
                            <td style="{{ $td }}; {{ $num }}; font-weight: 500">{{ $m($line->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="{{ $td }}; color: #71717a">Belum ada jasa di rekap ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr>
                    <td colspan="3" style="{{ $td }}; font-weight: 600">Total</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m((int) $lines->sum('gross_amount')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m((int) $lines->sum('discount_amount')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m((int) $lines->sum('material_cost')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m((int) $lines->sum('lab_cost')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m((int) $lines->sum('base_amount')) }}</td>
                    <td style="{{ $td }}"></td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m($payout->commission_total) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </section>

    <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: start">
        <section style="{{ $card }}">
            <div style="padding: 12px 16px; font-weight: 600; font-size: 14px">Uang hadir & on call</div>
            <table style="width: 100%; border-collapse: collapse">
                @forelse ($payout->attendances as $attendance)
                    <tr>
                        <td style="{{ $td }}">{{ $attendance->date->format('d/m/Y') }}</td>
                        <td style="{{ $td }}">{{ $attendance->type->getLabel() }} · {{ $attendance->branch->name }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($attendance->amount) }}</td>
                    </tr>
                @empty
                    <tr><td style="{{ $td }}; color: #71717a">Tidak ada.</td></tr>
                @endforelse
            </table>
        </section>
        <section style="{{ $card }}">
            <div style="padding: 12px 16px; font-weight: 600; font-size: 14px">Tambahan & potongan</div>
            <table style="width: 100%; border-collapse: collapse">
                @forelse ($payout->adjustments as $adjustment)
                    <tr>
                        <td style="{{ $td }}">{{ $adjustment->kind->getLabel() }}</td>
                        <td style="{{ $td }}">{{ $adjustment->description }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($adjustment->signedAmount()) }}</td>
                    </tr>
                @empty
                    <tr><td style="{{ $td }}; color: #71717a">Tidak ada. PPh 21 diisi manual oleh bagian keuangan.</td></tr>
                @endforelse
            </table>
        </section>
    </div>
</div>
