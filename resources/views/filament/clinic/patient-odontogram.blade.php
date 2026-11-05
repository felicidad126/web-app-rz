<x-filament-panels::page>
    @include('filament.clinic.patient-banner', ['patient' => $patient])

    <x-filament::section heading="Status terkini" description="Hitam: kondisi saat ini · Merah: rencana yang belum dikerjakan. Gigi susu ditampilkan lebih pucat.">
        <div style="overflow-x: auto">
            @include('clinic.odontogram-grid', ['teeth' => $teeth])
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 8px 28px; margin-top: 14px; font-size: 14px">
            <span><strong>DMF-T</strong>: D {{ $indices['D'] }} · M {{ $indices['M'] }} · F {{ $indices['F'] }} = <strong>{{ $indices['total'] }}</strong></span>
            @if ($indices['total_primary'] > 0)
                <span><strong>def-t</strong>: d {{ $indices['d'] }} · e {{ $indices['e'] }} · f {{ $indices['f'] }} = <strong>{{ $indices['total_primary'] }}</strong></span>
            @endif
            <span style="color: #52525b; font-size: 13px">Dihitung otomatis dari kode car/rrx (D), mis (M), dan restorasi (F). Cocokkan dengan dokter klinik.</span>
        </div>
    </x-filament::section>

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
