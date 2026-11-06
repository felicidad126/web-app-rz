@php
    /** @var \App\Models\Payslip $payslip */
    use App\Enums\SalaryComponentKind;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7; white-space: nowrap';
    $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid #f4f4f5';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $totals = [
        ['Pendapatan', $payslip->earning_total, false],
        ['Potongan', -$payslip->deduction_total, false],
        ['Diterima', $payslip->net_total, true],
    ];
@endphp
{{-- Slip final/dibayar: baris hanya dibaca (rupiah tanpa "Rp"); ubah lewat "Buka kembali". --}}
<section style="{{ $card }}">
    <div style="padding: 12px 16px; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e4e4e7">Baris slip</div>
    <table style="width: 100%; border-collapse: collapse">
        <thead><tr><th style="{{ $th }}">Komponen</th><th style="{{ $th }}">Jenis</th><th style="{{ $th }}; {{ $num }}">Nominal</th></tr></thead>
        <tbody>
            @forelse ($payslip->lines as $line)
                <tr>
                    <td style="{{ $td }}">{{ $line->name }}</td>
                    <td style="{{ $td }}; color: #52525b">{{ $line->kind->getLabel() }}</td>
                    <td style="{{ $td }}; {{ $num }}">{{ Money::format($line->kind === SalaryComponentKind::Deduction ? -$line->amount : $line->amount, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="{{ $td }}; color: #71717a">Belum ada baris.</td></tr>
            @endforelse
        </tbody>
    </table>
    <dl style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0 16px; margin: 0; padding: 8px 16px 12px; border-top: 1px solid #e4e4e7; font-size: 14px">
        @foreach ($totals as [$label, $value, $strong])
            <dt style="padding: 3px 0; {{ $strong ? 'font-weight: 600' : 'color: #52525b' }}">{{ $label }}</dt>
            <dd style="margin: 0; padding: 3px 0; {{ $num }}; {{ $strong ? 'font-weight: 600' : '' }}">{{ Money::format($value, false) }}</dd>
        @endforeach
    </dl>
    @if ($payslip->notes)
        <div style="padding: 10px 16px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #3f3f46; white-space: pre-line">Catatan: {{ $payslip->notes }}</div>
    @endif
</section>
