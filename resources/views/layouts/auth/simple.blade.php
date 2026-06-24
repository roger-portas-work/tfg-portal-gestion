<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="idronlex-auth">
        <main class="idronlex-auth__shell">
            <div
                class="idronlex-auth__logo"
                role="img"
                aria-label="Idronlex Lex & Consulting"
                style="background-image: url('{{ asset('images/logo-idronlex.png') }}');"
            ></div>

            <section class="idronlex-auth__card">
                {{ $slot }}
            </section>
        </main>

        <style>
            .idronlex-auth {
                display: grid;
                min-height: 100vh;
                margin: 0;
                padding: 2rem 1.25rem;
                background: linear-gradient(145deg, #f8fbff 0%, #eef5ff 100%);
                color: #172033;
                place-items: center;
            }

            .idronlex-auth__shell {
                width: min(100%, 28rem);
            }

            .idronlex-auth__logo {
                width: 10.5rem;
                height: 9.5rem;
                margin: 0 auto 1.25rem;
                background-position: center;
                background-repeat: no-repeat;
                background-size: contain;
            }

            .idronlex-auth__card {
                padding: 2rem;
                border: 1px solid #dbe5f3;
                border-radius: 1.25rem;
                background: #fff;
                box-shadow: 0 1.25rem 3.5rem rgba(30, 64, 175, 0.12);
            }

            @media (max-width: 40rem) {
                .idronlex-auth {
                    padding: 1.25rem;
                }

                .idronlex-auth__card {
                    padding: 1.5rem;
                }
            }
        </style>
        @fluxScripts
    </body>
</html>
