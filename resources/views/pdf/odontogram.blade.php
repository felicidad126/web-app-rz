<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Odontogram {{ $patient->rm_number }}</title>
    <style>
        @page { margin: 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #18181b; }
        h1 { font-size: 14pt; margin: 0; }
        .muted { color: #52525b; font-size: 9pt; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 8.5pt; }
        table.list th, table.list td { border-bottom: 1px solid #e4e4e7; padding: 3px 4px; text-align: left; }
        table.list th { color: #52525b; font-weight: normal; }
    </style>
</head>
<body>
    <h1>Odontogram</h1>
    <div class="muted">{{ $patient->name }} · {{ $patient->rm_number }} · {{ $patient->birth_date->format('d/m/Y') }} · dicetak {{ now()->format('d/m/Y H:i') }}</div>

    <div style="margin: 14px 0; text-align: center"><img src="data:image/svg+xml;base64,{{ $svg }}" style="width: 100%; max-width: 180mm"></div>

    <div class="muted">Warna permukaan = kode kondisi (lihat riwayat) · bingkai putus-putus merah: rencana · ✕ hilang · V sisa akar · bingkai tebal: mahkota/jembatan/implan. Permukaan: M mesial, O oklusal/insisal, D distal, V bukal/labial, L lingual/palatal.</div>
    <p><strong>DMF-T</strong>: D {{ $indices['D'] }} · M {{ $indices['M'] }} · F {{ $indices['F'] }} = {{ $indices['total'] }}
        @if ($indices['total_primary'] > 0)
            &nbsp; · &nbsp;<strong>def-t</strong>: d {{ $indices['d'] }} · e {{ $indices['e'] }} · f {{ $indices['f'] }} = {{ $indices['total_primary'] }}
        @endif
    </p>

    @include('clinic.oral-exam-summary', ['exam' => $exam, 'style' => 'font-size: 9pt; margin: 8px 0'])

    <table class="list">
        <thead><tr><th>Tanggal</th><th>Gigi</th><th>Permukaan</th><th>Kode</th><th>Jenis</th><th>Kunjungan</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at->format('d/m/Y') }}</td>
                    <td>{{ $entry->tooth_number }}</td>
                    <td>{{ $entry->surface?->value ?? '—' }}</td>
                    <td>{{ $entry->condition_code }} · {{ $entry->condition->name }}</td>
                    <td>{{ $entry->entry_type->getLabel() }}</td>
                    <td>{{ $entry->visit?->visit_number ?? '—' }}</td>
                    <td>{{ $entry->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
