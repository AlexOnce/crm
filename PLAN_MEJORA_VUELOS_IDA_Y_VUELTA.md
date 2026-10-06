# 📋 Plan de Implementación: Vuelos de Ida y Vuelta (Round-Trip)

Este documento detalla la hoja de ruta y especificaciones técnicas para implementar la búsqueda y reserva de vuelos de **Ida y Vuelta** en WhatsApp y el CRM de TAMep.

---

## 1. Archivo del Flow Creado

* **Ubicación:** [`resources/flows/tamep_flight_search_roundtrip_flow.json`](file:///c:/laragon/www/CRM%20WHATSAPP/CRM%20WHATSAPP/resources/flows/tamep_flight_search_roundtrip_flow.json)
* **Versión de Flow:** `7.3`
* **Campos incorporados:**
  1. `trip_type`: Dropdown con opciones `ONE_WAY` (Solo Ida) y `ROUND_TRIP` (Ida y Vuelta).
  2. `origin`: Selección de ciudad origen (`VVI`, `LPB`, `CBB`, `CIJ`).
  3. `destination`: Selección de ciudad destino.
  4. `flight_date`: Fecha de salida (Obligatorio).
  5. `return_date`: Fecha de regreso (Opcional si el viaje es solo ida).
  6. `adults`, `children`, `babies`: Conteo detallado de pasajeros.

---

## 2. Hoja de Ruta para Mañana

### Fase A: Recepción del Webhook de Meta
* **Archivo:** [`app/Http/Controllers/Webhooks/MetaWebhookController.php`](file:///c:/laragon/www/CRM%20WHATSAPP/CRM%20WHATSAPP/app/Http/Controllers/Webhooks/MetaWebhookController.php)
* **Ajuste:**
  * Extraer `trip_type` y `return_date` del `nfm_reply`.
  * Formatear el mensaje resumen del cliente para que refleje si solicitó Ida o Ida y Vuelta:
    ```text
    ✈️ *BÚSQUEDA DE ITINERARIO (FORMULARIO)*
    🔄 *Tipo:* Ida y Vuelta
    🛫 *Origen:* LPB ➔ 🛬 *Destino:* VVI
    📅 *Ida:* 15/10/2026 | 🔄 *Retorno:* 20/10/2026
    👥 *Pasajeros:* 1 Adulto
    ```

### Fase B: Flujo Conversacional en el Chatbot
* **Archivo:** [`app/Services/ChatbotEngineService.php`](file:///c:/laragon/www/CRM%20WHATSAPP/CRM%20WHATSAPP/app/Services/ChatbotEngineService.php)
* **Lógica:**
  1. Si `trip_type === 'ROUND_TRIP'` y viene `return_date`:
     * **Paso 1:** Mostrar vuelos de Ida para `flight_date` y guardar el estado temporal `VUELOS_IDA_MOSTRADOS`.
     * **Paso 2:** Al presionar el botón de vuelo de ida, guardar el vuelo seleccionado en sesión (`session['outbound_flight']`), invertir automáticamente la ruta (`destination ➔ origin`) y mostrar los vuelos disponibles para la fecha de regreso `return_date`.
     * **Paso 3:** Al presionar el vuelo de retorno, guardar `session['return_flight']`, enviar el mensaje consolidado de ambos tramos y activar el formulario de emisión de pasajeros (`replyWithFlow(..., 'PASSENGER_INFO')`).
  2. Si `trip_type === 'ONE_WAY'`:
     * Mantener el flujo actual intacto sin cambios.

### Fase C: Base de Datos y Panel de Boletos (CRM)
* **Migración:**
  * Agregar `return_date`, `return_flight_number` y `trip_type` a la tabla `leads` (o almacenarlos dentro de `bot_payload`).
* **Componente Vue:**
  * En [`TicketBookingPanel.vue`](file:///c:/laragon/www/CRM%20WHATSAPP/CRM%20WHATSAPP/resources/js/components/booking/TicketBookingPanel.vue), mostrar dos tarjetas de vuelo (Ida y Vuelta) cuando el lead sea de tipo `ROUND_TRIP`.

---

## 3. Pasos para Activar en Meta Business Suite
1. Ir al panel de **WhatsApp Manager** ➔ **Flows**.
2. Crear o editar el Flow de Búsqueda de Vuelos.
3. Copiar el contenido exacto de [`tamep_flight_search_roundtrip_flow.json`](file:///c:/laragon/www/CRM%20WHATSAPP/CRM%20WHATSAPP/resources/flows/tamep_flight_search_roundtrip_flow.json) en el editor JSON.
4. Guardar y Publicar.
