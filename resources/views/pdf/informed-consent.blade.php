@php
    /** @var \App\Models\InformedConsent $consent */
    $patient = $consent->patient;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $consent->title }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #18181b; line-height: 1.5; }
        h1 { font-size: 15pt; margin: 0 0 4px; }
        .muted { color: #52525b; font-size: 9.5pt; }
        table.meta { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 10pt; }
        table.meta td { padding: 3px 0; vertical-align: top; }
        table.meta td.k { width: 32%; color: #52525b; }
        .content { margin: 14px 0 22px; white-space: pre-wrap; }
        table.sign { width: 100%; margin-top: 18px; font-size: 10pt; }
        table.sign td { width: 50%; vertical-align: bottom; padding-top: 6px; }
        .sig { height: 70px; }
        .line { border-top: 1px solid #18181b; padding-top: 4px; }
        .footer { margin-top: 26px; font-size: 8.5pt; color: #71717a; }
    </style>
</head>
<body>
    <div class="muted">{{ $consent->visit->branch->name }}</div>
    <h1>{{ $consent->title }}</h1>
    <div class="muted">Persetujuan tindakan kedokteran gigi</div>

    <table class="meta">
        <tr><td class="k">Nama pasien</td><td>{{ $patient->name }}</td></tr>
        <tr><td class="k">No. rekam medis</td><td>{{ $patient->rm_number }}</td></tr>
        <tr><td class="k">Tanggal lahir</td><td>{{ $patient->birth_date->format('d/m/Y') }}</td></tr>
        <tr><td class="k">Dokter</td><td>{{ $consent->practitioner->user->name }}</td></tr>
        <tr><td class="k">Kunjungan</td><td>{{ $consent->visit->visit_number }}</td></tr>
    </table>

    <div class="content">{{ $consent->content_snapshot }}</div>

    <table class="sign">
        <tr>
            <td>
                <div class="sig"><img src="{{ $signature }}" style="max-height: 70px; max-width: 240px;" alt=""></div>
                <div class="line">{{ $consent->signer_name }}<br><span class="muted">{{ $consent->signer_relation->getLabel() }}</span></div>
            </td>
            <td>
                <div class="sig"></div>
                <div class="line">{{ $consent->practitioner->user->name }}<br><span class="muted">Dokter yang menjelaskan</span></div>
            </td>
        </tr>
    </table>

    <div class="footer">Ditandatangani secara elektronik pada {{ $consent->signed_at->format('d/m/Y H:i') }}. Isi dokumen ini disimpan sebagai salinan dan tidak dapat diubah.</div>
</body>
</html>
