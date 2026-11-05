<x-filament-panels::page>
    @php $refunds = $this->approvedRefunds(); @endphp

    @if ($refunds->isNotEmpty())
        <section style="background: #fffaeb; border: 1px solid #fedf89; border-radius: 12px; padding: 12px 16px">
            <div style="font-weight: 600; font-size: 14px; margin-bottom: 6px">Refund disetujui, menunggu diserahkan ke pasien</div>
            <ul style="margin: 0; padding: 0; list-style: none; font-size: 14px; display: grid; gap: 4px">
                @foreach ($refunds as $refund)
                    <li>
                        {{ $refund->patient->name }} · {{ \App\Support\Money::format($refund->amount) }} ({{ $refund->method->getLabel() }})
                        · {{ $refund->invoice ? $refund->invoice->label() : 'saldo deposit' }}
                        @if ($refund->invoice)
                            · <a href="{{ \App\Filament\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $refund->invoice]) }}" style="color: #0d6e63; font-weight: 500">buka tagihan</a>
                        @else
                            · <a href="{{ \App\Filament\Resources\Patients\PatientResource::getUrl('view', ['record' => $refund->patient_id]) }}" style="color: #0d6e63; font-weight: 500">buka pasien</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
