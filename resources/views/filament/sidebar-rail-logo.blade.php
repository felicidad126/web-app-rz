{{-- Logo saat sidebar diciutkan (Filament menyembunyikan logo + nama di mode ini). Klik = Dashboard. --}}
<a {{ \Filament\Support\generate_href_html(filament()->getHomeUrl() ?? filament()->getUrl()) }}
   x-data="{}" x-cloak x-show="! $store.sidebar.isOpen"
   class="klinik-rail-logo" aria-label="Dashboard">
    <img src="{{ asset('icons/logo.svg') }}" alt="" width="32" height="32">
</a>
