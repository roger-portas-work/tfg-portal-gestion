<x-filament-widgets::widget>
    <section id="operaciones-hoy" class="idrx-widget-card idrx-anchor">
        <div class="idrx-widget-header">
            <h2 class="idrx-widget-title">Operaciones de hoy</h2>
            <p class="idrx-widget-description">
                Trabajo operativo programado para hoy, con estado y resumen de trámites.
            </p>
        </div>

        <div class="idrx-card-grid">
            @forelse ($operaciones as $operacion)
                <a href="{{ $operacion['url'] }}" class="idrx-action-card idrx-card-{{ $operacion['tone'] }}">
                    <div class="idrx-card-top">
                        <div>
                            <h3 class="idrx-card-title">{{ $operacion['reference'] }}</h3>
                            <p class="idrx-card-subtitle">
                                {{ $operacion['cliente'] }}
                                @if ($operacion['location'])
                                    - {{ $operacion['location'] }}
                                @endif
                            </p>
                        </div>
                        <div class="idrx-badge-row" style="margin-top: 0;">
                            <span class="idrx-badge idrx-badge-warning">Hoy</span>
                            <span class="idrx-badge idrx-badge-{{ $operacion['tone'] }}">
                                {{ $operacion['state'] }}
                            </span>
                        </div>
                    </div>

                    <div class="idrx-meta-grid">
                        <div>
                            <div class="idrx-meta-label">Operación</div>
                            <div class="idrx-meta-value">{{ $operacion['operation_date'] }}</div>
                            @if ($operacion['schedule'])
                                <div class="idrx-meta-label">{{ $operacion['schedule'] }}</div>
                            @endif
                        </div>
                        <div>
                            <div class="idrx-meta-label">Seguimiento</div>
                            <div class="idrx-meta-value">{{ $operacion['description'] }}</div>
                        </div>
                        <div>
                            <div class="idrx-meta-label">Próxima fecha límite</div>
                            <div class="idrx-meta-value">{{ $operacion['next_deadline'] ?? 'Sin fecha' }}</div>
                        </div>
                    </div>

                    <div class="idrx-muted-row">
                        @if ($operacion['pilot'])
                            <span>Piloto: {{ $operacion['pilot'] }}</span>
                        @endif
                        @if ($operacion['drone'])
                            <span>Dron: {{ $operacion['drone'] }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="idrx-empty">
                    No hay operaciones programadas para hoy.
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
