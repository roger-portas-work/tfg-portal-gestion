<x-filament-widgets::widget>
    <section id="operaciones-por-confirmar" class="idrx-widget-card idrx-anchor">
        <div class="idrx-widget-header">
            <div class="idrx-widget-header-row">
                <div>
                    <h2 class="idrx-widget-title">Operaciones pendientes de confirmar</h2>
                    <p class="idrx-widget-description">
                        {{ $operationWindow['description'] }} que todavía necesitan decisión del gestor.
                    </p>
                </div>
                <a href="{{ $indexUrl }}" class="idrx-link">Ver todas</a>
            </div>
        </div>

        <div class="idrx-card-grid">
            @forelse ($operaciones as $operacion)
                <a href="{{ $operacion['url'] }}" class="idrx-action-card idrx-card-warning">
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
                            @if ($operacion['is_today'])
                                <span class="idrx-badge idrx-badge-warning">Hoy</span>
                            @endif
                            <span class="idrx-badge idrx-badge-warning">Decisión pendiente</span>
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
                            <div class="idrx-meta-label">Piloto</div>
                            <div class="idrx-meta-value">{{ $operacion['pilot'] ?? 'Sin piloto' }}</div>
                        </div>
                        <div>
                            <div class="idrx-meta-label">Dron</div>
                            <div class="idrx-meta-value">{{ $operacion['drone'] ?? 'Sin dron' }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="idrx-empty">
                    No hay operaciones pendientes de confirmar en esta vista.
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
