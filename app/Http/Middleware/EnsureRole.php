<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe rutas por rol. Uso en rutas:
 *   Route::...->middleware('role:admin,coordinator');
 *
 * Todos los roles autenticados pueden ver el horario, así que esas rutas
 * NO llevan este middleware; solo las acciones sensibles.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            abort(403, 'Cuenta inactiva o no autenticada.');
        }

        if (! in_array($user->role->value, $roles, true)) {
            abort(403, 'No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}
