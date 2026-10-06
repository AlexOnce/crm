import { ref } from 'vue';
import { playChime } from '@/stores/useChatStore';
import api from '@/api/client';

export const isSupported = ref(typeof window !== 'undefined' && 'serviceWorker' in navigator && 'Notification' in window);
export const notificationPermission = ref(typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'denied');
export const canInstall = ref(false);
export const isInstalled = ref(false);
export const isPushSubscribed = ref(false);
export const isPushLoading = ref(false);

let deferredInstallPrompt = null;
let swRegistration = null;
let onOpenConversationCallback = null;

/**
 * Convierte una clave VAPID Base64URL en Uint8Array para pushManager
 */
export function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

/**
 * Permite registrar un callback para cuando el usuario haga clic en una notificación
 * y el Service Worker solicite abrir un chat específico
 */
export function setConversationOpenHandler(cb) {
  onOpenConversationCallback = cb;
}

/**
 * Obtiene la llave pública VAPID (desde env o API)
 */
export async function getVapidPublicKey() {
  const envKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
  if (envKey && envKey.trim() !== '') {
    return envKey.trim();
  }

  try {
    const res = await api.get('/push/vapid-public-key');
    if (res.data.success && res.data.publicKey) {
      return res.data.publicKey;
    }
  } catch (err) {
    console.warn('[PWA] No se pudo obtener la llave VAPID del servidor:', err);
  }
  return null;
}

/**
 * Suscribe el navegador del usuario al canal de Web Push (VAPID) en segundo plano
 */
export async function subscribeUserToPush() {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    return null;
  }

  if (Notification.permission !== 'granted') {
    return null;
  }

  isPushLoading.value = true;
  try {
    const reg = swRegistration || await navigator.serviceWorker.ready;
    if (!reg.pushManager) {
      console.warn('[PWA] PushManager no disponible en este navegador.');
      return null;
    }

    const publicKey = await getVapidPublicKey();
    if (!publicKey) {
      console.warn('[PWA] No se encontró clave pública VAPID.');
      return null;
    }

    let subscription = await reg.pushManager.getSubscription();

    // Si ya existe suscripción previa
    if (!subscription) {
      const convertedVapidKey = urlBase64ToUint8Array(publicKey);
      subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: convertedVapidKey,
      });
      console.log('[PWA] Nueva suscripción push creada en el navegador.');
    }

    // Registrar en el backend de Laravel
    const token = localStorage.getItem('agent_token');
    if (token) {
      const subJson = subscription.toJSON();
      await api.post('/push/subscribe', {
        endpoint: subJson.endpoint,
        keys: subJson.keys,
        contentEncoding: (window.PushManager && PushManager.supportedContentEncodings) ? PushManager.supportedContentEncodings[0] : 'aes128gcm',
      });
      isPushSubscribed.value = true;
      console.log('[PWA] Suscripción VAPID sincronizada con éxito en el servidor.');
    }

    return subscription;
  } catch (err) {
    console.error('[PWA] Error suscribiendo a Web Push VAPID:', err);
    return null;
  } finally {
    isPushLoading.value = false;
  }
}

/**
 * Elimina la suscripción Web Push del navegador y del servidor
 */
export async function unsubscribeUserFromPush() {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return false;

  try {
    const reg = swRegistration || await navigator.serviceWorker.ready;
    const subscription = await reg.pushManager.getSubscription();
    if (subscription) {
      try {
        await api.post('/push/unsubscribe', { endpoint: subscription.endpoint });
      } catch (_) {}
      await subscription.unsubscribe();
      isPushSubscribed.value = false;
      console.log('[PWA] Suscripción push cancelada.');
      return true;
    }
  } catch (err) {
    console.warn('[PWA] Error al desuscribir de Web Push:', err);
  }
  return false;
}

/**
 * Envía una prueba de notificación Web Push al backend para validar el despertar del equipo
 */
export async function sendTestPushNotification() {
  try {
    const res = await api.post('/push/test');
    return res.data;
  } catch (err) {
    console.error('[PWA] Error disparando prueba Web Push:', err);
    return {
      success: false,
      message: err.response?.data?.message || 'Error al conectar con el servidor push.',
    };
  }
}

/**
 * Inicializa el Service Worker y los escuchadores de eventos PWA
 */
