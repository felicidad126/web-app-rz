<x-filament-panels::page>
    @php
        $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
        $head = 'display: flex; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-weight: 600; font-size: 14px';
        $th = 'padding: 8px 16px; font-size: 12px; font-weight: 500; color: #52525b; text-align: left; border-bottom: 1px solid #e4e4e7';
        $td = 'padding: 10px 16px; font-size: 14px; border-top: 1px solid #f4f4f5; vertical-align: top';
        $num = 'text-align: right; font-variant-numeric: tabular-nums';
        $problems = $this->problems();
        $byType = $this->todayByType();
        $recent = $this->recent();
        $configured = \App\Services\SatuSehat\SatuSehatClient::fromConfig()->isConfigured();
    @endphp

    @if (! \App\Services\SatuSehat\VisitSync::enabled() || ! $configured)
        <section style="background: #fffaeb; border: 1px solid #fedf89; border-radius: 12px; padding: 12px 16px; font-size: 14px">
            <div style="font-weight: 600">Bridging SATUSEHAT belum aktif</div>
            <div style="color: #52525b; margin-top: 2px">
                Isi <code>SATUSEHAT_ORGANIZATION_ID</code>, <code>SATUSEHAT_CLIENT_ID</code>, <code>SATUSEHAT_CLIENT_SECRET</code>, dan
                <code>SATUSEHAT_ENABLED=true</code> di <code>.env</code> server, lalu klik <strong>Tes koneksi</strong>.
                Setiap cabang juga perlu Location ID (Organisasi → Cabang).
            </div>
        </section>
    @endif

    <section style="{{ $card }}">
        <div style="{{ $head }}">
            <span>Perlu diperbaiki</span>
            <span style="color: {{ $problems->isEmpty() ? '#0d6e63' : '#b42318' }}">{{ $problems->isEmpty() ? 'tidak ada' : $problems->count().' kunjungan' }}</span>
        </div>
        @if ($problems->isEmpty())
            <div style="padding: 12px 16px; color: #52525b; font-size: 14px">Semua kunjungan yang sudah ditandatangani terkirim atau sedang antre.</div>
        @else
            <div style="overflow-x: auto">
                <table style="width: 100%; border-collapse: collapse; min-width: 720px">
                    <thead><tr><th style="{{ $th }}">Pasien</th><th style="{{ $th }}">Penyebab & cara memperbaiki</th><th style="{{ $th }}">Percobaan</th><th style="{{ $th }}"></th></tr></thead>
                    <tbody>
                        @foreach ($problems as $sync)
                            <tr>
                                <td style="{{ $td }}; white-space: nowrap">
                                    <div style="font-weight: 500">{{ $sync->visit?->patient->name ?? '—' }}</div>
                                    <div style="color: #52525b; font-family: ui-monospace, monospace; font-size: 12px">{{ $sync->visit?->visit_number }}</div>
                                </td>
                                <td style="{{ $td }}">
                                    <div style="color: #b42318; font-weight: 500">{{ $sync->issue_code?->title() ?? $sync->status->getLabel() }}</div>
                                    @if ($sync->last_error)
                                        <div style="font-size: 13px">{{ $sync->last_error }}</div>
                                    @endif
                                    @if ($sync->issue_code)
                                        <div style="color: #52525b; font-size: 13px">{{ $sync->issue_code->fix() }}</div>
                                    @endif
                                </td>
                                <td style="{{ $td }}; color: #52525b; font-size: 13px; white-space: nowrap">{{ $sync->attempts }}× · {{ $sync->last_attempt_at?->format('d/m H:i') }}</td>
                                <td style="{{ $td }}; text-align: right; white-space: nowrap">
                                    @if ($sync->issue_code === \App\Enums\SatuSehatIssue::Duplicate)
                                        {{ ($this->markSentAction)(['sync' => $sync->id]) }}
                                    @endif
                                    {{ ($this->resendAction)(['sync' => $sync->id]) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 8px 16px 12px; color: #52525b; font-size: 12px">Kirim ulang memakai nomor kunjungan yang sama sebagai identifier, dan kunjungan yang sudah diterima tidak dikirim dua kali.</div>
        @endif
    </section>

    <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); align-items: start">
        <section style="{{ $card }}">
            <div style="{{ $head }}"><span>Hari ini per jenis data</span><span style="font-weight: 400; color: #52525b; font-size: 13px">{{ $byType['Encounter']['sent'] + $byType['Encounter']['pending'] + $byType['Encounter']['failed'] }} kunjungan</span></div>
            <table style="width: 100%; border-collapse: collapse">
                <thead><tr><th style="{{ $th }}">Jenis data</th><th style="{{ $th }}; {{ $num }}">Terkirim</th><th style="{{ $th }}; {{ $num }}">Antre</th><th style="{{ $th }}; {{ $num }}">Gagal</th></tr></thead>
                <tbody>
                    @foreach ($byType as $type => $row)
                        <tr>
                            <td style="{{ $td }}">{{ $row['label'] }} <span style="color: #71717a; font-family: ui-monospace, monospace; font-size: 12px">{{ $type }}</span></td>
                            <td style="{{ $td }}; {{ $num }}">{{ $row['sent'] }}</td>
                            <td style="{{ $td }}; {{ $num }}">{{ $row['pending'] }}</td>
                            <td style="{{ $td }}; {{ $num }}; color: {{ $row['failed'] ? '#b42318' : 'inherit' }}">{{ $row['failed'] }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight: 600">
                        <td style="{{ $td }}">Total</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $byType->sum('sent') }}</td>
                        <td style="{{ $td }}; {{ $num }}">{{ $byType->sum('pending') }}</td>
                        <td style="{{ $td }}; {{ $num }}; color: {{ $byType->sum('failed') ? '#b42318' : 'inherit' }}">{{ $byType->sum('failed') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section style="{{ $card }}">
            <div style="{{ $head }}"><span>Pengiriman terakhir</span></div>
            @forelse ($recent as $sync)
                <div style="display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px">
                    <span><span style="color: #52525b; font-variant-numeric: tabular-nums">{{ $sync->sent_at?->format('d/m H:i') }}</span> · {{ $sync->visit?->patient->name }}</span>
                    <span style="font-family: ui-monospace, monospace; font-size: 12px; color: #0d6e63">{{ $sync->satusehat_id ? substr($sync->satusehat_id, 0, 4).'…'.substr($sync->satusehat_id, -4) : 'diterima' }}</span>
                </div>
            @empty
                <div style="padding: 12px 16px; color: #52525b; font-size: 14px">Belum ada kunjungan yang terkirim.</div>
            @endforelse
        </section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
