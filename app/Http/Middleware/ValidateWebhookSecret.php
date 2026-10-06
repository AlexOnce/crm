<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $incomingSecret = $request->header('X-Webhook-Secret')
            ?? $request->bearerToken()
            ?? $request->query('secret');

        $expectedSecret = (string) config('services.evolution.webhook_secret');

        // Si hay un secret configurado en el servidor, debe coincidir estrictamente
        if (! empty($expectedSecret)) {
            if (! $incomingSecret || ! hash_equals($expectedSecret, (string) $incomingSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso no autorizado: Token de webhook inválido o ausente.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        return $next($request);
    }
}
