<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Portal Idronlex'])
    </head>
    <body class="min-h-screen bg-[#071427] text-white antialiased">
        <main class="relative isolate flex min-h-svh items-center overflow-hidden px-5 py-10 sm:px-8 lg:px-12">
            <div class="pointer-events-none absolute -left-32 top-0 size-[32rem] rounded-full bg-blue-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 right-0 size-[34rem] rounded-full bg-cyan-400/15 blur-3xl"></div>

            <section class="relative mx-auto w-full max-w-5xl">
                <div class="flex items-center gap-3">
                    <span class="flex size-14 items-center justify-center overflow-hidden rounded-2xl bg-white p-1 shadow-lg shadow-blue-950/30">
                        <img src="{{ asset('images/simbol-idronlex.png') }}" alt="Símbolo de Idronlex" class="size-full object-contain" />
                    </span>
                    <span>
                        <span class="block text-xl font-semibold tracking-[0.16em]">IDRONLEX</span>
                        <span class="block text-sm text-blue-200">Lex &amp; Consulting</span>
                    </span>
                </div>

                <div class="mt-16 grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">Plataforma digital</p>
                        <h1 class="mt-5 max-w-2xl text-4xl font-semibold tracking-tight sm:text-5xl">
                            Gestiona tu actividad con todo lo importante en un mismo lugar.
                        </h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                            Accede a la documentación, operaciones y seguimiento de tu expediente de forma segura.
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <a href="{{ route('login') }}" class="group rounded-[1.75rem] border border-cyan-300/25 bg-slate-950/80 p-6 shadow-2xl shadow-blue-950/30 transition hover:-translate-y-1 hover:border-cyan-300/60 hover:bg-slate-900/90" wire:navigate>
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-cyan-400/15 text-cyan-200">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75V5.625A2.625 2.625 0 0 0 13.125 3h-4.5A2.625 2.625 0 0 0 6 5.625v12.75A2.625 2.625 0 0 0 8.625 21h4.5a2.625 2.625 0 0 0 2.625-2.625V17.25M18 12H9m9 0-3-3m3 3-3 3" />
                                </svg>
                            </span>
                            <h2 class="mt-5 text-xl font-semibold">Soy cliente</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Consulta tu expediente, documentación y próximas operaciones.</p>
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-cyan-300">Entrar al portal <span aria-hidden="true">→</span></span>
                        </a>

                        <a href="{{ url('/admin/login') }}" class="group rounded-[1.75rem] border border-white/10 bg-white/5 p-6 transition hover:-translate-y-1 hover:border-blue-300/45 hover:bg-white/10">
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-blue-400/15 text-blue-200">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 21V9.75L12 3l7.5 6.75V21M9 21v-6h6v6M8.25 10.5h.008v.008H8.25V10.5Zm7.5 0h.008v.008h-.008V10.5Z" />
                                </svg>
                            </span>
                            <h2 class="mt-5 text-xl font-semibold">Área de gestión</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Acceso exclusivo para el equipo gestor de Idronlex.</p>
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-blue-300">Acceder como gestor <span aria-hidden="true">→</span></span>
                        </a>
                    </div>
                </div>

                <p class="mt-12 text-xs leading-5 text-slate-400">Idronlex · Lex &amp; Consulting</p>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
