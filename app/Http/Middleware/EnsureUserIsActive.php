<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Valida que el usuario autenticado tenga su cuenta activa.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            // Revocar tokens activos si la cuenta se encuentra suspendida
            $user->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta ha sido suspendida. Contacta al administrador del sistema.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
