@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Portal cliente" {{ $attributes }}>
        <x-slot name="logo" class="flex h-9 w-12 items-center justify-center rounded-xl border border-zinc-200 bg-white p-1 shadow-sm">
            <img src="{{ asset('images/logo-idronlex.png') }}" alt="Idron Lex & Consulting" class="max-h-full max-w-full object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Portal cliente" {{ $attributes }}>
        <x-slot name="logo" class="flex h-9 w-12 items-center justify-center rounded-xl border border-zinc-200 bg-white p-1 shadow-sm">
            <img src="{{ asset('images/logo-idronlex.png') }}" alt="Idron Lex & Consulting" class="max-h-full max-w-full object-contain" />
        </x-slot>
    </flux:brand>
@endif
