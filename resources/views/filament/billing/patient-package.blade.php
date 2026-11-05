@php
    /** @var \App\Models\PatientPackage $package */
    use App\Enums\InstallmentStatus;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $head = 'display: flex; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-weight: 600; font-size: 14px';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7';
    $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid #f4f4f5';
    $num = 'text-align: right; font-variant-numeric: tabular-nums';
    $installments = $package->installments->where('sequence', '>', 0);
    $paid = $package->paidAmount();
    $stats = [
        ['Harga paket', Money::format($package->price)],
        ['DP', Money::format($package->down_payment)],
        ['Cicilan', $installments->count() ? $installments->count().' × '.Money::format((int) $installments->first()->amount) : '—'],
        ['Sudah dibayar', Money::format($paid)],
        ['Sisa', Money::format($package->remainingAmount())],
    ];
@endphp
<div style="display: grid; gap: 16px; grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); align-items: start" class="fi-package-grid">
    <style>@media (max-width: 1400px) { .fi-package-grid { grid-template-columns: 1fr !important; } }</style>
    <div style="display: grid; gap: 16px; min-width: 0">
        <section style="{{ $card }}">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr))">
                @foreach ($stats as [$label, $value])
                    <div style="padding: 12px 16px; border-left: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}">
                        <div style="color: #52525b; font-size: 12px">{{ $label }}</div>
                        <div style="font-size: 15px; {{ $loop->last ? 'font-weight: 600' : '' }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            <div style="padding: 10px 16px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #3f3f46">
                Termasuk:
                {{ $package->items->map(fn ($item) => $item->treatment->name.' '.$item->quota.'× (sisa '.($quota[$item->treatment_id] ?? 0).')')->implode('; ') }}.
                @if ($package->expires_at) Berlaku sampai {{ $package->expires_at->format('d/m/Y') }}. @endif
                @if ($package->notes) <div style="margin-top: 4px; white-space: pre-line">{{ $package->notes }}</div> @endif
            </div>
        </section>

        <section style="{{ $card }}">
            <div style="{{ $head }}"><span>Jadwal pembayaran</span><span style="font-weight: 400; color: #52525b; font-size: 13px">Jatuh tempo tiap tanggal {{ $package->due_day }}</span></div>
            <div style="overflow-x: auto">
                <table style="width: 100%; border-collapse: collapse; min-width: 560px">
                    <thead><tr><th style="{{ $th }}">Ke</th><th style="{{ $th }}">Jatuh tempo</th><th style="{{ $th }}; {{ $num }}">Jumlah</th><th style="{{ $th }}">Dibayar</th><th style="{{ $th }}">Status</th><th style="{{ $th }}"></th></tr></thead>
                    <tbody>
                        @foreach ($package->installments as $installment)
                            @php
                                $isOverdue = $installment->isOverdue();
                                $dueToday = $installment->due_date->isToday() && $installment->status !== InstallmentStatus::Paid;
                                $late = $installment->paid_at && $installment->paid_at->startOfDay()->gt($installment->due_date) ? $installment->due_date->diffInDays($installment->paid_at->startOfDay()) : 0;
                            @endphp
                            <tr style="{{ $dueToday ? 'background: #fffaeb' : '' }}">
                                <td style="{{ $td }}">{{ $installment->sequence === 0 ? 'DP' : $installment->sequence }}</td>
                                <td style="{{ $td }}; font-family: ui-monospace, monospace; font-size: 13px">{{ $installment->due_date->format('d/m/Y') }}</td>
                                <td style="{{ $td }}; {{ $num }}">{{ Money::format($installment->amount, false) }}</td>
                                <td style="{{ $td }}; font-family: ui-monospace, monospace; font-size: 13px">{{ $installment->paid_at?->format('d/m/Y') ?? '–' }}</td>
                                <td style="{{ $td }}; color: {{ $installment->status === InstallmentStatus::Paid ? '#0d6e63' : ($isOverdue || $dueToday ? '#b54708' : '#52525b') }}; font-weight: {{ $installment->status === InstallmentStatus::Paid || $isOverdue || $dueToday ? 500 : 400 }}">
                                    @if ($installment->status === InstallmentStatus::Paid)
                                        Lunas{{ $late ? ' · telat '.(int) $late.' hari' : '' }}
                                    @elseif ($dueToday)
                                        Jatuh tempo hari ini
                                    @elseif ($isOverdue)
                                        Lewat {{ (int) $installment->due_date->diffInDays(today()) }} hari
                                    @elseif ($installment->status === InstallmentStatus::Cancelled)
                                        Batal
                                    @else
                                        {{ $installment->status === InstallmentStatus::Billed ? 'Sudah ditagih' : 'Belum jatuh tempo' }}
                                    @endif
                                    @if ($installment->invoice)
                                        <a href="{{ \App\Filament\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $installment->invoice]) }}" style="display: block; color: #0d6e63; font-size: 12px; font-weight: 400; font-family: ui-monospace, monospace">{{ $installment->invoice->invoice_number }}</a>
                                    @endif
                                </td>
                                <td style="{{ $td }}; text-align: right">
                                    @if (in_array($installment->status, [InstallmentStatus::Pending, InstallmentStatus::Billed], true) && $package->status === \App\Enums\PatientPackageStatus::Active)
                                        {{ ($this->payInstallmentAction)(['installment' => $installment->id]) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section style="{{ $card }}">
            <div style="{{ $head }}"><span>Pemakaian kuota</span><span style="font-weight: 400; color: #52525b; font-size: 13px">{{ $package->usages->count() }} kali</span></div>
            @forelse ($package->usages as $usage)
                <div style="display: flex; justify-content: space-between; gap: 12px; padding: 8px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px">
                    <span>{{ $package->items->firstWhere('treatment_id', $usage->treatment_id)?->treatment->name }}</span>
                    <span style="color: #52525b">{{ $usage->used_at->format('d/m/Y') }}{{ $usage->visit ? ' · '.$usage->visit->visit_number : '' }}</span>
                </div>
            @empty
                <div style="padding: 12px 16px; color: #52525b; font-size: 14px">Belum dipakai. Kuota terpakai otomatis saat tagihan kunjungan diterbitkan.</div>
            @endforelse
        </section>
    </div>

    <section style="{{ $card }}">
        <div style="{{ $head }}"><span>Cicilan lewat jatuh tempo</span><span style="font-weight: 400; color: #52525b; font-size: 13px">semua paket</span></div>
        @forelse ($overdue as $item)
            <a href="{{ \App\Filament\Resources\PatientPackages\PatientPackageResource::getUrl('view', ['record' => $item->patient_package_id]) }}" style="display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px; color: inherit; text-decoration: none">
                <span><span style="font-weight: 500">{{ $item->patientPackage->patient->name }}</span><br><span style="color: #52525b; font-size: 13px">{{ $item->label() }}</span></span>
                <span style="text-align: right">{{ Money::format($item->amount, false) }}<br><span style="color: #b54708; font-size: 13px">{{ (int) $item->due_date->diffInDays(today()) }} hari</span></span>
            </a>
        @empty
            <div style="padding: 12px 16px; color: #52525b; font-size: 14px">Tidak ada.</div>
        @endforelse
    </section>
</div>
