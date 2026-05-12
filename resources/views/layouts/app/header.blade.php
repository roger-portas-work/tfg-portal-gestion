<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            @php
                $cliente = auth()->user()?->cliente;
                $isClientePortal = auth()->user()?->role === \App\Models\User::ROLE_CLIENTE;
                $canAccessDrones = $isClientePortal && $cliente?->profile_completed;
                $canAccessPilotos = $isClientePortal && $cliente?->isUnblocked();
                $canAccessOperaciones = $isClientePortal && $cliente?->isUnblocked();
                $canAccessOperadora = $isClientePortal && $cliente?->isUnblocked();
                $operadoraAttentionCount = $canAccessOperadora
                    ? $cliente->operadoraRequirements()
                        ->where('is_required', true)
                        ->whereIn('status', [
                            \App\Models\OperadoraRequirement::STATUS_PENDING,
                            \App\Models\OperadoraRequirement::STATUS_NEEDS_CHANGES,
                        ])
                        ->count()
                    : 0;
            @endphp

            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                @if ($canAccessOperaciones)
                    <flux:navbar.item icon="clipboard-document-list" :href="route('operaciones.index')" :current="request()->routeIs('operaciones.*')" wire:navigate>
                        Operaciones
                    </flux:navbar.item>
                @endif
                @if ($canAccessOperadora)
                    <flux:navbar.item icon="folder" :href="route('operadora.index')" :current="request()->routeIs('operadora.*')" wire:navigate>
                        <span class="inline-flex items-center gap-2">
                            <span>Operadora</span>
                            @if ($operadoraAttentionCount > 0)
                                <span class="inline-flex min-w-5 justify-center rounded-full bg-amber-500 px-1.5 text-[0.68rem] font-bold leading-5 text-white">
                                    {{ $operadoraAttentionCount }}
                                </span>
                            @endif
                        </span>
                    </flux:navbar.item>
                @endif
                @if ($canAccessDrones)
                    <flux:navbar.item icon="paper-airplane" :href="route('drones.index')" :current="request()->routeIs('drones.*')" wire:navigate>
                        Drones
                    </flux:navbar.item>
                @endif
                @if ($canAccessPilotos)
                    <flux:navbar.item icon="identification" :href="route('pilotos.index')" :current="request()->routeIs('pilotos.*')" wire:navigate>
                        Pilotos
                    </flux:navbar.item>
                @endif
                @if ($isClientePortal)
                    <flux:navbar.item icon="identification" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                        Mi ficha
                    </flux:navbar.item>
                @endif
            </flux:navbar>

            <flux:spacer />

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="$isClientePortal ? 'Portal cliente' : __('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if ($canAccessOperaciones)
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('operaciones.index')" :current="request()->routeIs('operaciones.*')" wire:navigate>
                            Operaciones
                        </flux:sidebar.item>
                    @endif
                    @if ($canAccessOperadora)
                        <flux:sidebar.item icon="folder" :href="route('operadora.index')" :current="request()->routeIs('operadora.*')" wire:navigate>
                            <span class="flex w-full items-center justify-between gap-2">
                                <span>Operadora</span>
                                @if ($operadoraAttentionCount > 0)
                                    <span class="inline-flex min-w-5 justify-center rounded-full bg-amber-500 px-1.5 text-[0.68rem] font-bold leading-5 text-white">
                                        {{ $operadoraAttentionCount }}
                                    </span>
                                @endif
                            </span>
                        </flux:sidebar.item>
                    @endif
                    @if ($canAccessDrones)
                        <flux:sidebar.item icon="paper-airplane" :href="route('drones.index')" :current="request()->routeIs('drones.*')" wire:navigate>
                            Drones
                        </flux:sidebar.item>
                    @endif
                    @if ($canAccessPilotos)
                        <flux:sidebar.item icon="identification" :href="route('pilotos.index')" :current="request()->routeIs('pilotos.*')" wire:navigate>
                            Pilotos
                        </flux:sidebar.item>
                    @endif
                    @if ($isClientePortal)
                        <flux:sidebar.item icon="identification" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                            Mi ficha
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
