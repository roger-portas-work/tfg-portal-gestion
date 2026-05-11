<x-layouts::app :title="__('Dashboard')">
    @php
        $user = auth()->user();
        $cliente = $user?->cliente;
        $isClientePortal = $user?->role === \App\Models\User::ROLE_CLIENTE && $cliente;
    @endphp

    @if ($isClientePortal)
        @php
            $profileCompleted = $cliente->profile_completed;
            $hasDrones = $cliente->drones()->exists();
            $dronesCount = $cliente->drones()->count();
            $isUnblocked = $cliente->isUnblocked();
            $pilotosCount = $cliente->pilotos()->count();
            $pendingOperaciones = $cliente->pendingOperacionesCount();
            $rejectedOperaciones = $cliente->rejectedOperacionesCount();
            $confirmedOperaciones = $cliente->confirmedOperacionesCount();
            $operadoraRequirements = $cliente->operadoraRequirements()->get();
            $requiredOperadoraRequirements = $operadoraRequirements->filter(fn ($requirement): bool => (bool) $requirement->is_required);
            $pendingOperadora = $operadoraRequirements->filter(fn ($requirement): bool => $requirement->status !== \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $pendingRequiredOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status !== \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $pendingOptionalOperadora = $operadoraRequirements->filter(fn ($requirement): bool => ! $requirement->is_required && $requirement->status !== \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $completedOperadora = $operadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $completedRequiredOperadora = $requiredOperadoraRequirements->filter(fn ($requirement): bool => $requirement->status === \App\Models\OperadoraRequirement::STATUS_APPROVED)->count();
            $completedOnboardingSteps = collect([$profileCompleted, $hasDrones])->filter()->count();
            $onboardingProgress = (int) (($completedOnboardingSteps / 2) * 100);
            $operacionesCount = $cliente->operaciones()->count();
            $totalOperadora = $operadoraRequirements->count();
            $totalRequiredOperadora = $requiredOperadoraRequirements->count();
            $operadoraProgress = $totalRequiredOperadora > 0 ? (int) round(($completedRequiredOperadora / $totalRequiredOperadora) * 100) : 0;
            $dashboardOperadoraTasks = $operadoraRequirements
                ->filter(fn ($requirement): bool => $requirement->status !== \App\Models\OperadoraRequirement::STATUS_APPROVED)
                ->sortBy(function ($requirement): string {
                    $statusOrder = [
                        \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 0,
                        \App\Models\OperadoraRequirement::STATUS_PENDING => 1,
                        \App\Models\OperadoraRequirement::STATUS_IN_REVIEW => 2,
                    ];

                    return sprintf(
                        '%d-%d-%010d',
                        $requirement->is_required ? 0 : 1,
                        $statusOrder[$requirement->status] ?? 9,
                        $requirement->id
                    );
                })
                ->take(4);
            $upcomingOperaciones = $cliente->operaciones()
                ->with(['piloto', 'dron'])
                ->whereDate('operation_date', '>=', now()->toDateString())
                ->orderBy('operation_date')
                ->limit(3)
                ->get();
        @endphp

        <div class="portal-page">
            @if (! $isUnblocked)
                <div class="portal-hero portal-hero--danger">
                    <div class="portal-dashboard-split">
                        <div class="portal-dashboard-main">
                            <div class="flex flex-col gap-8 md:gap-10">
                                <div class="flex flex-wrap items-center gap-4">
                                    <span class="portal-chip portal-chip--danger">
                                        Portal bloqueado
                                    </span>
                                    <span class="portal-chip portal-chip--neutral">
                                        {{ $completedOnboardingSteps }}/2 pasos completados
                                    </span>
                                </div>

                                <div class="space-y-5">
                                    <p class="portal-hero__eyebrow text-red-700 dark:text-red-300">Portal cliente</p>
                                    <h1 class="text-4xl font-semibold text-neutral-900 dark:text-white">
                                        Hola, {{ $cliente->fullName() ?: $user->name }}
                                    </h1>
                                    <p class="max-w-2xl text-lg leading-8 text-neutral-700 dark:text-neutral-300">
                                        {{ $profileCompleted
                                            ? 'Tu ficha ya esta completada. Ahora registra tu primer dron para terminar de activar el portal.'
                                            : 'Completa tu ficha para activar tu portal y poder registrar tu primer dron.' }}
                                    </p>
                                </div>

                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                        <span>Progreso de activacion</span>
                                        <span class="portal-chip portal-chip--danger">
                                            {{ $onboardingProgress }}%
                                        </span>
                                    </div>

                                    <div class="mt-3">
                                        <x-ui.progress-bar
                                            :value="$onboardingProgress"
                                            height="12px"
                                            track-color="#fee2e2"
                                            fill-color="linear-gradient(90deg, #f97316 0%, #ef4444 100%)"
                                        />
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-4">
                                    <flux:button
                                        as="a"
                                        variant="primary"
                                        :href="$profileCompleted ? route('drones.index') : route('profile.edit')"
                                        wire:navigate
                                    >
                                        {{ $profileCompleted ? 'Registrar mi primer dron' : 'Completar mi ficha' }}
                                    </flux:button>

                                    <span class="portal-chip portal-chip--warning">
                                        {{ $profileCompleted ? 'Siguiente accion: dron' : 'Accion requerida ahora' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="portal-dashboard-aside">
                            <div class="portal-panel portal-panel--soft">
                                <p class="text-base font-semibold text-neutral-900 dark:text-white">Pasos para activar tu portal</p>
                                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                    Sigue este orden para desbloquear el resto del portal.
                                </p>
                            </div>

                            <div class="{{ $profileCompleted ? 'portal-step-card portal-step-card--complete' : 'portal-step-card portal-step-card--pending' }}">
                                <div class="portal-step-card__row">
                                    <div class="portal-step-card__lead">
                                        <div class="portal-step-card__icon {{ $profileCompleted ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-300/70 dark:bg-emerald-500 dark:text-white' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="portal-step-card__title">Completar ficha del cliente</p>
                                            <p class="portal-step-card__text">
                                                {{ $profileCompleted ? 'Ficha completada correctamente.' : 'Anade tus datos personales y la informacion base para activar el portal.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <span class="{{ $profileCompleted ? 'portal-chip portal-chip--success' : 'portal-chip portal-chip--warning' }}">
                                        {{ $profileCompleted ? 'Completada' : 'Pendiente' }}
                                    </span>
                                </div>

                                @if (! $profileCompleted)
                                    <div class="portal-step-card__footer">
                                        <flux:button as="a" variant="primary" :href="route('profile.edit')" wire:navigate>
                                            Completar ahora
                                        </flux:button>
                                    </div>
                                @endif
                            </div>

                            <div class="portal-step-card portal-step-card--muted">
                                <div class="portal-step-card__row">
                                    <div class="portal-step-card__lead">
                                        <div class="portal-step-card__icon bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="portal-step-card__title">Registrar 1 dron</p>
                                            <p class="portal-step-card__text">
                                                {{ $profileCompleted
                                                    ? 'Ya puedes registrar tu primer dron para desbloquear el resto del portal.'
                                                    : 'Este paso se activara cuando tu ficha del cliente este completada.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <span class="{{ $profileCompleted ? 'portal-chip portal-chip--warning' : 'portal-chip portal-chip--danger' }}">
                                        {{ $profileCompleted ? 'Pendiente' : 'Bloqueado' }}
                                    </span>
                                </div>

                                @if ($profileCompleted)
                                    <div class="portal-step-card__footer">
                                        <flux:button as="a" variant="primary" :href="route('drones.index')" wire:navigate>
                                            Registrar ahora
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="portal-hero portal-hero--client portal-dashboard-hero">
                    <div class="portal-hero__row">
                        <div>
                            <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                            <h1 class="portal-hero__title">
                                Hola, {{ $cliente->fullName() ?: $user->name }}
                            </h1>
                            <p class="portal-hero__text">
                                Resumen general de tu expediente: drones, pilotos, operaciones y documentacion de operadora.
                            </p>

                            <div class="portal-dashboard-hero__chips">
                                <span class="portal-badge portal-badge--emerald">Portal activo</span>
                                <span class="portal-badge portal-badge--sky">{{ $operacionesCount }} operaciones</span>
                                <span class="portal-badge {{ $pendingRequiredOperadora > 0 ? 'portal-badge--danger' : 'portal-badge--emerald' }}">
                                    Obligatorios pendientes: {{ $pendingRequiredOperadora }}
                                </span>
                            </div>
                        </div>

                        <div class="portal-hero__aside">
                            <div class="portal-hero__brand">
                                <img
                                    src="{{ asset('images/logo-idronlex.png') }}"
                                    alt="Idron Lex & Consulting"
                                    class="portal-hero__logo"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="portal-dashboard-summary-grid">
                    <a href="{{ route('drones.index') }}" wire:navigate class="portal-dashboard-summary-card">
                        <span class="portal-dashboard-summary-card__icon portal-dashboard-summary-card__icon--sky">
                            <flux:icon icon="paper-airplane" variant="mini" class="size-5" />
                        </span>
                        <span>
                            <span class="portal-dashboard-summary-card__label">Drones</span>
                            <strong>{{ $dronesCount }}</strong>
                            <span class="portal-dashboard-summary-card__text">Registrados en tu expediente</span>
                        </span>
                    </a>

                    <a href="{{ route('pilotos.index') }}" wire:navigate class="portal-dashboard-summary-card">
                        <span class="portal-dashboard-summary-card__icon portal-dashboard-summary-card__icon--indigo">
                            <flux:icon icon="identification" variant="mini" class="size-5" />
                        </span>
                        <span>
                            <span class="portal-dashboard-summary-card__label">Pilotos</span>
                            <strong>{{ $pilotosCount }}</strong>
                            <span class="portal-dashboard-summary-card__text">Disponibles para operar</span>
                        </span>
                    </a>

                    <a href="{{ route('operaciones.index') }}" wire:navigate class="portal-dashboard-summary-card">
                        <span class="portal-dashboard-summary-card__icon portal-dashboard-summary-card__icon--emerald">
                            <flux:icon icon="clipboard-document-list" variant="mini" class="size-5" />
                        </span>
                        <span>
                            <span class="portal-dashboard-summary-card__label">Operaciones</span>
                            <strong>{{ $operacionesCount }}</strong>
                            <span class="portal-dashboard-summary-card__text">
                                {{ $confirmedOperaciones }} confirmadas, {{ $pendingOperaciones }} pendientes, {{ $rejectedOperaciones }} rechazadas
                            </span>
                        </span>
                    </a>

                    <a href="{{ route('operadora.index') }}" wire:navigate class="portal-dashboard-summary-card">
                        <span class="portal-dashboard-summary-card__icon {{ $pendingRequiredOperadora > 0 ? 'portal-dashboard-summary-card__icon--danger' : 'portal-dashboard-summary-card__icon--emerald' }}">
                            <flux:icon icon="folder" variant="mini" class="size-5" />
                        </span>
                        <span>
                            <span class="portal-dashboard-summary-card__label">Operadora</span>
                            <strong>{{ $operadoraProgress }}%</strong>
                            <span class="portal-dashboard-summary-card__text">
                                {{ $completedRequiredOperadora }} de {{ $totalRequiredOperadora }} obligatorios aprobados
                            </span>
                        </span>
                    </a>
                </div>

                <div class="portal-dashboard-layout">
                    <section class="portal-panel portal-dashboard-next">
                        <div class="portal-dashboard-section-header">
                            <div>
                                <p class="portal-dashboard-section-header__eyebrow">Agenda</p>
                                <h2 class="portal-dashboard-section-header__title">Proximas operaciones</h2>
                            </div>
                            <flux:button as="a" variant="primary" :href="route('operaciones.index')" wire:navigate>
                                Ver operaciones
                            </flux:button>
                        </div>

                        @forelse ($upcomingOperaciones as $operacion)
                            @php
                                $operationDate = $operacion->operation_date instanceof \DateTimeInterface
                                    ? $operacion->operation_date->format('d/m/Y')
                                    : (filled($operacion->operation_date) ? \Illuminate\Support\Carbon::parse((string) $operacion->operation_date)->format('d/m/Y') : 'Sin fecha');
                                $operationAddress = trim(collect([
                                    $operacion->address ?: $operacion->location,
                                    $operacion->city,
                                    $operacion->province,
                                ])->filter()->implode(', ')) ?: 'Sin direccion';
                                $operationBadge = $operacion->isConfirmed()
                                    ? 'portal-badge--emerald'
                                    : ($operacion->isRejected() ? 'portal-badge--danger' : 'portal-badge--amber');
                            @endphp

                            <article class="portal-dashboard-operation">
                                <div class="portal-dashboard-operation__date">
                                    <span>{{ $operationDate }}</span>
                                    <small>{{ $operacion->estimated_filming_schedule ?: 'Hora sin definir' }}</small>
                                </div>
                                <div class="portal-dashboard-operation__body">
                                    <h3>{{ $operacion->reference }}</h3>
                                    <p>{{ $operationAddress }}</p>
                                    <div class="portal-dashboard-operation__meta">
                                        <span>{{ $operacion->piloto?->fullName() ?? 'Sin piloto' }}</span>
                                        <span>{{ trim(($operacion->dron?->manufacturer_name ?? '').' '.($operacion->dron?->model ?? '')) ?: 'Sin dron' }}</span>
                                    </div>
                                </div>
                                <span class="portal-badge {{ $operationBadge }}">
                                    {{ $operacion->statusLabel() }}
                                </span>
                            </article>
                        @empty
                            <div class="portal-empty-state">
                                Todavia no tienes operaciones futuras programadas.
                            </div>
                        @endforelse
                    </section>

                    <aside class="portal-panel portal-dashboard-guide">
                        <div class="portal-dashboard-section-header">
                            <div>
                                <p class="portal-dashboard-section-header__eyebrow">Guia rapida</p>
                                <h2 class="portal-dashboard-section-header__title">Que puedes hacer ahora</h2>
                            </div>
                        </div>

                        <div class="portal-dashboard-guide__list">
                            <a href="{{ route('operaciones.index') }}" wire:navigate class="portal-dashboard-guide__item">
                                <span class="portal-dashboard-guide__icon portal-dashboard-guide__icon--emerald">
                                    <flux:icon icon="plus" variant="mini" class="size-4" />
                                </span>
                                <span>
                                    <strong>Crear una operacion</strong>
                                    <small>Vincula un piloto y un dron para enviar la solicitud.</small>
                                </span>
                            </a>

                            <a href="{{ route('operadora.index') }}" wire:navigate class="portal-dashboard-guide__item">
                                <span class="portal-dashboard-guide__icon {{ $pendingRequiredOperadora > 0 ? 'portal-dashboard-guide__icon--danger' : 'portal-dashboard-guide__icon--emerald' }}">
                                    <flux:icon icon="folder" variant="mini" class="size-4" />
                                </span>
                                <span>
                                    <strong>Revisar documentacion</strong>
                                    <small>{{ $pendingOperadora }} requisitos pendientes de operadora.</small>
                                </span>
                            </a>

                            <a href="{{ route('profile.edit') }}" wire:navigate class="portal-dashboard-guide__item">
                                <span class="portal-dashboard-guide__icon portal-dashboard-guide__icon--sky">
                                    <flux:icon icon="identification" variant="mini" class="size-4" />
                                </span>
                                <span>
                                    <strong>Actualizar mi ficha</strong>
                                    <small>Modifica tus datos base cuando lo necesites.</small>
                                </span>
                            </a>
                        </div>

                        <div class="portal-dashboard-doc-summary">
                            <div class="portal-dashboard-doc-summary__header">
                                <span>Documentacion operadora</span>
                                <strong>{{ $operadoraProgress }}%</strong>
                            </div>
                            <x-ui.progress-bar
                                :value="$operadoraProgress"
                                height="10px"
                                track-color="#e5e7eb"
                                fill-color="linear-gradient(90deg, #10b981 0%, #06b6d4 100%)"
                            />

                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($pendingOperadora === 0)
                                    <span class="portal-badge portal-badge--amber">
                                        Pendientes: 0
                                    </span>
                                @else
                                    <span class="portal-badge portal-badge--danger">
                                        Obligatorios pendientes: {{ $pendingRequiredOperadora }}
                                    </span>
                                    <span class="portal-badge portal-badge--amber">
                                        Opcionales pendientes: {{ $pendingOptionalOperadora }}
                                    </span>
                                @endif
                                <span class="portal-badge portal-badge--emerald">
                                    Completados: {{ $completedOperadora }}
                                </span>
                            </div>

                            <div class="portal-dashboard-doc-summary__list">
                                @forelse ($dashboardOperadoraTasks as $requirement)
                                    @php
                                        $requirementTone = match ($requirement->status) {
                                            \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 'danger',
                                            \App\Models\OperadoraRequirement::STATUS_IN_REVIEW => 'warning',
                                            default => 'neutral',
                                        };
                                        $requirementLabel = match ($requirement->status) {
                                            \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES => 'Corregir',
                                            \App\Models\OperadoraRequirement::STATUS_IN_REVIEW => 'En revision',
                                            default => 'Pendiente',
                                        };
                                    @endphp

                                    <a href="{{ route('operadora.index') }}" wire:navigate class="portal-dashboard-doc-task portal-dashboard-doc-task--{{ $requirementTone }}">
                                        <span>
                                            <strong>{{ $requirement->name }}</strong>
                                            <small>{{ $requirement->is_required ? 'Obligatorio' : 'Opcional' }}</small>
                                        </span>
                                        <span class="portal-badge {{ $requirementTone === 'danger' ? 'portal-badge--danger' : ($requirementTone === 'warning' ? 'portal-badge--amber' : 'portal-badge--neutral') }}">
                                            {{ $requirementLabel }}
                                        </span>
                                    </a>
                                @empty
                                    <div class="portal-dashboard-doc-task portal-dashboard-doc-task--success">
                                        <span>
                                            <strong>Sin documentos pendientes</strong>
                                            <small>Los requisitos de operadora estan al dia.</small>
                                        </span>
                                        <span class="portal-badge portal-badge--emerald">Al dia</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </aside>
                </div>
            @endif
        </div>
    @else
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
            <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
    @endif
</x-layouts::app>
