@php
    /** @var \App\Models\LabOrder $order */
    use App\Enums\LabOrderStatus;
    use App\Filament\Resources\LabOrders\LabOrderResource;
    use App\Support\Money;

    $card = 'background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden';
    $label = 'color: #52525b; font-size: 12px';
    $appointment = $order->getAttribute('next_appointment_at');
    $warning = LabOrderResource::appointmentWarning($order);
    $steps = [
        [LabOrderStatus::Created, $order->created_at, $order->creator?->name],
        [LabOrderStatus::Sent, $order->sent_at, null],
        [LabOrderStatus::Received, $order->received_at, null],
        [LabOrderStatus::Checked, $order->checked_at, null],
        [LabOrderStatus::Fitted, $order->fitted_at, null],
    ];
    $current = $order->status->step();
    $details = array_filter([
        'Pekerjaan' => $order->work_type->getLabel().($order->teeth ? ' · gigi '.$order->teeth : '').($order->shade ? ' · shade '.$order->shade : ''),
        'Spesifikasi' => $order->specification,
        'Lab' => $order->supplier->name.($order->supplier->phone ? ' · '.$order->supplier->phone : ''),
        'Biaya lab' => Money::format($order->cost),
        'Dokter' => $order->practitioner->user->name,
        'Kunjungan' => $order->visit ? \App\Filament\Clinic\CodeText::join([[$order->visit->visit_number, true], $order->visit->visit_date->format('d/m/Y')]) : null,
        'Tindakan' => $order->visitProcedure ? $order->visitProcedure->treatment->name.($order->visitProcedure->tooth_number ? ' · gigi '.$order->visitProcedure->tooth_number : '') : null,
        'Jatuh tempo' => $order->due_at ? $order->due_at->format('d/m/Y').(($note = LabOrderResource::dueNote($order)) ? ' ('.$note.')' : '') : null,
        'Janji pasang' => $appointment ? $appointment->format('d/m/Y H:i') : 'Belum dijadwalkan',
        'Catatan' => $order->notes,
        'Alasan batal' => $order->cancel_reason,
    ], fn ($v) => filled($v));
@endphp
<div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items: start">
    <section style="{{ $card }}">
        <dl style="display: grid; gap: 12px; padding: 16px; margin: 0; font-size: 14px">
            @foreach ($details as $name => $value)
                <div>
                    <dt style="{{ $label }}">{{ $name }}</dt>
                    <dd style="margin: 0; white-space: pre-line; {{ ($name === 'Jatuh tempo' && $order->isLate()) || ($name === 'Janji pasang' && $warning) ? 'color: #b54708; font-weight: 500' : '' }}">{{ $value }}@if ($name === 'Janji pasang' && $warning) — {{ $warning }}@endif</dd>
                </div>
            @endforeach
        </dl>
    </section>
    <section style="{{ $card }}; padding: 16px">
        <div style="{{ $label }}; margin-bottom: 10px">Tahap</div>
        @if ($order->status === LabOrderStatus::Cancelled)
            <p style="margin: 0 0 12px; color: #52525b; font-size: 14px; font-weight: 500">Dibatalkan {{ $order->cancelled_at?->format('d/m/Y H:i') }}</p>
        @endif
        <ol style="list-style: none; margin: 0; padding: 0; display: grid; gap: 0">
            @foreach ($steps as $i => [$status, $at, $by])
                @php
                    $done = $at !== null && ($current >= $status->step() || $order->status === LabOrderStatus::Cancelled);
                    $isNow = $order->status === $status;
                    $dot = $done ? '#0f766e' : '#d4d4d8';
                @endphp
                <li style="display: grid; grid-template-columns: 20px 1fr; gap: 10px; padding-bottom: {{ $loop->last ? '0' : '14px' }}; position: relative">
                    @unless ($loop->last)
                        <span style="position: absolute; left: 9px; top: 18px; bottom: 0; width: 2px; background: {{ $done && $current > $status->step() ? '#0f766e' : '#e4e4e7' }}"></span>
                    @endunless
                    <span style="width: 20px; height: 20px; border-radius: 999px; background: {{ $done ? $dot : '#fff' }}; border: 2px solid {{ $dot }}; box-sizing: border-box; position: relative"></span>
                    <div style="font-size: 14px">
                        <div style="font-weight: {{ $isNow ? '600' : '400' }}; color: {{ $done ? '#18181b' : '#71717a' }}">{{ $status->getLabel() }}</div>
                        @if ($at)
                            <div style="{{ $label }}">{{ $at->format('d/m/Y H:i') }}{{ $by ? ' · '.$by : '' }}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
        <p style="{{ $label }}; margin: 14px 0 0">Biaya lab ikut tercatat di tagihan kunjungan saat terbit, untuk margin dan jasa dokter.</p>
    </section>
</div>
