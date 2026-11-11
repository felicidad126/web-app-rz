{{-- Menu bawah di HP & tablet tegak (< 64rem): pengganti tombol ☰; isi dari App\Filament\Support\BottomNav (sesuai izin). --}}
@php($items = \App\Filament\Support\BottomNav::items())
@if ($items !== [])
    <nav class="klinik-bottom-nav" aria-label="Menu cepat" x-data="{}" x-bind:inert="$store.sidebar.isOpenMobile">
        @foreach ($items as $item)
            @php($classes = ['klinik-bnav-item', 'klinik-bnav-primary' => $item['primary'], 'klinik-bnav-active' => $item['active']])
            @if ($item['url'] === null)
                <button type="button" @class($classes) aria-controls="fi-main-sidebar"
                        x-bind:aria-expanded="$store.sidebar.isOpen" x-on:click="$store.sidebar.open($event.currentTarget)">
            @else
                <a {{ \Filament\Support\generate_href_html($item['url']) }} @class($classes) @if ($item['active']) aria-current="page" @endif>
            @endif
                <span class="klinik-bnav-icon">
                    <x-filament::icon :icon="$item['icon']" />
                    @if ($item['badge'])
                        <span class="klinik-bnav-badge" aria-label="{{ $item['badge'] }} menunggu">{{ $item['badge'] }}</span>
                    @endif
                </span>
                <span class="klinik-bnav-label">{{ $item['label'] }}</span>
            @if ($item['url'] === null)
                </button>
            @else
                </a>
            @endif
        @endforeach
    </nav>
@endif
