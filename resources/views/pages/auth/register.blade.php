<x-layouts::auth title="Crear una cuenta">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Solicita tu acceso" description="Completa tus datos para crear tu cuenta de cliente." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                label="Nombre completo"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                placeholder="Tu nombre y apellidos"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                label="Correo electrónico"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="nombre@idronlex.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                label="Contraseña"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Crea una contraseña"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                label="Confirmar contraseña"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Repite tu contraseña"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    Crear cuenta
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
                <span>¿Ya tienes una cuenta?</span>
                <flux:link :href="route('login')" wire:navigate>Acceder al portal</flux:link>
        </div>
    </div>
</x-layouts::auth>
