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
            $isUnblocked = $cliente->isUnblocked();
            $pilotosCount = $cliente->pilotos()->count();
            $operacionesCount = $cliente->operaciones()->count();
            $hasOperadoraRequirements = $cliente->operadoraRequirements()->exists();
            $pendingOperadora = $cliente->pendingOperadoraRequirementsCount();
            $completedOperadora = $cliente->completedOperadoraRequirementsCount();
            $completedOnboardingSteps = collect([$profileCompleted, $hasDrones])->filter()->count();
            $onboardingProgress = (int) (($completedOnboardingSteps / 2) * 100);
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
                                            <p class="portal-step-card__text">Este paso se activara cuando tu ficha del cliente este completada.</p>
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
                <div class="portal-hero portal-hero--emerald">
                    <div class="portal-section-split">
                        <div>
                            <p class="portal-hero__eyebrow text-emerald-700 dark:text-emerald-300">Portal cliente</p>
                            <h1 class="portal-hero__title">
                                Hola, {{ $cliente->fullName() ?: $user->name }}
                            </h1>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-700 dark:text-neutral-300">
                                Tu ficha ya esta completada y ya tienes un dron registrado. El onboarding base del portal esta finalizado.
                            </p>

                            <div class="mt-6">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-semibold text-neutral-900 dark:text-white">Progreso del onboarding base</p>
                                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                        100%
                                    </span>
                                </div>

                                <div class="mt-3">
                                    <x-ui.progress-bar
                                        :value="100"
                                        height="12px"
                                        track-color="#d1fae5"
                                        fill-color="linear-gradient(90deg, #10b981 0%, #06b6d4 100%)"
                                    />
                                </div>
                            </div>

                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <flux:button as="a" variant="primary" :href="route('profile.edit')" wire:navigate>
                                    Ver mi ficha
                                </flux:button>

                                <flux:button as="a" variant="filled" :href="route('drones.index')" wire:navigate>
                                    {{ $hasDrones ? 'Ver mis drones' : 'Registrar mi primer dron' }}
                                </flux:button>

                                <span class="portal-badge portal-badge--emerald">
                                    Estado actual: activo
                                </span>
                            </div>
                        </div>

                        <div class="portal-panel portal-panel--soft">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Siguiente bloque disponible</p>
                            <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                                El onboarding base ya esta completado. Ahora puedes empezar a trabajar la documentacion de operadora.
                            </p>

                            <div class="mt-5">
                                <flux:button as="a" variant="primary" :href="route('operadora.index')" wire:navigate>
                                    Ir a Operadora
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="portal-support-grid">
                    <div class="portal-support-card portal-support-card--indigo">
                        <p class="portal-support-card__title">Pilotos</p>
                        <p class="portal-support-card__text">
                            Crea los pilotos que podras asignar despues dentro de cada operacion.
                        </p>

                        <div class="mt-4">
                            <span class="portal-badge portal-badge--indigo">
                                Registrados: {{ $pilotosCount }}
                            </span>
                        </div>

                        <div class="portal-support-card__footer">
                            <flux:button as="a" variant="primary" :href="route('pilotos.index')" wire:navigate>
                                Ir a Pilotos
                            </flux:button>
                        </div>
                    </div>

                    <div class="portal-support-card portal-support-card--emerald">
                        <p class="portal-support-card__title">Operaciones</p>
                        <p class="portal-support-card__text">
                            Crea y gestiona las operaciones vinculando un piloto y un dron de tu expediente.
                        </p>

                        <div class="mt-4">
                            <span class="portal-badge portal-badge--emerald">
                                Registradas: {{ $operacionesCount }}
                            </span>
                        </div>

                        <div class="portal-support-card__footer">
                            <flux:button as="a" variant="primary" :href="route('operaciones.index')" wire:navigate>
                                Ir a Operaciones
                            </flux:button>
                        </div>
                    </div>

                    <div class="portal-support-card portal-support-card--sky">
                        <p class="portal-support-card__title">Documentacion Operadora</p>
                        <p class="portal-support-card__text">
                            Requisitos definidos por el gestor para la documentacion base de operadora.
                        </p>

                        @if ($hasOperadoraRequirements)
                            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                                <span class="portal-badge portal-badge--amber">
                                    Pendientes: {{ $pendingOperadora }}
                                </span>
                                <span class="portal-badge portal-badge--emerald">
                                    Completados: {{ $completedOperadora }}
                                </span>
                            </div>
                        @else
                            <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">
                                Todavia no hay requisitos definidos.
                            </p>
                        @endif

                        <div class="portal-support-card__footer">
                            <flux:button as="a" variant="primary" :href="route('operadora.index')" wire:navigate>
                                Ir a Operadora
                            </flux:button>
                        </div>
                    </div>
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
