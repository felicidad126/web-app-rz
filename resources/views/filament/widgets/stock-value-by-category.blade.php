{{-- Donat nilai persediaan per kategori (App\Filament\Widgets\StockValueByCategory). --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Nilai persediaan per kategori" description="Harga pokok · total sama dengan halaman Stok" class="klinik-dash-donut">
        @if ($rows === [])
            <p class="klinik-dash-empty">Belum ada barang berstok di cabang ini.</p>
        @else
            <div class="klinik-donut-wrap">
                <div class="klinik-donut" role="img" aria-label="Donat nilai persediaan per kategori, total {{ $total }}" style="background: conic-gradient({{ $gradient }})">
                    <div class="klinik-donut-hole">
                        <span>Total</span>
                        <strong>{{ $totalShort }}</strong>
                    </div>
                </div>
            </div>
            <ul class="klinik-donut-legend">
                @foreach ($rows as $row)
                    <li>
                        <span class="klinik-donut-swatch" style="background: {{ $row['color'] }}" aria-hidden="true"></span>
                        <span class="klinik-donut-label">{{ $row['label'] }}</span>
                        <span class="klinik-donut-pct">{{ $row['percent'] }}%</span>
                        <span class="klinik-donut-value">{{ $row['formatted'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
