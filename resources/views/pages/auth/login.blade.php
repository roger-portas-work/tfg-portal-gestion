<x-layouts::auth title="Acceso al portal">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Bienvenido/a" description="Accede a tu portal de cliente de Idronlex." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6" x-data="{ email: @js(old('email')), completarDominio() { this.email = this.email?.trim(); if (this.email && ! this.email.includes('@')) { this.email = this.email + '@idronlex.com' } } }" x-on:submit="completarDominio()">
            @csrf

            <!-- Email Address -->
            <div>
                <flux:input
                    name="email"
                    label="Correo electrónico"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="nombre@idronlex.com"
                    x-model="email"
                    x-on:blur="completarDominio()"
                />
                <p class="mt-2 text-xs leading-5 text-slate-400">
                    Puedes escribir solo tu usuario: añadiremos <span class="font-medium text-blue-300">@idronlex.com</span> automáticamente.
                </p>
            </div>

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    label="Contraseña"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Introduce tu contraseña"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        ¿Has olvidado tu contraseña?
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="Mantener la sesión iniciada" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    Entrar al portal
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>¿Aún no tienes acceso?</span>
                <flux:link :href="route('register')" wire:navigate>Solicitar acceso</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
