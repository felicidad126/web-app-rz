@php
    /** @var \App\Models\CashSession $session */
    use App\Enums\PaymentMethod;
    use App\Support\Money;
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7; white-space: nowrap';
    $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid #f4f4f5';
    $num = 'text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap';
    $methods = $session->method_totals;
    if ($methods === null && $system !== null) {
        $methods = collect($system)->mapWithKeys(fn ($amount, $method) => [$method => ['system' => $method === 'cash' ? $session->opening_cash + $amount : $amount, 'reported' => null, 'matched' => null]])->all();
    }
    $m = fn (?int $v): string => Money::format($v, false);
@endphp
{{-- Kasir, cabang, dan jam buka/tutup ada di subjudul halaman; di sini hanya angka (rupiah, tanpa "Rp"). --}}
<div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr)); align-items: start">
    <section style="{{ $card }}">
        <div style="padding: 12px 16px; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e4e4e7">Per metode bayar</div>
        <table style="width: 100%; border-collapse: collapse">
            <thead><tr><th style="{{ $th }}">Metode</th><th style="{{ $th }}; {{ $num }}">Sistem</th><th style="{{ $th }}; {{ $num }}">Dihitung</th><th style="{{ $th }}">Status</th></tr></thead>
            <tbody>
                @foreach ($methods ?? [] as $method => $row)
                    <tr>
                        <td style="{{ $td }}; font-weight: 500">{{ PaymentMethod::from($method)->getLabel() }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $m($row['system']) }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $row['reported'] === null ? '—' : $m($row['reported']) }}</td>
                        <td style="{{ $td }}; white-space: nowrap; color: {{ $row['matched'] === false ? '#b42318' : '#52525b' }}; {{ $row['matched'] === false ? 'font-weight: 500' : '' }}">
                            @if ($row['matched'] === null)
                                Sesi masih terbuka
                            @elseif ($row['matched'])
                                ✓ Cocok
                            @else
                                Selisih {{ $m(($row['reported'] ?? 0) - $row['system']) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    <section style="{{ $card }}">
        <div style="padding: 12px 16px; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e4e4e7">Ringkasan tunai</div>
        <dl style="display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 16px; padding: 12px 16px; margin: 0; font-size: 14px">
            <dt style="color: #52525b">Kas awal</dt><dd style="margin: 0; {{ $num }}">{{ $m($session->opening_cash) }}</dd>
            @if ($session->closed_at)
                <dt style="color: #52525b">Tunai menurut sistem</dt><dd style="margin: 0; {{ $num }}">{{ $m($session->expected_cash) }}</dd>
                <dt style="color: #52525b">Tunai dihitung</dt><dd style="margin: 0; {{ $num }}">{{ $m($session->counted_cash) }}</dd>
                <dt style="font-weight: 600">Selisih</dt><dd style="margin: 0; font-weight: 600; {{ $num }}; color: {{ $session->difference ? '#b42318' : 'inherit' }}">{{ $m($session->difference) }}</dd>
                @if ($session->difference_reason)
                    <dt style="color: #52525b">Penyebab</dt><dd style="margin: 0; text-align: right">{{ $session->difference_reason->getLabel() }}{{ $session->notes ? ' · '.$session->notes : '' }}</dd>
                @endif
                <dt style="color: #52525b">Disetor ke brankas</dt><dd style="margin: 0; {{ $num }}">{{ $m($session->deposited_to_safe) }}</dd>
                <dt style="color: #52525b">Kas awal berikutnya</dt><dd style="margin: 0; {{ $num }}">{{ $m($session->next_opening_cash) }}</dd>
            @else
                <dt style="color: #52525b">Status</dt><dd style="margin: 0; text-align: right">Sesi masih terbuka</dd>
            @endif
        </dl>
    </section>
</div>
