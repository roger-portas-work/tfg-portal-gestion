<x-filament-widgets::widget>
    <section id="tramites-7-dias" class="idrx-widget-card idrx-anchor">
        <div class="idrx-widget-header">
            <h2 class="idrx-widget-title">Trámites para gestionar esta semana</h2>
            <p class="idrx-widget-description">
                Siempre muestra trámites con fecha límite desde hoy hasta los próximos 7 días, aunque cambies la vista de operaciones.
            </p>
        </div>

        <div class="idrx-list">
            @forelse ($tramites as $tramite)
                <a href="{{ $tramite['url'] }}" class="idrx-row-card idrx-card-warning">
                    <div class="idrx-card-top">
                        <div>
                            <h3 class="idrx-card-title">{{ $tramite['title'] }}</h3>
                            <p class="idrx-card-subtitle">
                                {{ $tramite['operation'] }} - {{ $tramite['cliente'] }}
                            </p>
                        </div>
                        <div class="idrx-badge-row" style="margin-top: 0;">
                            <span class="idrx-badge idrx-badge-warning">{{ $tramite['description'] }}</span>
                            <span class="idrx-pill">Límite {{ $tramite['deadline'] }}</span>
                            @if ($tramite['operation_date'])
                                <span class="idrx-pill">Operación {{ $tramite['operation_date'] }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="idrx-empty">
                    No hay trámites con fecha límite dentro de los próximos 7 días.
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
