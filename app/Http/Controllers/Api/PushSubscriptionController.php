<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    /**
     * Retorna la clave pública VAPID para el navegador
     */
    public function vapidPublicKey(): JsonResponse
    {
        $publicKey = WebPushService::getPublicKey();

        if (empty($publicKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Llave pública VAPID no configurada en el servidor.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'publicKey' => $publicKey,
        ]);
    }

    /**
     * Guarda o actualiza la suscripción push del usuario actual
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'contentEncoding' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $endpoint = $validated['endpoint'];
        $endpointHash = hash('sha256', $endpoint);

        $subscription = PushSubscription::updateOrCreate(
            [
                'user_id' => $userId,
                'endpoint_hash' => $endpointHash,
            ],
            [
                'endpoint' => $endpoint,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => $request->userAgent(),
            ]
        );

        Log::info("[WebPush] Usuario {$userId} suscrito a notificaciones push.", [
            'subscription_id' => $subscription->id,
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Suscripción push registrada correctamente en el servidor.',
            'id' => $subscription->id,
        ]);
    }

    /**
     * Elimina una suscripción push cuando el usuario desactiva las alertas
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $userId = $request->user()->id;
        $endpointHash = hash('sha256', $validated['endpoint']);

        $deleted = PushSubscription::where('user_id', $userId)
            ->where('endpoint_hash', $endpointHash)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted ? 'Suscripción eliminada con éxito.' : 'No se encontró la suscripción.',
        ]);
    }

    /**
     * Envía una notificación Web Push de prueba al usuario actual
     */
    public function test(Request $request, WebPushService $webPushService): JsonResponse
    {
        $user = $request->user();
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes ningún dispositivo o navegador suscrito a alertas push.',
            ], 422);
        }

        $payload = [
            'title' => '🚀 Prueba Web Push VAPID',
            'body' => "¡Hola {$user->name}! Esta notificación confirma que tu equipo recibirá alertas aunque el CRM esté cerrado.",
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'tag' => 'vapid-test-'.time(),
            'url' => '/',
            'requireInteraction' => true,
        ];

        $sentCount = 0;
        foreach ($subscriptions as $sub) {
            if ($webPushService->sendToSubscription($sub, $payload)) {
                $sentCount++;
            }
        }

        return response()->json([
            'success' => $sentCount > 0,
            'message' => $sentCount > 0
                ? "Notificación push enviada a {$sentCount} dispositivo(s)."
                : 'No se pudo entregar la notificación push. Revisa los logs del servidor.',
            'sentCount' => $sentCount,
            'total' => $subscriptions->count(),
        ]);
    }
}
