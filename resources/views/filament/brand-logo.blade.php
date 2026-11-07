{{-- Logo klinik + nama aplikasi (header panel & halaman login). Logo: public/icons/logo.svg, nama: APP_NAME. --}}
<span class="klinik-brand">
    <img src="{{ asset('icons/logo.svg') }}" alt="" class="klinik-brand-logo">
    <span class="klinik-brand-name">{{ config('app.name') }}</span>
</span>
