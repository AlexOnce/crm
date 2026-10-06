/**
 * TAMep CRM - Service Worker para PWA y Notificaciones Nativas
 * Versión: 1.0.0
 */

const CACHE_NAME = 'tamep-crm-cache-v1';
const STATIC_ASSETS = [
  '/',
  '/manifest.webmanifest',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/icons/badge-72x72.png',
  '/icons/apple-touch-icon.png'
];

// 1. Instalación del Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('[SW] Algunos recursos iniciales no pudieron cachearse:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// 2. Activación y limpieza de caches antiguos
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    }).then(() => self.clients.claim())
  );
});

// 3. Estrategia de Fetch (Network-first para API y vistas, Cache-fallback)
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // No interceptar peticiones a la API, WebSockets ni métodos distintos de GET
  if (req.method !== 'GET' || url.pathname.startsWith('/api') || url.pathname.startsWith('/broadcasting')) {
    return;
  }

  event.respondWith(
    fetch(req)
      .then((response) => {
        // Guardar copia de estáticos seguros en cache
        if (response && response.status === 200 && (url.pathname.startsWith('/icons/') || url.pathname.startsWith('/build/'))) {
          const resClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, resClone));
        }
        return response;
      })
      .catch(() => {
        return caches.match(req).then((cached) => cached || caches.match('/'));
      })
  );
});

// 4. Recepción de Notificaciones Push (Web Push API)
self.addEventListener('push', (event) => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (_) {
      data = { title: 'TAMep CRM', body: event.data.text() };
    }
  }

  const title = data.title || 'TAMep CRM - Notificación';
  const options = {
    body: data.body || 'Tienes una nueva interacción en la bandeja.',
    icon: data.icon || '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    tag: data.tag || 'tamep-notification',
    renotify: true,
    requireInteraction: true, // Notificación persistente hasta interacción
    vibrate: [200, 100, 200, 100, 250],
    data: {
      url: data.url || '/',
      conversationId: data.conversationId || null
    },
    actions: [
      { action: 'open', title: 'Abrir Chat' },
      { action: 'close', title: 'Ignorar' }
    ]
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// 5. Interacción del usuario con la Notificación Nativa
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'close') {
    return;
  }

  const targetUrl = event.notification.data?.url || '/';
  const convId = event.notification.data?.conversationId;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      // Si ya hay una pestaña o ventana del CRM abierta, enfocarla
      for (const client of windowClients) {
        if (client.url.includes(self.origin) && 'focus' in client) {
          if (convId) {
            client.postMessage({ type: 'OPEN_CONVERSATION', conversationId: convId });
          }
          return client.focus();
        }
      }
      // Si no está abierta ninguna ventana, abrir una nueva
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
    })
  );
});