export async function initPwa() {
  if (typeof window === 'undefined') return;

  // 1. Detectar si ya está corriendo en modo Standalone (App instalada)
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  isInstalled.value = isStandalone;

  // 2. Estado actual de permisos de notificación
  if ('Notification' in window) {
    notificationPermission.value = Notification.permission;
  }

  // 3. Registrar Service Worker
  if ('serviceWorker' in navigator) {
    try {
      swRegistration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      console.log('[PWA] Service Worker registrado exitosamente con scope:', swRegistration.scope);

      // Escuchar mensajes provenientes del Service Worker (ej. clic en notificación)
      navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'OPEN_CONVERSATION' && event.data?.conversationId) {
          if (typeof onOpenConversationCallback === 'function') {
            onOpenConversationCallback(event.data.conversationId);
          }
        }
      });

      // Si las notificaciones ya estaban concedidas, sincronizar la suscripción VAPID
      if (Notification.permission === 'granted' && localStorage.getItem('agent_token')) {
        subscribeUserToPush().catch((e) => console.warn('[PWA] Auto-sync VAPID:', e));
      }
    } catch (err) {
      console.warn('[PWA] Error al registrar Service Worker:', err);
    }
  }

  // 4. Capturar evento de instalación nativa (Chrome, Edge, Android)
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    canInstall.value = true;
    console.log('[PWA] Evento beforeinstallprompt capturado. La app es instalable.');
  });

  window.addEventListener('appinstalled', () => {
    canInstall.value = false;
    deferredInstallPrompt = null;
    isInstalled.value = true;
    console.log('[PWA] Aplicación instalada con éxito en el sistema.');
  });
}

/**
 * Solicita permiso de notificaciones nativas al usuario y suscribe a VAPID
 */
export async function requestNotificationPermission() {
  if (!('Notification' in window)) {
    alert('Tu navegador no soporta notificaciones de escritorio.');
    return 'unsupported';
  }

  try {
    const permission = await Notification.requestPermission();
    notificationPermission.value = permission;

    if (permission === 'granted') {
      // Suscribir a Web Push VAPID para alertas con app cerrada
      await subscribeUserToPush();

      showSystemNotification({
        title: 'TAMep CRM - Notificaciones Activas',
        body: '¡Listo! Recibirás alertas instantáneas incluso si cierras esta ventana.',
        tag: 'welcome-notification'
      });
    }

    return permission;
  } catch (err) {
    console.error('[PWA] Error al solicitar permisos de notificación:', err);
    return 'denied';
  }
}

/**
 * Dispara el banner de instalación nativa de la PWA
 */
export async function promptInstall() {
  if (!deferredInstallPrompt) {
    return false;
  }

  deferredInstallPrompt.prompt();
  const choiceResult = await deferredInstallPrompt.userChoice;

  if (choiceResult.outcome === 'accepted') {
    console.log('[PWA] El usuario aceptó instalar el CRM');
    canInstall.value = false;
    deferredInstallPrompt = null;
    return true;
  } else {
    console.log('[PWA] El usuario canceló la instalación');
    return false;
  }
}

/**
 * Envía una notificación nativa del sistema (Windows, macOS, Android, iOS)
 */
export async function showSystemNotification({ title, body, icon = '/icons/icon-192x192.png', data = {}, tag = 'tamep-msg' }) {
  if (typeof window === 'undefined' || !('Notification' in window)) return;
  if (Notification.permission !== 'granted') return;

  // 1. Tono sonoro sutil
  try {
    playChime();
  } catch (_) {}

  // 2. Vibración física en dispositivos móviles compatibles
  if ('vibrate' in navigator) {
    try {
      navigator.vibrate([150, 80, 150]);
    } catch (_) {}
  }

  const options = {
    body,
    icon,
    badge: '/icons/badge-72x72.png',
    tag,
    renotify: true,
    requireInteraction: true, // Permanece fija en pantalla hasta que el agente interactúe
    data: {
      url: '/',
      ...data
    }
  };

  // 3. Priorizar envío a través del Service Worker Registration activo o listo
  if ('serviceWorker' in navigator) {
    try {
      const reg = swRegistration || await navigator.serviceWorker.ready;
      if (reg && 'showNotification' in reg) {
        await reg.showNotification(title, options);
        return;
      }
    } catch (err) {
      console.warn('[PWA] Falló reg.showNotification:', err);
    }
  }

  // 4. Fallback directo a la Notification API estándar (Desktop)
  if ('Notification' in window) {
    try {
      const notif = new Notification(title, options);
      notif.onclick = () => {
        window.focus();
        if (data?.conversationId && typeof onOpenConversationCallback === 'function') {
          onOpenConversationCallback(data.conversationId);
        }
        notif.close();
      };
    } catch (err) {
      console.warn('[PWA] Error al disparar notificación nativa:', err);
    }
  }
}
