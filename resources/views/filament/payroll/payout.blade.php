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
    // Rekap bisa ratusan baris: tampilkan 25 dulu, sisanya lewat tombol.
    $visibleLines = 25;
    $stats = [
        ['Jasa tindakan', $payout->commission_total, $lines->whereNull('reverses_commission_id')->count().' tindakan'],
        ['Uang hadir / on call', $payout->allowance_total, $payout->attendances->count().' hari'],
        ['Tambahan / potongan', $payout->adjustment_total, $payout->adjustments->count().' baris'],
        ['Diterima', $payout->total, 'sebelum PPh 21 bila belum dipotong'],
    ];
@endphp
<div style="display: grid; gap: 16px">
    <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr))">
        @foreach ($stats as [$label, $value, $note])
            <div style="{{ $card }}; padding: 14px 16px">
                <div style="font-size: 12px; color: #52525b">{{ $label }}</div>
                <div style="font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; margin-top: 4px; color: #18181b">{{ Money::format($value) }}</div>
                <div style="font-size: 12px; color: #71717a">{{ $note }}</div>
            </div>
        @endforeach
    </div>

    <section style="{{ $card }}" x-data="{ all: false }">
        <div style="display: flex; justify-content: space-between; gap: 12px; padding: 12px 16px; font-weight: 600; font-size: 14px">
            <span>Rincian tindakan</span><span style="font-weight: 400; color: #52525b; font-size: 13px">{{ $lines->count() }} baris</span>
        </div>
        <div style="overflow-x: auto">
            {{-- Di HP hanya Pasien (+ No. tagihan), Tindakan, Jasa; kolom lain disembunyikan. --}}
            <table style="width: 100%; border-collapse: collapse">
                <thead><tr>
                    <th style="{{ $th }}" class="klinik-hide-sm">Tanggal</th><th style="{{ $th }}">Pasien</th><th style="{{ $th }}">Tindakan</th>
                    <th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Bruto</th><th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Diskon</th><th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Bahan</th>
                    <th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Lab</th><th style="{{ $th }}; {{ $num }}" class="klinik-hide-sm">Basis</th><th style="{{ $th }}" class="klinik-hide-sm">Aturan</th><th style="{{ $th }}; {{ $num }}">Jasa</th>
                </tr></thead>
                @forelse ($lines->chunk($visibleLines) as $chunk)
                    <tbody @if (! $loop->first) x-show="all" x-cloak @endif>
                        @foreach ($chunk as $line)
                            <tr>
                                <td style="{{ $td }}; white-space: nowrap" class="klinik-hide-sm">{{ $line->service_date->format('d/m/Y') }}</td>
                                <td style="{{ $td }}">{{ $line->invoice->patient->name }}<div class="klinik-mono" style="font-size: 12px; color: #71717a; white-space: nowrap">{{ $line->invoice->label() }}</div></td>
                                <td style="{{ $td }}">{{ $line->invoiceItem->description }}@if ($line->invoiceItem->tooth_number) · <span class="klinik-mono">{{ $line->invoiceItem->tooth_number }}</span>@endif
                                    @if ($line->role === CommissionRole::Assistant)<div style="font-size: 12px; color: #71717a">Asisten</div>@endif
                                    @if ($line->isReversal())<div style="font-size: 12px; color: #71717a">Pembalik: {{ $line->reason }}</div>@endif
                                </td>
                                <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ $m($line->gross_amount) }}</td>
                                <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ $m($line->discount_amount) }}</td>
                                <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ $m($line->material_cost) }}</td>
                                <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ $m($line->lab_cost) }}</td>
                                <td style="{{ $td }}; {{ $num }}" class="klinik-hide-sm">{{ $m($line->base_amount) }}</td>
                                <td style="{{ $td }}; font-size: 12px; color: #52525b; white-space: nowrap" class="klinik-hide-sm">{{ $line->rule_snapshot['summary'] ?? '' }}</td>
                                <td style="{{ $td }}; {{ $num }}; font-weight: 500">{{ $m($line->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody><tr><td colspan="10" style="{{ $td }}; color: #71717a">Belum ada jasa di rekap ini.</td></tr></tbody>
                @endforelse
                <tfoot><tr>
                    <td style="{{ $td }}" class="klinik-hide-sm"></td>
                    <td colspan="2" style="{{ $td }}; font-weight: 600">Total</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600" class="klinik-hide-sm">{{ $m((int) $lines->sum('gross_amount')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600" class="klinik-hide-sm">{{ $m((int) $lines->sum('discount_amount')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600" class="klinik-hide-sm">{{ $m((int) $lines->sum('material_cost')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600" class="klinik-hide-sm">{{ $m((int) $lines->sum('lab_cost')) }}</td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600" class="klinik-hide-sm">{{ $m((int) $lines->sum('base_amount')) }}</td>
                    <td style="{{ $td }}" class="klinik-hide-sm"></td>
                    <td style="{{ $td }}; {{ $num }}; font-weight: 600">{{ $m($payout->commission_total) }}</td>
                </tr></tfoot>
            </table>
        </div>
        @if ($lines->count() > $visibleLines)
            <div style="padding: 10px 16px; border-top: 1px solid #f4f4f5" x-show="! all">
                <x-filament::button color="gray" size="sm" x-on:click="all = true">Tampilkan semua ({{ $lines->count() }} baris)</x-filament::button>
            </div>
        @endif
    </section>

    <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr)); align-items: start">
        <section style="{{ $card }}">
            <div style="padding: 12px 16px; font-weight: 600; font-size: 14px">Uang hadir & on call</div>
            <table style="width: 100%; border-collapse: collapse">
                @forelse ($payout->attendances as $attendance)
                    <tr>
                        <td style="{{ $td }}; white-space: nowrap">{{ $attendance->date->format('d/m/Y') }}</td>
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
