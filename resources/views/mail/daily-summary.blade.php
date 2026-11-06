@php
    use App\Services\Reports\Metrics;
    use App\Support\Money;
    $t = $summary['total'];
@endphp
<x-mail::message>
# Ringkasan {{ $summary['date']->locale('id')->translatedFormat('l, j F Y') }}

**Semua cabang:** produksi {{ Money::format($t['production']) }}{{ $t['target'] ? ' ('.Metrics::percent(Metrics::ratio($t['production'], $t['target'])).' target)' : '' }} · kas masuk {{ Money::format($t['cash_in']) }} · {{ $t['visits'] }} kunjungan, {{ $t['new_patients'] }} pasien baru · tidak datang {{ $t['no_shows'] }} dari {{ $t['appointments'] }} janji ({{ Money::format($t['no_show_value']) }}).

<x-mail::table>
| Cabang | Produksi | Kas masuk | Kunjungan | Baru | Tidak datang | Stok kritis |
|:--|--:|--:|--:|--:|--:|:--|
@foreach ($summary['branches'] as $b)
| {{ $b['name'] }} | {{ Money::format($b['production'], false) }}{{ $b['target'] ? ' ('.Metrics::percent(Metrics::ratio($b['production'], $b['target'])).')' : '' }} | {{ Money::format($b['cash_in'], false) }} | {{ $b['visits'] }} | {{ $b['new_patients'] }} | {{ $b['no_shows'] }}/{{ $b['appointments'] }} | {{ $b['below_minimum'] }} di bawah min · {{ $b['expiring'] }} ED ≤ {{ (int) config('clinic.stock.expiry_warning_days', 60) }} hr{{ $b['negative'] ? ' · '.$b['negative'].' minus' : '' }} |
@endforeach
</x-mail::table>

Produksi = tindakan selesai setelah diskon (menurut tanggal kunjungan). Kas masuk = pembayaran diterima + top-up deposit − refund. Angka hari ini bisa berubah sampai tutup kasir; angka final tersimpan di snapshot 00:30.

<x-mail::button :url="url('/')">Buka dashboard</x-mail::button>
</x-mail::message>
