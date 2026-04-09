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
    style="
        width: 100%;
        height: {{ $height }};
        background: {{ $trackColor }};
        border-radius: 9999px;
        overflow: hidden;
    "
>
    @if ($progressValue > 0)
        <div
            style="
                width: {{ rtrim(rtrim(number_format($progressValue, 2, '.', ''), '0'), '.') }}%;
                height: 100%;
                background: {{ $fillColor }};
                border-radius: 9999px;
                transition: width 0.4s ease;
            "
        ></div>
    @endif
</div>
