<x-layouts::auth title="Verifica tu correo">
    <div class="mt-4 flex flex-col gap-6">
        <x-auth-header title="Verifica tu correo" description="Necesitamos confirmar tu dirección para proteger tu acceso." />
        <flux:text class="text-center">
            Revisa tu bandeja de entrada y pulsa el enlace que acabamos de enviarte.
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                Hemos enviado un nuevo enlace de verificación a tu correo.
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    Reenviar correo de verificación
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    Cerrar sesión
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
