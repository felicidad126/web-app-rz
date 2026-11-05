@php
    /** @var \App\Models\Invoice $invoice */
    $checklist = \App\Filament\Resources\Invoices\CheckoutPanel::checklist($invoice);
    $outstanding = \App\Filament\Resources\Invoices\CheckoutPanel::otherOutstanding($invoice);
    $done = collect($checklist)->where('done', true)->count();
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $head = 'display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-weight: 600; font-size: 14px';
@endphp
<div style="display: grid; gap: 16px">
    <section style="{{ $card }}">
        <div style="{{ $head }}"><span>Sebelum pasien pulang</span><span style="font-weight: 400; color: #52525b; font-size: 13px">{{ $done }} dari {{ count($checklist) }}</span></div>
        <ul style="list-style: none; margin: 0; padding: 0">
            @foreach ($checklist as $item)
                <li style="display: flex; gap: 10px; align-items: flex-start; padding: 10px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px">
                    @if ($item['done'])
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" style="flex: none; margin-top: 1px"><circle cx="12" cy="12" r="11" fill="#0d6e63"/><path d="M7 12.5l3 3 7-7" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @else
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" style="flex: none; margin-top: 1px"><circle cx="12" cy="12" r="10" fill="none" stroke="#a1a1aa" stroke-width="2"/></svg>
                    @endif
                    <div style="flex: 1; min-width: 0">
                        <div>{{ $item['label'] }} <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">{{ $item['done'] ? '(sudah)' : '(belum)' }}</span></div>
                        @if ($item['detail'])
                            <div style="color: #52525b; font-size: 13px">{{ $item['detail'] }}</div>
                        @endif
                    </div>
                    @if (! empty($item['action']))
                        <x-filament::button size="xs" color="gray" wire:click="{{ $item['action'] }}">Sudah</x-filament::button>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if ($outstanding->isNotEmpty())
        <section style="{{ $card }}">
            <div style="{{ $head }}"><span>Saldo terutang lain</span><span style="color: #b54708">{{ \App\Support\Money::format($outstanding->sum(fn ($i) => $i->balance())) }}</span></div>
            <ul style="list-style: none; margin: 0; padding: 0">
                @foreach ($outstanding as $other)
                    <li style="display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 13px">
                        <span><span style="font-family: ui-monospace, monospace">{{ $other->invoice_number }}</span> · {{ $other->issued_at?->format('d/m/Y') }} · {{ $other->issued_at?->diffForHumans(null, true) }}</span>
                        <span style="font-variant-numeric: tabular-nums">{{ \App\Support\Money::format($other->balance(), false) }}</span>
                    </li>
                @endforeach
            </ul>
            <div style="padding: 8px 16px 12px; color: #52525b; font-size: 12px">Bisa ditagih sekalian di jendela "Terima pembayaran".</div>
        </section>
    @endif
</div>
