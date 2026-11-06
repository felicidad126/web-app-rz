{{-- Penyesuaian tampilan panel sesuai Fondasi UI mockup v2 (public/css/klinik.css, tanpa build aset). --}}
@php($path = public_path('css/klinik.css'))
<link rel="stylesheet" href="{{ asset('css/klinik.css') }}?v={{ is_file($path) ? filemtime($path) : 0 }}">
