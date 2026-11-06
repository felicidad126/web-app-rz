@php
    /** @var \App\Models\OralExamination|null $exam */
    use App\Services\Clinic\OralHygiene;
    $items = $exam ? array_filter([
        'Oklusi' => $exam->occlusion?->getLabel(),
        'Torus palatinus' => $exam->torus_palatinus?->getLabel(),
        'Torus mandibularis' => $exam->torus_mandibularis?->getLabel(),
        'Palatum' => $exam->palate?->getLabel(),
        'Diastema' => $exam->diastema,
        'Gigi anomali' => $exam->anomaly,
        'OHI-S' => $exam->ohis !== null ? OralHygiene::format((float) $exam->ohis).' ('.$exam->category().') · DI-S '.OralHygiene::format($exam->debris_index === null ? null : (float) $exam->debris_index).' · CI-S '.OralHygiene::format($exam->calculus_index === null ? null : (float) $exam->calculus_index) : null,
    ]) : [];
@endphp
@if ($exam && $items !== [])
    <div style="{{ $style ?? '' }}">
        <div style="font-weight: 600; margin-bottom: 4px">Pemeriksaan intraoral terakhir · {{ $exam->created_at->format('d/m/Y') }}</div>
        <table style="border-collapse: collapse; width: 100%">
            @foreach ($items as $label => $value)
                <tr><td style="padding: 2px 8px 2px 0; color: #52525b; width: 34%">{{ $label }}</td><td style="padding: 2px 0">{{ $value }}</td></tr>
            @endforeach
        </table>
    </div>
@endif
