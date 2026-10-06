<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastHelper
{
    /**
     * Emite un evento por broadcast de forma segura y tolerante a fallos.
     * Si las credenciales no están configuradas o hay un error de red con Pusher,
     * no interrumpe el flujo principal de la aplicación.
     */
    public static function safeBroadcast(object $event): void
    {
        $connection = config('broadcasting.default');

        // Si está configurado pusher pero la clave está vacía, omitir para no lanzar excepciones
        if ($connection === 'pusher') {
            $key = config('broadcasting.connections.pusher.key');
            if (empty($key)) {
                return;
            }
        }

        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast skipped or failed safely: '.$e->getMessage(), [
                'event' => get_class($event),
            ]);
        }
    }
}
