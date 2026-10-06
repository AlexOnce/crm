<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    protected ?WebPush $webPush = null;

    /**
     * Obtiene la clave pública VAPID configurada en .env
     */
    public static function getPublicKey(): ?string
    {
        return config('services.webpush.public_key') ?: env('VAPID_PUBLIC_KEY');
    }

    /**
     * Obtiene la instancia configurada de WebPush
     */
    protected function getWebPush(): ?WebPush
    {
        if ($this->webPush) {
            return $this->webPush;
        }

        $publicKey = self::getPublicKey();
        $privateKey = config('services.webpush.private_key') ?: env('VAPID_PRIVATE_KEY');
        $subject = config('services.webpush.subject') ?: env('VAPID_SUBJECT', 'mailto:soporte@tamep.bo');

        if (empty($publicKey) || empty($privateKey)) {
            Log::warning('[WebPush] Llaves VAPID no configuradas en .env. Omitiendo Web Push.');

            return null;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ];

            // Opciones por defecto para tolerancia y timeout
            $defaultOptions = [
                'TTL' => 3600, // 1 hora en cola de Google/Microsoft si el dispositivo está apagado
                'urgency' => 'high',
            ];

            $this->webPush = new WebPush($auth, $defaultOptions);

            return $this->webPush;
        } catch (\Throwable $e) {
            Log::error('[WebPush] Error inicializando WebPush: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Envía una notificación Web Push a una suscripción específica
     */
    public function sendToSubscription(PushSubscription $sub, array $payload): bool
    {
        $webPush = $this->getWebPush();
        if (! $webPush) {
            return false;
        }

        try {
            $subscriptionObj = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
            ]);

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $report = $webPush->sendOneNotification($subscriptionObj, $jsonPayload);

            if ($report->isSuccess()) {
                return true;
            }

            // Si el endpoint expiró o fue desuscrito por el usuario en el navegador (HTTP 404 o 410 Gone)
            if ($report->isSubscriptionExpired()) {
                Log::info("[WebPush] Suscripción expirada ({$sub->id}). Eliminando de la BD.");
                $sub->delete();
            } else {
                Log::warning("[WebPush] Falló envío a suscripción {$sub->id}: ".$report->getReason());
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("[WebPush] Excepción al enviar a suscripción {$sub->id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Envía una notificación Web Push a múltiples usuarios (Agentes / Admins)
     */
    public function notifyUsers($users, array $payload): int
    {
        $userIds = $users instanceof Collection ? $users->pluck('id')->all() : (array) $users;
        if (empty($userIds)) {
            return 0;
        }

        $subscriptions = PushSubscription::whereIn('user_id', $userIds)->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $sentCount = 0;
        foreach ($subscriptions as $sub) {
            if ($this->sendToSubscription($sub, $payload)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Notifica a todos los agentes activos sobre un nuevo pasajero en espera (Handover)
     */
    public function notifyWaitingPassenger(Conversation $conversation, ?string $previewText = null): int
    {
        $activeAgentIds = User::where('is_active', true)
            ->whereIn('role', ['agent', 'admin'])
            ->pluck('id');

        $contactName = $conversation->contact_name ?: $conversation->phone ?: 'Pasajero';
        $body = $previewText ?: 'Solicita atención de un asesor de ventas de TAMep.';

        $payload = [
            'title' => "🛎️ Pasajero en Espera: {$contactName}",
            'body' => $body,
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'tag' => "waiting-{$conversation->id}",
            'url' => "/?conversation_id={$conversation->id}",
            'conversationId' => $conversation->id,
            'requireInteraction' => true,
        ];

        return $this->notifyUsers($activeAgentIds, $payload);
    }

    /**
     * Notifica a un agente asignado (o a todos si no tiene asignado) sobre un nuevo mensaje del cliente
     */
    public function notifyNewMessage(Conversation $conversation, ChatMessage $message): int
    {
        // Solo notificar si el mensaje es del cliente ('user')
        if ($message->sender !== 'user') {
            return 0;
        }

        // Si la conversación tiene un agente asignado, notificarle a él
        if ($conversation->assigned_user_id) {
            $targetUserIds = [$conversation->assigned_user_id];
        } else {
            // Si está sin asignar o en espera, notificar a todos los asesores y administradores activos
            $targetUserIds = User::where('is_active', true)
                ->whereIn('role', ['agent', 'admin'])
                ->pluck('id')
                ->all();
        }

        $senderName = $conversation->contact_name ?: $conversation->phone ?: 'Pasajero';
        $bodyText = $message->message ?: ($message->media_type === 'audio' ? '🎤 Nota de voz recibida' : 'Nuevo mensaje recibido');

        $payload = [
            'title' => "💬 {$senderName}",
            'body' => $bodyText,
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'tag' => "chat-{$conversation->id}",
            'url' => "/?conversation_id={$conversation->id}",
            'conversationId' => $conversation->id,
            'requireInteraction' => true,
        ];

        return $this->notifyUsers($targetUserIds, $payload);
    }
}
