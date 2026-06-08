<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCliente
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role === User::ROLE_CLIENTE) {
            return $next($request);
        }

        if ($user?->role === User::ROLE_GESTOR) {
            return redirect('/admin');
        }

        abort(403);
    }
}
