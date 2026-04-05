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
            $hasOperadoraRequirements = $cliente->operadoraRequirements()->exists();
            $pendingOperadora = $cliente->pendingOperadoraRequirementsCount();
            $completedOperadora = $cliente->completedOperadoraRequirementsCount();
        @endphp

        <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
            @if (! $profileCompleted)
                <div class="overflow-hidden rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50 shadow-sm dark:border-amber-800/70 dark:from-amber-950/40 dark:via-neutral-900 dark:to-orange-950/30">
                    <div class="grid gap-6 p-6 md:grid-cols-[1.5fr_1fr] md:p-8">
                        <div>
                            <p class="text-sm uppercase tracking-[0.25em] text-amber-700 dark:text-amber-300">Portal cliente</p>
                            <h1 class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">
                                Hola, {{ $cliente->fullName() ?: $user->name }}
                            </h1>
                            <p class="mt-4 max-w-2xl text-sm text-neutral-700 dark:text-neutral-300">
                                Tu cuenta ya esta creada, pero el portal sigue bloqueado hasta que completes tu ficha de cliente.
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <flux:button as="a" variant="primary" :href="route('profile.edit')" wire:navigate>
                                    Completar mi ficha
                                </flux:button>

                                <span class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                    Estado actual: bloqueado
                                </span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-200/80 bg-white/80 p-5 backdrop-blur dark:border-amber-800/70 dark:bg-neutral-950/50">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Para empezar</p>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/70 dark:bg-amber-900/20">
                                    <div class="mt-0.5 size-5 rounded-full border-2 border-amber-500"></div>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-900 dark:text-white">Completar ficha del cliente</p>
                                        <p class="text-xs text-neutral-600 dark:text-neutral-300">Pendiente</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 rounded-2xl border border-neutral-200 bg-neutral-50 p-4 opacity-70 dark:border-neutral-700 dark:bg-neutral-900/40">
                                    <div class="mt-0.5 size-5 rounded-full border-2 border-neutral-300 dark:border-neutral-600"></div>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-900 dark:text-white">Registrar 1 dron</p>
                                        <p class="text-xs text-neutral-600 dark:text-neutral-300">Siguiente paso del MVP</p>
                                    </div>
                                </div>
                            </div>

                            <p class="mt-4 text-xs text-neutral-600 dark:text-neutral-300">
                                Completa la ficha del cliente para desbloquear el siguiente bloque.
                            </p>
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
                                Tu ficha ya esta completada. El acceso base al portal ya esta listo para continuar con el siguiente bloque del proyecto.
                            </p>

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

                        <div class="rounded-2xl border border-emerald-200/80 bg-white/80 p-5 backdrop-blur dark:border-emerald-800/70 dark:bg-neutral-950/50">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Progreso del onboarding</p>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800/70 dark:bg-emerald-900/20">
                                    <div class="mt-0.5 flex size-5 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                        {!! '&check;' !!}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-900 dark:text-white">Ficha del cliente completada</p>
                                        <p class="text-xs text-neutral-600 dark:text-neutral-300">Correcto</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 rounded-2xl border {{ $hasDrones ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800/70 dark:bg-emerald-900/20' : 'border-sky-200 bg-sky-50 dark:border-sky-800/70 dark:bg-sky-900/20' }} p-4">
                                    @if ($hasDrones)
                                        <div class="mt-0.5 flex size-5 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                            {!! '&check;' !!}
                                        </div>
                                    @else
                                        <div class="mt-0.5 size-5 rounded-full border-2 border-sky-500"></div>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-neutral-900 dark:text-white">Registrar 1 dron</p>
                                        <p class="text-xs text-neutral-600 dark:text-neutral-300">
                                            {{ $hasDrones ? 'Correcto' : 'Siguiente paso del MVP' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <p class="mt-4 text-xs text-neutral-600 dark:text-neutral-300">
                                @if ($hasDrones)
                                    Ya tienes al menos un dron registrado. Ahora puedes trabajar en la documentacion de operadora.
                                @else
                                    El siguiente bloque que construiremos en el portal sera la gestion de drones.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
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

                    <div class="rounded-3xl border border-dashed border-neutral-300 bg-neutral-50 p-6 dark:border-neutral-700 dark:bg-neutral-950/30">
                        <p class="text-sm font-semibold text-neutral-900 dark:text-white">Documentacion Pilotos</p>
                        <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                            Sera el siguiente bloque a construir. Mantendremos el mismo enfoque que en Operadora: requisitos definidos por el gestor y completados por el cliente.
                        </p>
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
