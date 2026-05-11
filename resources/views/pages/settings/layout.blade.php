@php
    $cliente = auth()->user()?->cliente;
    $isClientePortal = auth()->user()?->role === \App\Models\User::ROLE_CLIENTE;
    $canAccessDrones = $isClientePortal && $cliente?->profile_completed;
@endphp

<div class="{{ $isClientePortal ? 'w-full' : 'flex items-start max-md:flex-col' }}">

    @unless ($isClientePortal)
        <div class="me-10 w-full pb-4 md:w-[220px]">
            <flux:navlist aria-label="{{ __('Settings') }}">
                @if ($canAccessDrones)
                    <flux:navlist.item :href="route('drones.index')" wire:navigate>Drones</flux:navlist.item>
                @endif
                <flux:navlist.item :href="route('profile.edit')" wire:navigate>
                    {{ $isClientePortal ? 'Mi ficha' : __('Profile') }}
                </flux:navlist.item>

                @unless ($isClientePortal)
                    <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
                    <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
                @endunless
            </flux:navlist>
        </div>

        <flux:separator class="md:hidden" />
    @endunless

    <div class="flex-1 self-stretch max-md:pt-6">
        @unless ($isClientePortal)
            <flux:heading>{{ $heading ?? '' }}</flux:heading>
            <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>
        @endunless

        <div class="{{ $isClientePortal ? 'portal-client-shell' : 'mt-5 w-full max-w-lg' }}">
            {{ $slot }}
        </div>
    </div>
</div>
