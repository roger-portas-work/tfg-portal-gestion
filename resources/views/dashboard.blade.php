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
            $hasOperadoraRequirements = $cliente->operadoraRequirements()->exists();
            $pendingOperadora = $cliente->pendingOperadoraRequirementsCount();
            $completedOperadora = $cliente->completedOperadoraRequirementsCount();
            $completedOnboardingSteps = collect([$profileCompleted, $hasDrones])->filter()->count();
            $onboardingProgress = (int) (($completedOnboardingSteps / 2) * 100);
        @endphp

        <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
            @if (! $isUnblocked)
                <div class="overflow-hidden rounded-3xl border border-red-300 bg-gradient-to-br from-red-50 via-white to-rose-100 shadow-sm dark:border-red-800/70 dark:from-red-950/40 dark:via-neutral-900 dark:to-rose-950/30">
                    <div class="grid gap-6 p-7 md:grid-cols-[1.6fr_1fr] md:p-10">
                        <div class="rounded-[2rem] border border-white/70 bg-white/85 p-8 shadow-sm backdrop-blur dark:border-white/10 dark:bg-neutral-950/45">
                            <div class="flex flex-col gap-8 md:gap-10">
                                <div class="flex flex-wrap items-center gap-4">
                                    <span class="rounded-full border border-red-400 bg-red-100 px-4 py-2 text-sm font-semibold text-red-800 dark:border-red-700 dark:bg-red-900/40 dark:text-red-200">
                                        Portal bloqueado
                                    </span>
                                    <span class="rounded-full border border-neutral-200 bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                                        {{ $completedOnboardingSteps }}/2 pasos completados
                                    </span>
                                </div>

                                <div class="space-y-5">
                                    <p class="text-sm uppercase tracking-[0.28em] text-red-700 dark:text-red-300">Portal cliente</p>
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
                                        <span class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-red-700 shadow-sm dark:bg-neutral-900 dark:text-red-300">
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

                                    <span class="rounded-full border border-amber-300 bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                        {{ $profileCompleted ? 'Siguiente accion: dron' : 'Accion requerida ahora' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-[2rem] border border-red-200/80 bg-white/90 p-6 backdrop-blur dark:border-red-800/70 dark:bg-neutral-950/50">
                                <p class="text-base font-semibold text-neutral-900 dark:text-white">Pasos para activar tu portal</p>
                                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                                    Sigue este orden para desbloquear el resto del portal.
                                </p>
                            </div>

                            <div class="rounded-[2rem] border {{ $profileCompleted ? 'border-emerald-200 dark:border-emerald-800/70' : 'border-amber-200 dark:border-amber-800/70' }} bg-white/90 p-6 shadow-sm dark:bg-neutral-950/50">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-4">
                                        <div class="flex size-12 items-center justify-center rounded-2xl {{ $profileCompleted ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-300/70 dark:bg-emerald-500 dark:text-white' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-lg font-semibold text-neutral-900 dark:text-white">Completar ficha del cliente</p>
                                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                                {{ $profileCompleted ? 'Ficha completada correctamente.' : 'Añade tus datos personales y la informacion base para activar el portal.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <span class="rounded-full border {{ $profileCompleted ? 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }} px-3 py-1 text-xs font-semibold">
                                        {{ $profileCompleted ? 'Completada' : 'Pendiente' }}
                                    </span>
                                </div>

                                @if (! $profileCompleted)
                                    <div class="mt-5">
                                        <flux:button as="a" variant="primary" :href="route('profile.edit')" wire:navigate>
                                            Completar ahora
                                        </flux:button>
                                    </div>
                                @endif
                            </div>

                            <div class="rounded-[2rem] border border-neutral-200 bg-neutral-50/90 p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900/40">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-4">
                                        <div class="flex size-12 items-center justify-center rounded-2xl {{ $profileCompleted ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-lg font-semibold text-neutral-900 dark:text-white">Registrar 1 dron</p>
                                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">Este paso se activara cuando tu ficha del cliente este completada.</p>
                                        </div>
                                    </div>

                                    <span class="rounded-full border {{ $profileCompleted ? 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200' : 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-900/40 dark:text-red-200' }} px-3 py-1 text-xs font-semibold">
                                        {{ $profileCompleted ? 'Pendiente' : 'Bloqueado' }}
                                    </span>
                                </div>

                                @if ($profileCompleted)
                                    <div class="mt-5">
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
                <div class="overflow-hidden rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-sky-50 shadow-sm dark:border-emerald-800/70 dark:from-emerald-950/30 dark:via-neutral-900 dark:to-sky-950/30">
                    <div class="grid gap-6 p-6 md:grid-cols-[1.5fr_1fr] md:p-8">
                        <div>
                            <p class="text-sm uppercase tracking-[0.25em] text-emerald-700 dark:text-emerald-300">Portal cliente</p>
                            <h1 class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">
                                Hola, {{ $cliente->fullName() ?: $user->name }}
                            </h1>
                            <p class="mt-4 max-w-2xl text-sm text-neutral-700 dark:text-neutral-300">
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

                                <span class="rounded-full border border-emerald-300 bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                    Estado actual: activo
                                </span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-cyan-200/80 bg-white/80 p-5 backdrop-blur dark:border-cyan-800/70 dark:bg-neutral-950/50">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Siguiente bloque disponible</p>
                            <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-300">
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

                <div class="grid gap-4 md:grid-cols-1">
                    <div class="rounded-3xl border border-cyan-200 bg-white p-6 shadow-sm dark:border-cyan-800/60 dark:bg-neutral-900">
                        <p class="text-sm font-semibold text-neutral-900 dark:text-white">Documentacion Operadora</p>
                        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                            Requisitos definidos por el gestor para la documentacion base de operadora.
                        </p>

                        @if ($hasOperadoraRequirements)
                            <div class="mt-4 flex gap-3 text-sm">
                                <span class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                    Pendientes: {{ $pendingOperadora }}
                                </span>
                                <span class="rounded-full border border-emerald-300 bg-emerald-100 px-3 py-1 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                    Completados: {{ $completedOperadora }}
                                </span>
                            </div>
                        @else
                            <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">
                                Todavia no hay requisitos definidos.
                            </p>
                        @endif

                        <div class="mt-5">
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
