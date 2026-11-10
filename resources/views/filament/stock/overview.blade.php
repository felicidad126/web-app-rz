<x-filament-panels::page>
    @php
        $counts = $this->counts();
        $views = ['semua' => 'Semua', 'minimum' => 'Di bawah minimum', 'kedaluwarsa' => 'Kedaluwarsa ≤ '.config('clinic.stock.expiry_warning_days').' hari', 'minus' => 'Stok minus'];
    @endphp
    {{-- Chip tampilan (mockup Stok): yang aktif gelap, bukan teal — teal hanya untuk aksi utama "Catat penerimaan". --}}
    <style>
        .stock-chip { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border: 1px solid var(--gray-300); border-radius: 6px; background: #fff; color: var(--gray-700); font-size: 13px; font-weight: 500; white-space: nowrap; cursor: pointer }
        .stock-chip:hover { background: var(--gray-100) }
        .stock-chip:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px }
        .stock-chip[aria-pressed="true"] { border-color: var(--gray-950); background: var(--gray-950); color: #fff }
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
