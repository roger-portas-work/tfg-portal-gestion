<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    public string $name = '';
    public string $last_name = '';
    public string $second_last_name = '';
    public string $email = '';
    public string $personal_email = '';
    public string $phone = '';
    public string $dni = '';
    public string $address = '';
    public string $country = '';
    public string $city = '';
    public string $province = '';
    public string $postal_code = '';
    public string $birth_date = '';

    public ?Cliente $cliente = null;

    /**
     * Cargamos tanto el usuario autenticado como su ficha de cliente.
     * Asi la misma pantalla puede servir para gestor y para cliente.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->cliente = $user->cliente;

        if (! $this->cliente) {
            return;
        }

        $this->name = $this->cliente->name ?? '';
        $this->last_name = $this->cliente->last_name ?? '';
        $this->second_last_name = $this->cliente->second_last_name ?? '';
        $this->email = $this->cliente->email ?? $user->email;
        $this->personal_email = $this->cliente->personal_email ?? $this->cliente->email ?? $user->email;
        $this->phone = $this->cliente->phone ?? '';
        $this->dni = $this->cliente->dni ?? '';
        $this->address = $this->cliente->address ?? '';
        $this->country = $this->cliente->country ?? '';
        $this->city = $this->cliente->city ?? '';
        $this->province = $this->cliente->province ?? '';
        $this->postal_code = $this->cliente->postal_code ?? '';
        $this->birth_date = match (true) {
            $this->cliente->birth_date instanceof \DateTimeInterface => $this->cliente->birth_date->format('Y-m-d'),
            filled($this->cliente->birth_date) => Carbon::parse($this->cliente->birth_date)->format('Y-m-d'),
            default => '',
        };
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        if (! $this->isClientePortal()) {
            $validated = $this->validate($this->userRules($user->id));

            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            $this->dispatch('profile-updated', name: $user->name);

            return;
        }

        if (! $this->cliente) {
            return;
        }

        $validated = $this->validate($this->clienteRules($user->id));

        // Guardamos primero la ficha del cliente y calculamos a partir de ella
        // si ya esta completa segun el tipo fisico o juridico.
        $this->cliente->fill($validated);
        $this->cliente->profile_completed = $this->cliente->profileIsComplete([
            ...$this->cliente->attributesToArray(),
            ...$validated,
        ]);
        $this->cliente->save();

        $user->fill([
            'name' => trim(implode(' ', array_filter([
                $validated['name'],
                $validated['last_name'],
                $validated['second_last_name'] ?? null,
            ]))),
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);

        // En el flujo del cliente, despues de guardar la ficha
        // tiene mas sentido volver al dashboard para continuar.
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! $this->isClientePortal() && (
            ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail())
        );
    }

    #[Computed]
    public function profileHeading(): string
    {
        return $this->isClientePortal() ? 'Mi ficha' : 'Profile';
    }

    #[Computed]
    public function profileSubheading(): string
    {
        return $this->isClientePortal()
            ? 'Completa y actualiza los datos de tu ficha de cliente.'
            : 'Update your name and email address';
    }

    public function isClientePortal(): bool
    {
        return Auth::user()->role === User::ROLE_CLIENTE && $this->cliente instanceof Cliente;
    }

    /**
     * @return array<string, mixed>
     */
    protected function userRules(int $userId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function clienteRules(int $userId): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userId),
            ],
            'personal_email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'dni' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'birth_date' => ['required', 'date'],
        ];

        return $rules;
    }
}; ?>

<section class="w-full">
    @unless ($this->isClientePortal())
        @include('partials.settings-heading')
        <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>
    @endunless

    <x-pages::settings.layout :heading="$this->profileHeading" :subheading="$this->profileSubheading">
        @if ($this->isClientePortal())
            <div class="rounded-3xl border border-sky-200 bg-gradient-to-br from-sky-50 via-white to-cyan-50 p-6 shadow-sm dark:border-sky-800/60 dark:from-sky-950/30 dark:via-neutral-900 dark:to-cyan-950/30">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-sky-700 dark:text-sky-300">Portal cliente</p>
                        <h1 class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-white">Mi ficha</h1>
                        <p class="mt-3 max-w-3xl text-sm text-neutral-700 dark:text-neutral-300">
                            Completa y actualiza los datos base de tu ficha para activar el resto del portal.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            @if ($this->isClientePortal())
                <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="name" label="Nombre" type="text" required />
                        <flux:input wire:model="last_name" label="Primer apellido" type="text" required />
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="second_last_name" label="Segundo apellido" type="text" />
                        <flux:input wire:model="dni" label="DNI o NIE" type="text" required />
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="email" label="Correo de acceso" type="email" required />
                        <flux:input wire:model="personal_email" label="Correo personal" type="email" required />
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="phone" label="Telefono" type="text" required />
                        <flux:input wire:model="address" label="Direccion completa" type="text" required />
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="country" label="Pais" type="text" required />
                        <flux:input wire:model="city" label="Ciudad" type="text" required />
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <flux:input wire:model="province" label="Provincia" type="text" required />
                        <flux:input wire:model="postal_code" label="Codigo postal" type="text" required />
                    </div>

                    <div class="mt-6 max-w-md">
                        <flux:input wire:model="birth_date" label="Fecha de nacimiento" type="date" required />
                    </div>

                    <div class="mt-6 flex items-center gap-4">
                        <div class="flex items-center justify-end">
                            <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                                Guardar cambios
                            </flux:button>
                        </div>

                        <x-action-message class="me-3" on="profile-updated">
                            Guardado.
                        </x-action-message>
                    </div>
                </div>
            @else
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div>
                            <flux:text class="mt-4">
                                {{ __('Your email address is unverified.') }}

                                <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Click here to re-send the verification email.') }}
                                </flux:link>
                            </flux:text>

                            @if (session('status') === 'verification-link-sent')
                                <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-end">
                        <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                            {{ __('Save') }}
                        </flux:button>
                    </div>

                    <x-action-message class="me-3" on="profile-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            @endif
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
