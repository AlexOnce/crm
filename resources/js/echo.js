import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

/**
 * Obtiene o inicializa la instancia singleton de Laravel Echo.
 * Si las credenciales de Pusher no están configuradas, retorna null de forma segura.
 */
export function getEcho() {
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    
    // Si no se ha configurado la clave de Pusher, no inicializar para evitar errores de conexión
    if (!pusherKey || pusherKey.trim() === '') {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
    const token = localStorage.getItem('agent_token');

    try {
        echoInstance = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: cluster,
            forceTLS: true,
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: token ? `Bearer ${token}` : '',
                    Accept: 'application/json',
                },
            },
        });

        return echoInstance;
    } catch (err) {
        console.warn('[Echo] Error al inicializar Laravel Echo:', err);
        return null;
    }
}

/**
 * Actualiza el Bearer Token en los headers de autorización de Echo
 */
export function updateEchoToken(newToken) {
    if (echoInstance && echoInstance.options?.auth?.headers) {
        echoInstance.options.auth.headers.Authorization = newToken ? `Bearer ${newToken}` : '';
    }
}

/**
 * Cierra la conexión de WebSockets (útil al hacer logout)
 */
export function disconnectEcho() {
    if (echoInstance) {
        try {
            echoInstance.disconnect();
        } catch (_) {}
        echoInstance = null;
    }
}
