<x-filament-widgets::widget>
    <section id="operadora-revision" class="idrx-widget-card idrx-anchor">
        <div class="idrx-widget-header">
            <h2 class="idrx-widget-title">Requisitos de operadora en revisión</h2>
            <p class="idrx-widget-description">
                Entregas del cliente pendientes de aprobar o devolver con correcciones.
            </p>
        </div>

        <div class="idrx-card-grid">
            @forelse ($requirements as $requirement)
                <a href="{{ $requirement['url'] }}" class="idrx-action-card idrx-card-info">
                    <div class="idrx-card-top">
                        <div>
                            <h3 class="idrx-card-title">{{ $requirement['name'] }}</h3>
                            <p class="idrx-card-subtitle">{{ $requirement['cliente'] }}</p>
                        </div>
                        <span class="idrx-badge idrx-badge-info">En revisión</span>
                    </div>

                    <div class="idrx-badge-row">
                        <span class="idrx-pill">{{ $requirement['type'] }}</span>
                        <span class="idrx-pill">{{ $requirement['is_required'] ? 'Obligatorio' : 'Opcional' }}</span>
                        <span class="idrx-pill">Entregado {{ $requirement['submitted_at'] }}</span>
                    </div>
                </a>
            @empty
                <div class="idrx-empty">
                    No hay requisitos en revisión.
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
