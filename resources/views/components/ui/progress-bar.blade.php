@props([
    'value' => 0,
    'height' => '12px',
    'trackColor' => '#d1fae5',
    'fillColor' => 'linear-gradient(90deg, #10b981 0%, #06b6d4 100%)',
])

@php
    // Dejamos la barra preparada para porcentajes enteros o decimales,
    // de forma que se pueda reutilizar en cualquier progreso del portal.
    $progressValue = max(0, min(100, (float) $value));
@endphp

<div
    class="ui-progress"
    style="--progress-height: {{ $height }}; --progress-track: {{ $trackColor }};"
>
    @if ($progressValue > 0)
        <div
            class="ui-progress__fill"
            style="--progress-value: {{ rtrim(rtrim(number_format($progressValue, 2, '.', ''), '0'), '.') }}%; --progress-fill: {{ $fillColor }};"
        ></div>
    @endif
</div>
