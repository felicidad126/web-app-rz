@php
    use App\Enums\AppointmentStatus;
    use App\Enums\TreatmentCategory;
    use App\Enums\VisitStatus;

    $dot = fn ($appointment): array => match (true) {
        $appointment->visit?->status === VisitStatus::InTreatment => ['#7a5af8', 'Di kursi '.$appointment->visit->started_at?->format('H:i')],
        in_array($appointment->visit?->status, [VisitStatus::Done, VisitStatus::Billed], true) => ['#079455', 'Selesai '.$appointment->visit->finished_at?->format('H:i')],
        $appointment->status === AppointmentStatus::Arrived => ['#dc6803', 'Datang '.$appointment->visit?->arrived_at?->format('H:i')],
        $appointment->status === AppointmentStatus::Confirmed => ['#175cd3', 'Dikonfirmasi'],
        $appointment->status === AppointmentStatus::NoShow => ['#d92d20', 'Tidak datang'],
        default => ['#a1a1aa', 'Terjadwal'],
    };
    $minutes = ($endHour - $startHour) * 60;
    $height = $minutes * $scale;
@endphp

<x-filament-panels::page>
    <div wire:poll.30s style="display: flex; flex-direction: column; gap: 16px">
        {{-- Toolbar --}}
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="shiftDate(-1)" label-sr-only>Hari sebelumnya</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="goToday">Hari ini</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="shiftDate(1)" label-sr-only>Hari berikutnya</x-filament::button>
            <x-filament::input.wrapper style="width: 170px">
                <x-filament::input type="date" wire:model.live="date" aria-label="Tanggal" />
            </x-filament::input.wrapper>
            @if (count($branches) > 1)
                <x-filament::input.wrapper style="width: 240px">
                    <x-filament::input.select wire:model.live="branchId" aria-label="Cabang">
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            @endif
            <div style="margin-left: auto; display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 12px; color: #52525b">
                @foreach ([['#a1a1aa', 'Terjadwal'], ['#175cd3', 'Dikonfirmasi'], ['#dc6803', 'Datang'], ['#7a5af8', 'Di kursi'], ['#079455', 'Selesai'], ['#d92d20', 'Tidak datang']] as [$color, $label])
                    <span style="display: inline-flex; align-items: center; gap: 5px"><span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $color }}"></span>{{ $label }}</span>
                @endforeach
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start">
            {{-- Papan per kursi --}}
            <section style="flex: 1 1 620px; min-width: 0; background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; overflow-x: auto">
                <div style="min-width: {{ 56 + max(1, count($columns)) * 200 }}px">
                    <div style="display: grid; grid-template-columns: 56px repeat({{ max(1, count($columns)) }}, minmax(200px, 1fr)); border-bottom: 1px solid #e4e4e7">
                        <div></div>
                        @foreach ($columns as $column)
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; border-left: 1px solid #e4e4e7">
                                <div>
                                    <div style="font-weight: 600; font-size: 14px">{{ $column['name'] }}</div>
                                    <div style="font-size: 12px; color: #52525b">{{ count($column['appointments']) }} janji</div>
                                </div>
                                @can('Create:Appointment')
                                    <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="sm" label="Buat janji di {{ $column['name'] }}"
                                        wire:click="mountAction('createAppointment', { chair: {{ $column['id'] ?? 'null' }} })" />
                                @endcan
                            </div>
                        @endforeach
                    </div>

                    <div style="display: grid; grid-template-columns: 56px repeat({{ max(1, count($columns)) }}, minmax(200px, 1fr)); position: relative">
                        <div style="position: relative; height: {{ $height }}px">
                            @for ($h = $startHour; $h < $endHour; $h++)
                                <span style="position: absolute; right: 8px; top: {{ max(2, ($h - $startHour) * 60 * $scale - 7) }}px; font: 500 12px ui-monospace, monospace; color: #71717a">{{ sprintf('%02d:00', $h) }}</span>
                            @endfor
                        </div>
                        @foreach ($columns as $column)
                            <div style="position: relative; height: {{ $height }}px; border-left: 1px solid #e4e4e7">
                                @for ($h = $startHour; $h < $endHour; $h++)
                                    <div style="position: absolute; left: 0; right: 0; top: {{ ($h - $startHour) * 60 * $scale }}px; border-top: 1px solid #f4f4f5"></div>
                                @endfor
                                @foreach ($column['appointments'] as $appointment)
                                    @php
                                        [$bg, $accent] = ($appointment->category ?? TreatmentCategory::Other)->tint();
                                        [$dotColor, $statusText] = $dot($appointment);
                                        $top = ($appointment->start_at->hour * 60 + $appointment->start_at->minute - $startHour * 60) * $scale;
                                        $cardHeight = max(28, $appointment->start_at->diffInMinutes($appointment->end_at) * $scale - 3);
                                        $isSelected = $selected?->id === $appointment->id;
                                    @endphp
                                    <button type="button" wire:click="select({{ $appointment->id }})"
                                        aria-label="{{ $appointment->start_at->format('H:i') }} {{ $appointment->patient->name }}, {{ $statusText }}"
                                        style="position: absolute; z-index: 2; left: 4px; right: 4px; top: {{ $top }}px; height: {{ $cardHeight }}px; box-sizing: border-box; padding: 3px 8px; overflow: hidden; text-align: left; cursor: pointer;
                                               background: {{ $bg }}; border: 1px solid rgba(0,0,0,.06); border-left: 3px solid {{ $accent }}; border-radius: 4px; font-size: 12px; line-height: 1.3; color: #18181b;
                                               {{ $appointment->status === AppointmentStatus::NoShow ? 'opacity: .55;' : '' }}
                                               {{ $isSelected ? 'outline: 2px solid var(--primary-600); outline-offset: 1px;' : '' }}">
                                        <span style="display: flex; gap: 6px; white-space: nowrap; overflow: hidden">
                                            <span style="font-family: ui-monospace, monospace; color: #52525b">{{ $appointment->start_at->format('H:i') }}</span>
                                            <strong style="font-weight: 600; overflow: hidden; text-overflow: ellipsis">{{ $appointment->patient->name }}</strong>
                                        </span>
                                        <span style="display: flex; align-items: center; gap: 5px; color: #3f3f46">
                                            <span style="width: 7px; height: 7px; border-radius: 50%; background: {{ $dotColor }}; flex: none"></span>{{ $statusText }}
                                        </span>
                                        @if ($cardHeight >= 52)
                                            <span style="display: block; color: #3f3f46; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ $appointment->reason ?: $appointment->practitioner->user->name }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                        @if ($isToday && $nowOffset > 0 && $nowOffset < $height)
                            <div aria-hidden="true" style="position: absolute; left: 48px; right: 0; top: {{ $nowOffset }}px; border-top: 2px solid var(--primary-600); z-index: 1">
                                <span style="position: absolute; left: -48px; top: -10px; padding: 1px 4px; border-radius: 4px; background: var(--primary-600); color: #fff; font: 500 11px ui-monospace, monospace">{{ now()->format('H:i') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Panel kanan --}}
            <div style="flex: 0 1 340px; min-width: 280px; display: flex; flex-direction: column; gap: 16px">
                <x-filament::section heading="Antrean" :description="count($waiting).' menunggu · '.count($inTreatment).' di kursi'" compact>
                    <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px">
                        <div style="font-size: 12px; font-weight: 500; color: #71717a">Ruang tunggu</div>
                        @forelse ($waiting as $visit)
                            <div style="display: flex; justify-content: space-between; gap: 8px; align-items: flex-start">
                                <div>
                                    <div style="font-weight: 500"><span style="font-family: ui-monospace, monospace; color: #52525b">#{{ $visit->queue_number }}</span> {{ $visit->patient->name }}</div>
                                    <div style="font-size: 13px; color: #52525b">{{ $visit->practitioner->user->name }} · datang {{ $visit->arrived_at?->format('H:i') }} · <span style="color: #b54708">{{ $visit->waitingMinutes() }} mnt</span></div>
                                </div>
                                {{ ($this->startVisitAction)(['visit' => $visit->id]) }}
                            </div>
                        @empty
                            <div style="color: #52525b; font-size: 13px">Tidak ada pasien menunggu.</div>
                        @endforelse

                        <div style="font-size: 12px; font-weight: 500; color: #71717a; padding-top: 4px">Di kursi</div>
                        @forelse ($inTreatment as $visit)
                            <div style="display: flex; justify-content: space-between; gap: 8px">
                                <div>
                                    <div style="font-weight: 500">{{ $visit->patient->name }}</div>
                                    <div style="font-size: 13px; color: #52525b">{{ $visit->chair?->name ?? 'Kursi ?' }} · sejak {{ $visit->started_at?->format('H:i') }}</div>
                                </div>
                                @if ($canExamine)
                                    <x-filament::link :href="$this->examUrl($visit)" size="sm">Pemeriksaan</x-filament::link>
                                @endif
                            </div>
                        @empty
                            <div style="color: #52525b; font-size: 13px">Belum ada.</div>
                        @endforelse

                        @if (count($done))
                            <div style="font-size: 12px; font-weight: 500; color: #71717a; padding-top: 4px">Selesai hari ini</div>
                            @foreach ($done as $visit)
                                <div style="font-size: 13px; color: #52525b">{{ $visit->patient->name }} · {{ $visit->finished_at?->format('H:i') }}</div>
                            @endforeach
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="Detail janji" compact>
                    @if ($selected)
                        @php [$dotColor, $statusText] = $dot($selected); @endphp
                        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px">
                            <div>
                                <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap">
                                    <a href="{{ \App\Filament\Resources\Patients\PatientResource::getUrl('view', ['record' => $selected->patient]) }}" style="font-size: 16px; font-weight: 600; color: inherit">{{ $selected->patient->name }}</a>
                                    <span style="font-family: ui-monospace, monospace; font-size: 13px; color: #52525b">{{ $selected->patient->rm_number }}</span>
                                </div>
                                <div style="font-size: 13px; color: #52525b">{{ $selected->start_at->format('H:i') }}–{{ $selected->end_at->format('H:i') }} · {{ $selected->chair?->name ?? 'Kursi belum ditentukan' }} · {{ $selected->practitioner->user->name }}</div>
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; margin-top: 4px"><span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $dotColor }}"></span>{{ $statusText }}</div>
                            </div>
                            <dl style="display: grid; grid-template-columns: 90px 1fr; gap: 4px 10px; margin: 0; font-size: 13px">
                                <dt style="color: #71717a">Rencana</dt><dd style="margin: 0">{{ $selected->reason ?: '—' }}</dd>
                                <dt style="color: #71717a">Jenis</dt><dd style="margin: 0">{{ $selected->category?->getLabel() ?? '—' }}</dd>
                                @if ($selected->notes)
                                    <dt style="color: #71717a">Catatan</dt><dd style="margin: 0">{{ $selected->notes }}</dd>
                                @endif
                            </dl>
                            @if ($selected->patient->allergies || $selected->patient->systemic_diseases)
                                <div style="font-size: 13px; color: #b42318; font-weight: 500">
                                    {{ collect([$selected->patient->allergies ? 'Alergi: '.$selected->patient->allergies : null, $selected->patient->systemic_diseases])->filter()->implode(' · ') }}
                                </div>
                            @endif
                            <div style="display: flex; flex-wrap: wrap; gap: 8px">
                                {{ ($this->checkInAppointmentAction)(['appointment' => $selected->id]) }}
                                {{ ($this->confirmAppointmentAction)(['appointment' => $selected->id]) }}
                                {{ ($this->editAppointmentAction)(['appointment' => $selected->id]) }}
                                {{ ($this->noShowAppointmentAction)(['appointment' => $selected->id]) }}
                                {{ ($this->cancelAppointmentAction)(['appointment' => $selected->id]) }}
                                @if ($canExamine && $selected->visit && $selected->visit->status === VisitStatus::InTreatment)
                                    <x-filament::button tag="a" :href="$this->examUrl($selected->visit)" size="sm">Buka pemeriksaan</x-filament::button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div style="font-size: 13px; color: #52525b">Klik kartu janji di papan untuk melihat detail dan mengubah statusnya.</div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
