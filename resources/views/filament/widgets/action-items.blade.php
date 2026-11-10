{{-- Daftar "Perlu tindakan" (App\Filament\Widgets\ActionItems). --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Perlu tindakan" class="klinik-dash-actions">
        <x-slot name="afterHeader">
            @if ($urgent > 0)
                <span class="klinik-action-count">{{ $urgent }}</span>
            @endif
        </x-slot>

        @if ($items === [])
            <p class="klinik-dash-empty">
                <x-filament::icon icon="heroicon-o-check-circle" class="klinik-dash-empty-icon" />
                Tidak ada yang perlu ditindaklanjuti di cabang ini.
            </p>
        @else
            <ul class="klinik-actions">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="klinik-action">
                            <span class="klinik-action-icon klinik-tone-{{ $item['tone'] }}" aria-hidden="true">
                                <x-filament::icon :icon="$item['icon']" />
                            </span>
                            <span class="klinik-action-body">
                                <span class="klinik-action-title">{{ $item['title'] }}</span>
                                <span class="klinik-action-meta">@if ($item['code'])<span class="klinik-mono">{{ $item['code'] }}</span> · @endif{{ $item['meta'] }}</span>
                            </span>
                            <span class="klinik-action-badge klinik-tone-{{ $item['tone'] }}">{{ $item['badge'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($more > 0)
                <a href="{{ $moreUrl }}" class="klinik-action-more">dan {{ $more }} lainnya di halaman Stok →</a>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
