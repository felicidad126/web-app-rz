<x-filament-panels::page>
    <style>
        /* Baris ±72 karakter agar nyaman dibaca di layar lebar. */
        .guide-body { font-size: 15px; line-height: 1.6; max-width: 72ch }
        .guide-body h2 { font-size: 17px; font-weight: 600; margin: 4px 0 10px }
        .guide-body p, .guide-body ul, .guide-body ol { margin: 0 0 10px }
        .guide-body ul { list-style: disc; padding-left: 22px }
        .guide-body ol { list-style: decimal; padding-left: 22px }
        .guide-body li { margin: 2px 0 }
        .guide-body code { font-size: 13px; padding: 1px 5px; border-radius: 4px; background: rgba(128,128,128,.15) }
    </style>

    <div x-data="{ tab: @js(array_key_first($sections)) }">
        <x-filament::tabs style="margin-bottom: 16px; overflow-x: auto">
            @foreach ($sections as $role => $section)
                <x-filament::tabs.item :alpine-active="'tab === \''.$role.'\''" x-on:click="tab = '{{ $role }}'">
                    {{ $section['title'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @foreach ($sections as $role => $section)
            <x-filament::section x-show="tab === '{{ $role }}'" x-cloak>
                <div class="guide-body">{!! $section['html'] !!}</div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
