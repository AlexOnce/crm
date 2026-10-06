# 📋 Informe Técnico: Diagnóstico y Observaciones sobre las Notificaciones en TAMep CRM

**Fecha:** 21 de Septiembre de 2026  
**Sistema:** TAMep WhatsApp CRM (Laravel 11 + Vue 3 PWA)  
**Objetivo:** Documentar las causas exactas por las cuales las notificaciones no se mostraban en Windows 11 y dispositivos móviles, tanto con la aplicación cerrada como con la aplicación abierta, así como las soluciones técnicas aplicadas.

---

## 📌 Resumen Ejecutivo

Durante las pruebas en Windows 11 y cPanel se detectaron dos escenarios con causas técnicas totalmente distintas:
1. **Con la aplicación/pestaña cerrada con la "X":** No llegaban porque el navegador destruye el proceso de JavaScript y corta la conexión en vivo (WebSockets). Para despertar un equipo con la app apagada se requiere el protocolo **Web Push (VAPID)**.
2. **Con la aplicación abierta o minimizada:** No llegaban debido a restricciones en el código frontend (bloqueo por ventana activa, dependencia exclusiva de WebSockets sin disparador en el refresco de respaldo, y permisos de sistema).

A continuación se detalla cada caso, su explicación técnica y las correcciones implementadas.

---

## 🔍 Escenario 1: ¿Por qué no salían con la aplicación CERRADA?

### 1.1 Naturaleza de una PWA instalada en Windows 11
- Al instalar el CRM como PWA en Windows 11, el sistema operativo genera un acceso directo que abre la web en una ventana independiente (sin barra de direcciones).
- **No es un archivo ejecutable (`.exe`)** ni un servicio residente en segundo plano de Windows.
- Cuando el usuario hace clic en la **"X"** roja superior:
  - Windows finaliza el proceso del navegador inmediatamente.
  - La conexión TCP de WebSockets (Pusher) se interrumpe al instante.
  - La memoria RAM asignada a la aplicación se libera y ningún script de Vue/JS continúa ejecutándose.

### 1.2 Por qué WhatsApp Web o Gmail sí avisan con la app cerrada
- Aplicaciones como WhatsApp o Gmail no dependen de que la ventana esté abierta porque utilizan el protocolo **Web Push con llaves criptográficas VAPID**:
  1. El servidor de WhatsApp no le habla a la computadora del usuario.
  2. El servidor le habla a los servidores centrales de **Microsoft (Windows Push Notification Services - WNS)** o **Google (Firebase Cloud Messaging - FCM)**.
  3. Los servidores de Microsoft/Google envían un paquete a nivel de sistema operativo que enciende la alerta en la pantalla aunque la app lleve horas cerrada.
- En el backend actual de Laravel no se encontraba instalado el canal de Web Push VAPID, por lo que al cerrar la ventana la comunicación quedaba 100% interrumpida.

---

## 🔍 Escenario 2: ¿Por qué no salían incluso con la aplicación ABIERTA?

Al analizar el código del frontend ([useChatStore.js](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/stores/useChatStore.js) y [pwaService.js](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/services/pwaService.js)), se encontraron **cuatro causas críticas**:

### Causa A: Restricción por "Ventana con Foco" (`document.hidden`)
En el archivo `useChatStore.js` existía la siguiente validación previa:
```javascript
// Código anterior (Problemático):
if (typeof document !== 'undefined' && (document.hidden || !document.hasFocus())) {
    showSystemNotification({ ... });
}
```
* **El problema:** Si el usuario tenía la ventana abierta frente a sus ojos (o en una pantalla secundaria) y la ventana tenía el foco (`hasFocus() === true`), el sistema **suprimía la notificación intencionalmente**, asumiendo erróneamente que el usuario ya estaba leyendo la pantalla.

### Causa B: Dependencia exclusiva de WebSockets (Pusher)
- El disparador de `showSystemNotification` estaba vinculado **únicamente** a los eventos de WebSockets:
  ```javascript
  echo.private('conversations').listen('.ChatMessageSent', ...);
  ```
- Si en el servidor de cPanel las variables de Pusher (`BROADCAST_CONNECTION`, `PUSHER_APP_KEY`) no estaban configuradas, o si la red corporativa/firewall bloqueaba el puerto de WebSockets, el CRM recurría a su mecanismo de respaldo: **Smart Polling** (consulta periódica a la base de datos cada 2.5 y 8 segundos).
- **El fallo:** Ese mecanismo de refresco de fondo actualizaba la lista de chats, pero **no tenía programada la instrucción de emitir la notificación sonora ni de sistema**.

### Causa C: Inicialización asíncrona del Service Worker
En `pwaService.js`, la función `showSystemNotification` dependía de una variable local `swRegistration`:
```javascript
// Código anterior:
if (swRegistration && 'showNotification' in swRegistration) {
    await swRegistration.showNotification(title, options);
}
```
* **El problema:** Si la función era invocada antes de que el Service Worker completara su ciclo de registro (o si se recargaba la página), `swRegistration` era `null` y el navegador intentaba el fallback `new Notification()`, el cual en navegadores basados en Chromium o en dispositivos móviles genera excepciones o se descarta silenciosamente.

