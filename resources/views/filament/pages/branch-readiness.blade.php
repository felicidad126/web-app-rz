<x-filament-panels::page>
    @forelse ($branches as $row)
        <x-filament::section
            :heading="$row['branch']->code.' · '.$row['branch']->name"
            :description="$row['blocking'] === 0 ? 'Siap dipakai' : $row['blocking'].' butir wajib belum terpenuhi'"
            collapsible
            :collapsed="$row['blocking'] === 0"
        >
            <x-slot name="afterHeader">
                <x-filament::badge :color="$row['blocking'] === 0 ? 'success' : 'danger'" :icon="$row['blocking'] === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle'">
                    {{ $row['blocking'] === 0 ? 'Siap' : 'Belum siap' }}
                </x-filament::badge>
            </x-slot>

            {{-- Yang belum terpenuhi di atas (wajib dulu, lalu disarankan); urutan asli dipertahankan di dalam tiap kelompok. --}}
            @php
                $checks = collect($row['checks'])->sortBy(fn (array $c): int => $c['ok'] ? 2 : ($c['level'] === \App\Services\Clinic\BranchReadiness::REQUIRED ? 0 : 1));
            @endphp
            <ul style="list-style: none; margin: 0; padding: 0">
                @foreach ($checks as $check)
                    <li style="display: flex; gap: 12px; align-items: flex-start; padding: 10px 0; border-top: 1px solid rgba(128,128,128,.15)">
                        <span style="flex: none; width: 22px; height: 22px; margin-top: 1px" aria-hidden="true">
                            @if ($check['ok'])
                                <x-filament::icon icon="heroicon-s-check-circle" style="width: 22px; height: 22px; color: #12b76a" />
                            @elseif ($check['level'] === \App\Services\Clinic\BranchReadiness::REQUIRED)
                                <x-filament::icon icon="heroicon-s-x-circle" style="width: 22px; height: 22px; color: #d92d20" />
                            @else
                                <x-filament::icon icon="heroicon-s-exclamation-circle" style="width: 22px; height: 22px; color: #f79009" />
                            @endif
                        </span>
                        <div style="flex: 1; min-width: 0">
                            <div style="font-weight: 600; font-size: 14px">
                                {{ $check['label'] }}
                                <span style="font-weight: 400; font-size: 12px; opacity: .7">· {{ $check['level'] }} · {{ $check['ok'] ? 'Sudah' : 'Belum' }}</span>
                            </div>
                            <div style="font-size: 13px; opacity: .8">{{ $check['detail'] }}</div>
                        </div>
                        @if (! $check['ok'] && $check['url'])
                            <x-filament::link :href="$check['url']" size="sm" style="flex: none; padding: 8px 0">Atur</x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @empty
        <x-filament::section>Belum ada cabang.</x-filament::section>
    @endforelse
</x-filament-panels::page>
