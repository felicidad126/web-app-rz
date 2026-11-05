@php use App\Filament\Pages\QueueBoard; @endphp
<x-filament-panels::page>
    <div wire:poll.10s style="display: flex; flex-direction: column; gap: 16px">
        <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px">
            <div style="font-size: 15px; color: #52525b">{{ $branch }} · {{ now()->translatedFormat('l, j F Y') }}</div>
            <div style="font: 600 28px ui-monospace, monospace">{{ now()->format('H:i') }}</div>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start">
            <section style="flex: 1 1 420px; background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 20px">
                <h2 style="margin: 0 0 12px; font-size: 18px; font-weight: 600">Sedang dilayani</h2>
                @forelse ($serving as $visit)
                    <div style="display: flex; align-items: center; gap: 18px; padding: 12px 0; border-top: 1px solid #f4f4f5">
                        <span style="font: 700 44px ui-monospace, monospace; color: var(--primary-700); min-width: 90px">{{ str_pad((string) $visit->queue_number, 3, '0', STR_PAD_LEFT) }}</span>
                        <span style="display: flex; flex-direction: column">
                            <span style="font-size: 22px; font-weight: 600">{{ QueueBoard::maskName($visit->patient->name) }}</span>
                            <span style="font-size: 16px; color: #52525b">{{ $visit->chair?->name ?? 'Ruang periksa' }} · {{ $visit->practitioner->user->name }}</span>
                        </span>
                    </div>
                @empty
                    <div style="font-size: 16px; color: #52525b">Belum ada pasien di kursi.</div>
                @endforelse
            </section>
            <section style="flex: 1 1 320px; background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 20px">
                <h2 style="margin: 0 0 12px; font-size: 18px; font-weight: 600">Menunggu ({{ count($waiting) }})</h2>
                @forelse ($waiting as $visit)
                    <div style="display: flex; align-items: baseline; gap: 14px; padding: 8px 0; border-top: 1px solid #f4f4f5">
                        <span style="font: 600 24px ui-monospace, monospace; min-width: 64px">{{ str_pad((string) $visit->queue_number, 3, '0', STR_PAD_LEFT) }}</span>
                        <span style="font-size: 18px">{{ QueueBoard::maskName($visit->patient->name) }}</span>
                    </div>
                @empty
                    <div style="font-size: 16px; color: #52525b">Tidak ada antrean.</div>
                @endforelse
            </section>
        </div>
        <p style="margin: 0; font-size: 13px; color: #52525b">Diperbarui otomatis tiap 10 detik. Nama pasien disamarkan untuk menjaga privasi.</p>
    </div>
</x-filament-panels::page>
