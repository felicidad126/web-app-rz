<x-filament-panels::page>
    @php
        $counts = $this->counts();
        $views = ['semua' => 'Semua', 'minimum' => 'Di bawah minimum', 'kedaluwarsa' => 'Kedaluwarsa ≤ '.config('clinic.stock.expiry_warning_days').' hari', 'minus' => 'Stok minus'];
    @endphp
    {{-- Chip tampilan (mockup Stok): yang aktif gelap, bukan teal — teal hanya untuk aksi utama "Catat penerimaan". --}}
    <style>
        .stock-chip { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border: 1px solid #d4d4d8; border-radius: 6px; background: #fff; color: #3f3f46; font-size: 13px; font-weight: 500; white-space: nowrap; cursor: pointer }
        .stock-chip:hover { background: #f4f4f5 }
        .stock-chip:focus-visible { outline: 2px solid #0d6e63; outline-offset: 2px }
        .stock-chip[aria-pressed="true"] { border-color: #18181b; background: #18181b; color: #fff }
        .stock-chip-count { font-variant-numeric: tabular-nums; opacity: .75 }
        @media (pointer: coarse) { .stock-chip { height: 44px; padding: 0 14px } }
    </style>
    <div style="display: flex; flex-wrap: wrap; gap: 8px" role="group" aria-label="Tampilan">
        @foreach ($views as $key => $label)
            @continue($key === 'minus' && ! $counts['minus'])
            <button type="button" class="stock-chip" aria-pressed="{{ $view_filter === $key ? 'true' : 'false' }}" wire:click="setView('{{ $key }}')">
                {{ $label }} <span class="stock-chip-count">({{ $counts[$key] }})</span>
            </button>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
