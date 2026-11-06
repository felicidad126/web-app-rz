@php
    /** @var \App\Models\Patient $patient */
    $alerts = collect([
        $patient->allergies ? 'Alergi: '.$patient->allergies : null,
        $patient->systemic_diseases ?: null,
    ])->filter();
    // Konteks pasien (Fondasi UI): penjamin & saldo terutang selalu di posisi yang sama.
    $patient->loadMissing('defaultPayer');
    $outstanding = $patient->outstandingBalance();
    $deposit = $patient->depositBalance();
@endphp
<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px 28px; padding: 12px 16px; background: #fff; border: 1px solid #e4e4e7; border-radius: 8px">
    <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1 1 360px; min-width: 0">
        <span aria-hidden="true" style="width: 40px; height: 40px; flex: none; border-radius: 50%; background: #f4f4f5; color: #3f3f46; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px">
            {{ collect(explode(' ', $patient->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
        </span>
        <div style="min-width: 0">
            <div style="display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px">
                <span style="font-size: 16px; font-weight: 600">{{ $patient->name }}</span>
                <span class="klinik-mono" style="font-weight: 500; color: #52525b">{{ $patient->rm_number }}</span>
                <span style="font-size: 13px; color: #52525b">{{ $patient->gender->getLabel() }} · {{ $patient->age }} th ({{ $patient->birth_date->format('d/m/Y') }}){{ $patient->phone ? ' · '.$patient->phone : '' }}</span>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 2px 16px; font-size: 13px; margin-top: 2px">
                @forelse ($alerts as $alert)
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #b42318; font-weight: 500"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M12 3L2 20h20zM12 10v4M12 17v.5"/></svg>{{ $alert }}</span>
                @empty
                    <span style="color: #52525b">Tidak ada alergi atau penyakit penyerta tercatat</span>
                @endforelse
            </div>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 8px 24px; font-size: 13px">
        <div><div style="color: #52525b">Penjamin</div><div style="font-weight: 500">{{ $patient->defaultPayer?->name ?? 'Umum' }}</div></div>
        @if ($patient->blood_type)
            <div><div style="color: #52525b">Gol. darah</div><div style="font-weight: 500">{{ $patient->blood_type->getLabel() }}</div></div>
        @endif
        <div style="text-align: right">
            <div style="color: #52525b">Saldo terutang</div>
            <div style="font-weight: 600; font-variant-numeric: tabular-nums; color: {{ $outstanding > 0 ? '#b54708' : '#18181b' }}">{{ $outstanding > 0 ? \App\Support\Money::format($outstanding) : 'Tidak ada' }}</div>
        </div>
        @if ($deposit > 0)
            <div style="text-align: right"><div style="color: #52525b">Deposit</div><div style="font-weight: 500; font-variant-numeric: tabular-nums">{{ \App\Support\Money::format($deposit) }}</div></div>
        @endif
    </div>
</div>