### Causa D: Estado de Permisos en el Navegador y Windows 11
Para que cualquier navegador (Chrome, Edge) pueda emitir notificaciones, requiere dos condiciones externas al código:
1. **Permiso explícito en la barra:** El agente debe haber presionado *"Permitir"* cuando el navegador pregunta si autoriza notificaciones a `boot.tamep.bo`.
2. **Asistente de concentración de Windows 11:** Si en la barra de tareas de Windows 11 está activado el modo *"No Molestar"* o *"Asistente de concentración"*, Windows oculta y silencia todas las notificaciones emergentes de cualquier navegador.

---

## 📱 Escenario 3: ¿Qué ocurre en teléfonos móviles (Android e iOS)?

### 3.1 En Dispositivos Android
- **Con el CRM abierto o en segundo plano (minimizada en el navegador/PWA):**
  - El Service Worker y el Smart Polling / WebSockets funcionan de forma regular, emitiendo vibración y notificación emergente en la barra superior.
- **Con la aplicación cerrada por completo (deslizada hacia arriba / matar proceso):**
  - Android suspende o mata el hilo de JavaScript para ahorrar batería y memoria RAM.
  - Al igual que en Windows 11, las alertas locales se detienen. Para que un celular Android suene estando la app cerrada, se requiere que el backend de Laravel envíe un mensaje a los servidores de **Google FCM (Firebase Cloud Messaging)** a través de Web Push VAPID.

### 3.2 En Dispositivos Apple iOS (iPhone / iPad)
- En el ecosistema de Apple aplican reglas más estrictas desde iOS 16.4:
  1. La aplicación **debe estar agregada a la Pantalla de Inicio** como PWA ("Compartir" -> "Añadir a pantalla de inicio").
  2. Apple bloquea WebSockets cuando la pantalla se bloquea o el usuario cambia de app tras unos pocos segundos.
  3. Para despertar un iPhone con la pantalla apagada es obligatorio contar con Web Push (VAPID) conectado con el servicio **Apple Push Notification service (APNs)**.

---

## 🛠️ Soluciones Técnicas Implementadas en el Código

Para garantizar que las alertas funcionen con total fiabilidad cuando la aplicación esté abierta o minimizada:

1. **Eliminación del bloqueo de ventana activa:**
   - Se removió la condición restrictiva `document.hidden`. Ahora el sonido y la notificación se emiten siempre que entra un mensaje de un cliente o un pasajero entra a la cola de espera.

2. **Disparo Dual Garantizado (WebSockets + Smart Polling de Respaldo):**
   - En [useChatStore.js](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/stores/useChatStore.js), se integró el detector de nuevos mensajes y nuevos pasajeros en espera dentro de las funciones de refresco `fetchConversations()` y `syncActiveChat()`.
   - **Resultado:** Si WebSockets llega a fallar o no está configurado en cPanel, el refresco automático de fondo detecta el incremento de clientes y **dispara la notificación de todas formas**.

3. **Garantía del Service Worker con `navigator.serviceWorker.ready`:**
   - En [pwaService.js](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/services/pwaService.js), se implementó:
     ```javascript
     const reg = swRegistration || await navigator.serviceWorker.ready;
     if (reg && 'showNotification' in reg) {
         await reg.showNotification(title, options);
     }
     ```
   - Esto asegura que el Service Worker siempre esté disponible antes de enviar la alerta en Windows, Android e iOS.

4. **Alertas Fijas y Persistentes (`requireInteraction: true`):**
   - Se activó la directiva `requireInteraction: true` para que las alertas no desaparezcan a los 5 segundos, manteniéndose en pantalla hasta que el agente haga clic para atender al pasajero.

5. **Botón de Diagnóstico y Prueba con 1 Clic:**
   - En la barra superior de [AgentDashboard.vue](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/views/AgentDashboard.vue) y [AdminDashboard.vue](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/views/AdminDashboard.vue):
     - Botón **`Activar Alertas`** (amarillo) si el permiso no está otorgado.
     - Botón **`Alertas ON`** (verde) cuando está listo. Al hacerle clic, envía una **notificación de prueba inmediata** para verificar el sonido y la tarjeta visual en Windows 11.

---

## 📋 Guía de Verificación Rápida para el Agente

Para validar el funcionamiento en cualquier equipo con Windows 11:
1. Abrir el CRM en Chrome o Edge (`https://boot.tamep.bo`).
2. Verificar que el botón superior diga **`Alertas ON`** en verde. Si dice *Activar Alertas*, hacer clic y seleccionar *"Permitir"*.
3. Hacer clic sobre el botón **`Alertas ON`**: Debe sonar la campana y salir la tarjeta de prueba en la esquina inferior derecha.
4. Minimizar la ventana (o dejarla en segundo plano).
5. Enviar un mensaje desde WhatsApp al número de la aerolínea: Windows 11 emitirá el sonido y mostrará la alerta fija en pantalla.
