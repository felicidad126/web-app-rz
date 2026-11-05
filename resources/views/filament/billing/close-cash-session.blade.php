<x-filament-panels::page>
    <form wire:submit="close" style="display: grid; gap: 24px">
        {{ $this->form }}

        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-lock-closed">Tutup & kunci sesi</x-filament::button>
            <span style="color: #52525b; font-size: 13px">Setelah ditutup, angka sesi ini tidak bisa diubah.</span>
        </div>
    </form>
</x-filament-panels::page>
