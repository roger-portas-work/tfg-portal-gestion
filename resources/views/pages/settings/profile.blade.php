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
    public string $email = '';
    public string $phone = '';
    public string $dni = '';
    public string $address = '';
    public string $operator_registration_number = '';
    public string $birth_date = '';
    public string $pilot_identification_number = '';
    public string $pilot_certificate = '';
    public string $operator_certification = '';

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
        $this->email = $this->cliente->email ?? $user->email;
        $this->phone = $this->cliente->phone ?? '';
        $this->dni = $this->cliente->dni ?? '';
        $this->address = $this->cliente->address ?? '';
        $this->operator_registration_number = $this->cliente->operator_registration_number ?? '';
        $this->birth_date = match (true) {
            $this->cliente->birth_date instanceof \DateTimeInterface => $this->cliente->birth_date->format('Y-m-d'),
            filled($this->cliente->birth_date) => Carbon::parse($this->cliente->birth_date)->format('Y-m-d'),
            default => '',
        };
        $this->pilot_identification_number = $this->cliente->pilot_identification_number ?? '';
        $this->pilot_certificate = $this->cliente->pilot_certificate ?? '';
        $this->operator_certification = $this->cliente->operator_certification ?? '';
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
            'name' => trim("{$validated['name']} {$validated['last_name']}"),
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
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userId),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'dni' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'operator_registration_number' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'pilot_identification_number' => ['required', 'string', 'max:255'],
            'pilot_certificate' => ['nullable', 'string', 'max:255'],
            'operator_certification' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->cliente?->client_type === Cliente::TYPE_JURIDICO) {
            $rules['operator_certification'][0] = 'required';
        } else {
            $rules['pilot_certificate'][0] = 'required';
        }

        return $rules;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="$this->profileHeading" :subheading="$this->profileSubheading">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            @if ($this->isClientePortal())
                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model="name" label="Nombre" type="text" required />
                    <flux:input wire:model="last_name" label="Apellido" type="text" required />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model="email" label="Email" type="email" required />
                    <flux:input wire:model="phone" label="Telefono" type="text" required />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model="dni" label="DNI" type="text" required />
                    <flux:input wire:model="address" label="Direccion" type="text" required />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model="operator_registration_number" label="Numero de registro operadora" type="text" required />
                    <flux:input wire:model="birth_date" label="Fecha de nacimiento" type="date" required />
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <flux:input wire:model="pilot_identification_number" label="Numero identificacion piloto" type="text" required />

                    @if ($this->cliente?->client_type === \App\Models\Cliente::TYPE_JURIDICO)
                        <flux:input wire:model="operator_certification" label="Certificacion operadora" type="text" required />
                    @else
                        <flux:input wire:model="pilot_certificate" label="Certificado titulacion de piloto" type="text" required />
                    @endif
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
            @endif

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

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
