@php
    /** @var \App\Models\CashSession $session */
    $voids = \App\Models\Payment::query()
        ->with(['invoice', 'voider', 'voidRequester'])
        ->whereHas('transaction', fn ($q) => $q->where('cash_session_id', $session->id))
        ->whereIn('status', [\App\Enums\PaymentStatus::Void, \App\Enums\PaymentStatus::VoidRequested])
        ->orderBy('id')
        ->get();
@endphp
<section style="background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden">
    <div style="display: flex; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-weight: 600; font-size: 14px">
        <span>Void di sesi ini</span><span style="font-weight: 400; color: #52525b">{{ $voids->count() }} transaksi</span>
    </div>
    @forelse ($voids as $payment)
        <div style="display: flex; gap: 16px; padding: 10px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px; flex-wrap: wrap">
            <span style="font-family: ui-monospace, monospace; font-size: 13px">{{ $payment->invoice->label() }}</span>
            <span style="flex: 1; min-width: 200px">{{ $payment->method->getLabel() }} {{ $payment->void_requested_at?->format('H:i') }} · {{ $payment->void_reason }}</span>
            <span style="font-variant-numeric: tabular-nums">{{ \App\Support\Money::format($payment->amount, false) }}</span>
            <span style="color: #52525b">{{ $payment->voider ? 'Disetujui '.$payment->voider->name : 'Menunggu persetujuan' }}</span>
        </div>
    @empty
        <div style="padding: 12px 16px; color: #52525b; font-size: 14px">Tidak ada.</div>
    @endforelse
</section>
