{{--
    Daftar periksa sebelum rekam medis ditandatangani.
    $items: list<array{label: string, done: bool, required: bool, note?: string}>
    Gaya inline: panel tidak memakai tema Tailwind sendiri, kelas utilitas baru tidak ikut terkompilasi.
--}}
<ul style="display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; font-size: 14px">
    @foreach ($items as $item)
        @php
            $missing = ! $item['done'] && $item['required'];
            $color = $item['done'] ? '#0d6e63' : ($missing ? '#b42318' : '#71717a');
        @endphp
        <li style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid {{ $missing ? '#fecdca' : '#e4e4e7' }}; border-radius: 8px; background: {{ $missing ? '#fef3f2' : '#fff' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="{{ $color }}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex: none">
                <circle cx="12" cy="12" r="9.5" />
                @if ($item['done'])
                    <path d="M7.5 12.5l3 3 6-6.5" />
                @elseif ($missing)
                    <path d="M9 9l6 6M15 9l-6 6" />
                @else
                    <path d="M8 12h8" />
                @endif
            </svg>
            <span style="flex: 1; min-width: 0; font-weight: 500; color: {{ $missing ? '#b42318' : '#18181b' }}">
                {{ $item['label'] }}
                <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap">{{ $item['done'] ? '(sudah)' : '(belum)' }}</span>
            </span>
            <span style="flex: none; font-size: 13px; color: #52525b">
                {{ isset($item['note']) ? $item['note'].' · ' : '' }}{{ $item['required'] ? 'wajib' : 'tidak wajib' }}
            </span>
        </li>
    @endforeach
</ul>
