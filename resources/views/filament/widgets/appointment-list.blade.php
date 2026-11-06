@php
    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $badge = ['info' => '#1d4ed8;background:#eff6ff', 'warning' => '#92400e;background:#fffbeb', 'danger' => '#b91c1c;background:#fef2f2', 'success' => '#047857;background:#ecfdf5', 'gray' => '#3f3f46;background:#f4f4f5'];
@endphp
<x-filament-widgets::widget>
    <section style="{{ $card }}">
        <div style="padding: 14px 16px">
            <div style="font-weight: 600">{{ $heading }}</div>
            <div style="font-size: 12px; color: #52525b">{{ $description }}</div>
        </div>
        @forelse ($items as $item)
            @php $a = $item['appointment']; @endphp
            <div style="display: grid; grid-template-columns: 64px 1fr; gap: 12px; padding: 10px 16px; border-top: 1px solid #f4f4f5">
                <div style="font-variant-numeric: tabular-nums; font-weight: 600">{{ $a->start_at->format('H:i') }}</div>
                <div>
                    <div style="font-weight: 500">{{ $a->patient->name }} <span style="font-size: 12px; color: #71717a">{{ $a->patient->rm_number }}{{ $showDoctor ? ' · '.$a->practitioner->displayName() : '' }}{{ $a->chair ? ' · '.$a->chair->name : '' }}</span></div>
                    @if ($a->reason)<div style="font-size: 13px; color: #52525b">{{ $a->reason }}</div>@endif
                    @if ($a->status === \App\Enums\AppointmentStatus::NoShow)<div style="font-size: 12px; color: #b91c1c">Tidak datang</div>@endif
                    @if (! empty($item['flags']))
                        <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px">
                            @foreach ($item['flags'] as $flag)
                                <span style="font-size: 11px; padding: 1px 8px; border-radius: 999px; color: {{ $badge[$flag['color']] }}">{{ $flag['label'] }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding: 12px 16px 16px; font-size: 14px; color: #71717a">{{ $empty }}</div>
        @endforelse
    </section>
</x-filament-widgets::widget>
