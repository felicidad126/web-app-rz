<x-filament-panels::page>
    @php
        $counts = $this->counts();
        $views = ['semua' => 'Semua', 'minimum' => 'Di bawah minimum', 'kedaluwarsa' => 'Kedaluwarsa ≤ '.config('clinic.stock.expiry_warning_days').' hari', 'minus' => 'Stok minus'];
    @endphp
    <div style="display: flex; flex-wrap: wrap; gap: 8px" role="group" aria-label="Tampilan">
        @foreach ($views as $key => $label)
            @continue($key === 'minus' && ! $counts['minus'])
            <x-filament::button size="sm" :color="$view_filter === $key ? 'primary' : 'gray'" :outlined="$view_filter !== $key" wire:click="setView('{{ $key }}')">
                {{ $label }} ({{ $counts[$key] }})
            </x-filament::button>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
