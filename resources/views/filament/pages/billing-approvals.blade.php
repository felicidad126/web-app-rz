<x-filament-panels::page>
    @php
        $groups = [
            ['title' => 'Void pembayaran', 'hint' => 'Koreksi salah input. Uang tidak berpindah; kasir mencatat ulang pembayaran yang benar.', 'type' => 'payment', 'rows' => static::pendingPayments()->map(fn ($p) => [
                'id' => $p->id, 'ref' => $p->invoice->label(), 'who' => $p->invoice->patient->name,
                'what' => $p->method->getLabel().' '.\App\Support\Money::format($p->amount).' · dibayar '.$p->transaction->paid_at->format('d/m H:i'),
                'reason' => $p->void_reason, 'by' => $p->voidRequester?->name.' · '.$p->void_requested_at?->format('d/m H:i'),
            ])],
            ['title' => 'Void tagihan', 'hint' => 'Tagihan dibatalkan; kuota paket yang terpakai dikembalikan.', 'type' => 'invoice', 'rows' => static::pendingInvoices()->map(fn ($i) => [
                'id' => $i->id, 'ref' => $i->label(), 'who' => $i->patient->name, 'what' => 'Total '.\App\Support\Money::format($i->total),
                'reason' => $i->void_reason, 'by' => $i->voidRequester?->name.' · '.$i->void_requested_at?->format('d/m H:i'),
            ])],
            ['title' => 'Refund', 'hint' => 'Setelah disetujui, kasir menyerahkan dana dari sesi kasirnya.', 'type' => 'refund', 'rows' => static::pendingRefunds()->map(fn ($r) => [
                'id' => $r->id, 'ref' => $r->invoice?->label() ?? 'Deposit', 'who' => $r->patient->name,
                'what' => \App\Support\Money::format($r->amount).' lewat '.$r->method->getLabel(),
                'reason' => $r->reason, 'by' => $r->requester?->name.' · '.$r->requested_at->format('d/m H:i'),
            ])],
        ];
    @endphp

    @foreach ($groups as $group)
        <section style="background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden">
            <div style="display: flex; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #e4e4e7; flex-wrap: wrap">
                <div><div style="font-weight: 600; font-size: 14px">{{ $group['title'] }}</div><div style="color: #52525b; font-size: 13px">{{ $group['hint'] }}</div></div>
                <span style="color: #52525b; font-size: 13px">{{ $group['rows']->count() }} menunggu</span>
            </div>
            @forelse ($group['rows'] as $row)
                <div style="display: flex; gap: 12px 20px; align-items: center; padding: 12px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; flex-wrap: wrap; font-size: 14px">
                    <span style="font-family: ui-monospace, monospace; font-size: 13px; min-width: 130px">{{ $row['ref'] }}</span>
                    <div style="flex: 1; min-width: 240px">
                        <div><strong style="font-weight: 600">{{ $row['who'] }}</strong> · {{ $row['what'] }}</div>
                        <div style="color: #52525b; font-size: 13px">Alasan: {{ $row['reason'] }} · diajukan {{ $row['by'] }}</div>
                    </div>
                    <div style="display: flex; gap: 8px">
                        {{ ($this->decideAction)(['type' => $group['type'], 'id' => $row['id'], 'approve' => true]) }}
                        {{ ($this->decideAction)(['type' => $group['type'], 'id' => $row['id'], 'approve' => false]) }}
                    </div>
                </div>
            @empty
                <div style="padding: 12px 16px; color: #52525b; font-size: 14px">Tidak ada pengajuan.</div>
            @endforelse
        </section>
    @endforeach

</x-filament-panels::page>
