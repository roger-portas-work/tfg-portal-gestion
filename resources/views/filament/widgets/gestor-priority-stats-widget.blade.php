<x-filament-widgets::widget>
    <section class="idrx-widget-card idrx-widget-padded">
        <div class="idrx-widget-header-row" style="margin-bottom: 1rem;">
            <div>
                <h2 class="idrx-widget-title">Prioridades del gestor</h2>
                <p class="idrx-widget-description">Resumen compacto de avisos operativos.</p>
            </div>
        </div>

        <div class="idrx-filter-panel">
            <div>
                <div class="idrx-filter-title">Vista de operaciones</div>
                <div class="idrx-filter-description">{{ $operationWindow['description'] }}</div>
            </div>
            <div class="idrx-filter-options" aria-label="Elegir ventana de operaciones del dashboard">
                @foreach ($operationFilterOptions as $option)
                    <a
                        href="{{ $option['url'] }}"
                        class="idrx-filter-option {{ $option['active'] ? 'idrx-filter-option-active' : '' }}"
                    >
                        {{ $option['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="idrx-priority-grid">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}" class="idrx-priority-card idrx-tone-{{ $item['tone'] }}">
                    <div class="idrx-priority-label">{{ $item['label'] }}</div>
                    <div class="idrx-priority-value">{{ $item['value'] }}</div>
                    <div class="idrx-priority-description">{{ $item['description'] }}</div>
                </a>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
