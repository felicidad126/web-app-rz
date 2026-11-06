<x-filament-panels::page>
    @include('filament.clinic.patient-banner', ['patient' => $patient])

    @include('clinic.odontogram-chart', ['chart' => $chart, 'editable' => $editable, 'withVisit' => false])
    <p style="font-size: 12px; color: #52525b; margin: -8px 0 0">
        Catatan dari halaman ini adalah <strong>kondisi awal</strong> (tanpa kunjungan). Catatan saat pemeriksaan dibuat di tab Odontogram kunjungan.
        DMF-T dihitung otomatis dari kode car/rrx (D), mis (M), dan restorasi (F).
    </p>

    @if ($exam)
        <x-filament::section>
            @include('clinic.oral-exam-summary', ['exam' => $exam, 'style' => 'font-size: 14px'])
        </x-filament::section>
    @endif

    <x-filament::section heading="Riwayat catatan" :description="count($entries).' catatan terakhir'">
        <div style="overflow-x: auto">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px">
                <thead>
                    <tr style="text-align: left; color: #71717a; font-size: 12px">
                        <th style="padding: 6px 8px; font-weight: 500">Tanggal</th>
                        <th style="padding: 6px 8px; font-weight: 500">Gigi</th>
                        <th style="padding: 6px 8px; font-weight: 500">Permukaan</th>
                        <th style="padding: 6px 8px; font-weight: 500">Kode</th>
                        <th style="padding: 6px 8px; font-weight: 500">Jenis</th>
                        <th style="padding: 6px 8px; font-weight: 500">Kunjungan</th>
                        <th style="padding: 6px 8px; font-weight: 500">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr style="border-top: 1px solid #f4f4f5">
                            <td style="padding: 8px; white-space: nowrap">{{ $entry->created_at->format('d/m/Y') }}</td>
                            <td style="padding: 8px; font-family: ui-monospace, monospace">{{ $entry->tooth_number }}</td>
                            <td style="padding: 8px">{{ $entry->surface?->value ?? 'Seluruh gigi' }}</td>
                            <td style="padding: 8px">{{ $entry->condition_code }} · {{ $entry->condition->name }}</td>
                            <td style="padding: 8px"><x-filament::badge :color="$entry->entry_type->getColor()">{{ $entry->entry_type->getLabel() }}</x-filament::badge></td>
                            <td style="padding: 8px; font-family: ui-monospace, monospace; font-size: 13px">{{ $entry->visit?->visit_number ?? 'Kondisi awal' }}</td>
                            <td style="padding: 8px; color: #52525b">{{ $entry->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="padding: 12px 8px; color: #52525b">Belum ada catatan odontogram.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
