{{-- Kop klinik: hanya tampil saat halaman panel dicetak (lihat @media print di klinik.css). --}}
@php($branch = \App\Support\BranchContext::available()->firstWhere('id', \App\Support\BranchContext::id()))
<div class="klinik-print-header" aria-hidden="true">
    <img src="{{ asset('icons/logo.svg') }}" alt="" class="klinik-print-header-logo">
    <div class="klinik-print-header-clinic">
        <div class="klinik-print-header-name">{{ config('app.name') }}</div>
        <div class="klinik-print-header-branch">{{ collect([$branch?->name, $branch?->address, $branch?->phone])->filter()->implode(' · ') }}</div>
    </div>
    <div class="klinik-print-header-meta">Dicetak {{ now()->translatedFormat('j M Y, H:i') }}<br>{{ auth()->user()?->name }}</div>
</div>
