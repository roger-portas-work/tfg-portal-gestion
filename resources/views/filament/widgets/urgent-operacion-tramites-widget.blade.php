<x-filament-widgets::widget>
    <section id="tramites-vencidos" class="idrx-widget-card idrx-anchor">
        <div class="idrx-widget-header">
            <h2 class="idrx-widget-title">Tramites vencidos</h2>
            <p class="idrx-widget-description">
                Tramites de operaciones confirmadas con fecha limite anterior a hoy y sin fecha de tramitacion.
            </p>
        </div>

        <div class="idrx-list">
            @forelse ($overdueTramites as $tramite)
                <a href="{{ $tramite['url'] }}" class="idrx-row-card idrx-card-danger">
                    <div class="idrx-card-top">
                        <div>
                            <h3 class="idrx-card-title">{{ $tramite['title'] }}</h3>
                            <p class="idrx-card-subtitle">
                                {{ $tramite['operation'] }} - {{ $tramite['cliente'] }}
                            </p>
                        </div>
                        <div class="idrx-badge-row" style="margin-top: 0;">
                            <span class="idrx-badge idrx-badge-danger">{{ $tramite['description'] }}</span>
                            <span class="idrx-pill">Limite {{ $tramite['deadline'] }}</span>
                            @if ($tramite['operation_date'])
                                <span class="idrx-pill">Operacion {{ $tramite['operation_date'] }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="idrx-empty">
                    No hay tramites vencidos pendientes.
                </div>
            @endforelse
        </div>
    </section>

    <section id="tramites-7-dias" class="idrx-widget-card idrx-anchor">
        <div class="idrx-widget-header">
            <h2 class="idrx-widget-title">Tramites para gestionar esta semana</h2>
            <p class="idrx-widget-description">
                Tramites con fecha limite desde hoy hasta los proximos 7 dias, aunque cambies la vista de operaciones.
            </p>
        </div>

        <div class="idrx-list">
            @forelse ($dueSoonTramites as $tramite)
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
                            <span class="idrx-pill">Limite {{ $tramite['deadline'] }}</span>
                            @if ($tramite['operation_date'])
                                <span class="idrx-pill">Operacion {{ $tramite['operation_date'] }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="idrx-empty">
                    No hay tramites con fecha limite dentro de los proximos 7 dias.
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
