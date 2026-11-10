{{-- Panel gelap "Alur pembelian" (App\Filament\Widgets\PurchasingFlow). --}}
<x-filament-widgets::widget>
    <section class="klinik-flow" aria-labelledby="klinik-flow-title">
        <div class="klinik-flow-head">
            <div>
                <h2 id="klinik-flow-title">Alur pembelian</h2>
                <p>Permintaan dan pesanan yang masih terbuka</p>
            </div>
            <span class="klinik-flow-total">{{ $total }} <small>terbuka</small></span>
        </div>
        <div class="klinik-flow-tiles">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['url'] }}" class="klinik-flow-tile">
                    <span class="klinik-flow-label"><span class="klinik-flow-dot klinik-flow-dot-{{ $tile['dot'] }}" aria-hidden="true"></span>{{ $tile['label'] }}</span>
                    <span class="klinik-flow-count">{{ $tile['count'] }}</span>
                    <span class="klinik-flow-text">{{ $tile['text'] }}@if ($tile['code'])<br><span class="klinik-mono">{{ $tile['code'] }}</span>@endif</span>
                </a>
            @endforeach
        </div>
        <div class="klinik-flow-foot">
            @if ($cash)
                <span>Saldo kas pembelian {{ $cash['balance'] }} · keluar 30 hari {{ $cash['out'] }}</span>
            @else
                <span>Stok bertambah saat barang pesanan diterima.</span>
            @endif
            <a href="{{ $requestsUrl }}">Lihat permintaan →</a>
        </div>
    </section>
</x-filament-widgets::widget>
