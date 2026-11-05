@php
    /** @var array $teeth  hasil OdontogramChart::teeth() */
    $rows = \App\Support\Teeth::ROWS;
    $upper = fn (string $t): bool => in_array((int) $t[0], [1, 2, 5, 6], true);
@endphp
<table style="border-collapse: separate; border-spacing: 3px; margin: 0 auto; font-family: DejaVu Sans, ui-sans-serif, sans-serif">
    @foreach ($rows as $index => $row)
        @php $half = intdiv(count($row), 2); @endphp
        <tr>
            @if ($index === 1 || $index === 2)
                @for ($i = 0; $i < 3; $i++)<td></td>@endfor
            @endif
            @foreach ($row as $i => $tooth)
                @php
                    $state = $teeth[(int) $tooth] ?? ['current' => [], 'planned' => []];
                    $current = collect($state['current'])->map(fn ($e) => $e->condition_code.($e->surface ? ' '.$e->surface->value : ''));
                    $planned = collect($state['planned'])->map(fn ($e) => $e->condition_code.($e->surface ? ' '.$e->surface->value : ''));
                    $primary = ! \App\Support\Teeth::isPermanent((string) $tooth);
                @endphp
                <td style="width: 46px; padding: 0; vertical-align: {{ $upper((string) $tooth) ? 'top' : 'bottom' }}; {{ $i === $half ? 'padding-left: 10px;' : '' }}">
                    <div style="border: 1px solid {{ $primary ? '#d4d4d8' : '#71717a' }}; border-radius: 4px; min-height: 52px; padding: 2px 3px; text-align: center; background: {{ $current->isEmpty() && $planned->isEmpty() ? '#fff' : '#fafafa' }}">
                        <div style="font-size: 10px; font-weight: bold; color: {{ $primary ? '#a1a1aa' : '#3f3f46' }}">{{ $tooth }}</div>
                        @foreach ($current as $code)
                            <div style="font-size: 9px; color: #18181b; line-height: 1.2">{{ $code }}</div>
                        @endforeach
                        @foreach ($planned as $code)
                            <div style="font-size: 9px; color: #b42318; line-height: 1.2">{{ $code }}</div>
                        @endforeach
                    </div>
                </td>
            @endforeach
        </tr>
    @endforeach
</table>
