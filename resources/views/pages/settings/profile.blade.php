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
        // si ya esta completa.
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
        if (! $this->isClientePortal()) {
            return 'Update your name and email address';
        }

        return $this->cliente?->isUnblocked()
            ? 'Puedes modificar los datos de tu ficha base.'
            : 'Completa tu ficha para desbloquear el resto del portal.';
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
            <div class="portal-page portal-page--wide">
                <div class="portal-hero portal-hero--client">
                    <div class="portal-hero__row">
                        <div>
                            <p class="portal-hero__eyebrow text-sky-700 dark:text-sky-300">Portal cliente</p>
                            <h1 class="portal-hero__title">Mi ficha</h1>
                            <p class="portal-hero__text">
                                {{ $this->cliente?->isUnblocked()
                                    ? 'Puedes modificar los datos de tu ficha base.'
                                    : 'Completa tu ficha para desbloquear el resto del portal.' }}
                            </p>
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

                <form wire:submit="updateProfileInformation" class="portal-form-shell">
                    <div class="portal-form-header">
                        <div>
                            <h2 class="portal-form-title">Datos de la ficha base</h2>
                            <p class="portal-form-text">
                                Manten actualizada la informacion principal de tu expediente de cliente.
                            </p>
                        </div>
                    </div>

                    <div class="portal-form-sections">
                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title">Datos personales</h3>
                            <p class="portal-form-section__text">
                                Estos datos identifican tu ficha dentro del portal.
                            </p>

                            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                                <flux:input wire:model="name" label="Nombre" type="text" required />
                                <flux:input wire:model="last_name" label="Primer apellido" type="text" required />
                                <flux:input wire:model="second_last_name" label="Segundo apellido" type="text" />
                                <flux:input wire:model="dni" label="DNI o NIE" type="text" required />
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title">Contacto</h3>
                            <p class="portal-form-section__text">
                                Usaremos estos datos para comunicaciones relacionadas con tu expediente.
                            </p>

                            <div class="mt-6 grid gap-6 md:grid-cols-3">
                                <flux:input wire:model="email" label="Correo de acceso" type="email" required />
                                <flux:input wire:model="personal_email" label="Correo personal" type="email" required />
                                <flux:input wire:model="phone" label="Telefono" type="text" required />
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title">Direccion y nacimiento</h3>
                            <p class="portal-form-section__text">
                                Completa la direccion completa y la fecha de nacimiento para mantener la ficha validada.
                            </p>

                            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                                <flux:input wire:model="address" label="Direccion completa" type="text" required />
                                <flux:input wire:model="country" label="Pais" type="text" required />
                                <flux:input wire:model="city" label="Ciudad" type="text" required />
                                <flux:input wire:model="province" label="Provincia" type="text" required />
                                <flux:input wire:model="postal_code" label="Codigo postal" type="text" required />
                                <flux:input wire:model="birth_date" label="Fecha de nacimiento" type="date" required />
                            </div>
                        </div>

                        <div class="portal-form-actions">
                            <flux:button variant="primary" type="submit" data-test="update-profile-button">
                                Guardar cambios
                            </flux:button>

                            <x-action-message class="me-3" on="profile-updated">
                                Guardado.
                            </x-action-message>
                        </div>
                    </div>
                </form>
            </div>
        @else
            <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
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
            </form>
        @endif

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
