<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    public function getTitle(): string | Htmlable
    {
        return 'Acceso de gestores';
    }

    public function getHeading(): string | Htmlable | null
    {
        return new HtmlString(
            '<img src="'.asset('images/logo-idronlex.png').'" alt="Idronlex Lex & Consulting" style="display:block; width:5.5rem; height:6.5rem; margin:0 auto 1rem; object-fit:contain;">'
            .'<span style="display:block">Área de gestión</span>',
        );
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Accede para gestionar clientes, operaciones y documentación.';
    }
}
