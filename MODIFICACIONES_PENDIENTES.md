# 📋 Registro de Modificaciones Pendientes y Hoja de Ruta - TAMep CRM

**Fecha de Registro:** 02 de Octubre de 2026
**Sistema:** TAMep WhatsApp CRM & Chatbot Aerocomercial

---

## 📌 Resumen de Tareas Pendientes

| # | Módulo / Mejora | Descripción | Prioridad | Estado |
| :-: | :--- | :--- | :---: | :---: |
| 1 | **Ingreso Numérico de Pasajeros** | Reemplazar los dropdowns (tope de 4) por campos de texto numéricos (input-type: number) para Adultos, Niños y Bebés sin límite artificial. | 🟢 Alta | ✅ Completado e Integrado (69 tests OK) |
| 2 | **Vuelos de Ida y Vuelta (Round-Trip)** | Formulario interactivo con trip_type y return_date + Flujo guiado en 2 pasos (Ida a Vuelta). | 🔴 Alta | Flow JSON creado (Listo para activar) |
| 3 | **Detección Dual de Rutas por Texto** | Permitir que el bot entienda rutas escritas directamente por chat (ej. 'La Paz a Santa Cruz') sin forzar exclusivamente el botón del Flow. | 🟡 Media | Pendiente de desarrollo |
| 4 | **Nómina de Acompañantes en Compras Grupales** | El Chatbot interactivo solicita los datos de cada acompañante uno a uno + visualización y copia de nómina en el CRM. | 🟢 Alta | ✅ Completado e Integrado (71 tests OK) |
| 5 | **Despliegue Final en Producción (cPanel)** | Subir CRM WHATSAPP.zip, ejecutar migraciones y conectar el webhook en vivo en Meta for Developers. | 🟢 Operativa | Paquete compilado |

---

## 🛠️ Detalle Técnico de Cada Modificación

### 1. Ingreso Numérico Directo de Pasajeros (Sin Límite de 4)
* **Objetivo:** Permitir que familias grandes, delegaciones deportivas o grupos militares (ej. 10, 15 pasajeros) puedan escribir la cantidad exacta sin restricciones.
* **Cambio en WhatsApp Flow (tamep_flight_search_flow.json):**
  * Cambiar los componentes Dropdown de adults, children y babies por TextInput con input-type: 'number'.
  * **Beneficio:** Abre automáticamente el teclado numérico en el celular del cliente.
* **Cambio en Backend Laravel:**
  * En ChatbotEngineService.php y FlightMicroservice.php:
    * Reemplazar max(1, min(4, ...)) por max(1, min(70, (int) ['adults'])) (capacidad del avión Avro RJ70).
* **Comportamiento en CRM (TicketBookingPanel.vue):**
  * La ficha ya detecta automáticamente N PAX y adapta el botón a: 'N Boletos Vendidos'.

---

### 2. Vuelos de Ida y Vuelta (Round-Trip)
* **Objetivo:** Permitir cotizar y reservar viajes redondos en un solo proceso interactivo.
* **Archivo de Flow Creado:**
  * resources/flows/tamep_flight_search_roundtrip_flow.json (Versión 7.3 validada para Meta Flow Builder).
  * Incluye selector de trip_type (Solo Ida o Ida y Vuelta) y campo return_date (Fecha de retorno).
* **Flujo Conversacional en el Bot (ChatbotEngineService.php):**
  1. Si es ROUND_TRIP, el bot muestra primero los vuelos de Ida.
  2. Al seleccionar el vuelo de ida, el bot invierte la ruta (destination a origin) para la fecha de regreso y muestra los vuelos de Vuelta.
  3. Al elegir la vuelta, genera el resumen consolidado de ambos tramos y abre el formulario de emisión de pasajeros.
* **Documentación Completa:** Consultar PLAN_MEJORA_VUELOS_IDA_Y_VUELTA.md.

---

### 3. Detección Dual de Rutas (Texto Libre + Botón)
* **Objetivo:** Evitar bucles o frustración en usuarios que ignoran el botón interactivo del Flow y escriben directamente: '1', 'vuelos a santa cruz' o 'LPB a VVI'.
* **Ajuste:**
  * En ChatbotEngineService::handleMenuPrincipal(), incorporar el analizador de rutas por texto para que, si el mensaje contiene ciudades válidas, consulte directamente a Transclick sin obligar al usuario a abrir el formulario.

---

### 4. Gestión de Acompañantes en Reservas Múltiples (PAX > 1)
* **Objetivo:** Registrar ordenadamente los datos de todos los pasajeros cuando una sola persona compra varios boletos.
* **Operativa Implementada:**
  * El formulario de WhatsApp recoge al titular (Pasajero 1).
  * Si `passengers_count > 1`, el Chatbot no pasa al asesor de inmediato: permanece activo (`COLLECTING_COMPANIONS`) y solicita interactivamente los nombres y documentos de cada acompañante (Acompañante 1 de N, Acompañante 2 de N, etc.).
  * Al completar todos los acompañantes, el bot confirma la nómina completa consolidada por WhatsApp y transfiere al asesor (`AGENT_HANDOFF`).
  * En el panel lateral del CRM ([`TicketBookingPanel.vue`](file:///c:/laragon/www/CRM%20WHATSAPP/resources/js/components/booking/TicketBookingPanel.vue)), el asesor visualiza la nómina de todos los pasajeros con sus carnets y un botón para **Copiar Nómina Completa** en 1 clic para la emisión oficial.

---

### 5. Despliegue en Servidor de Producción (cPanel)
* **Archivos listos:**
  * CRM WHATSAPP.zip y public.zip.
  * Guía paso a paso: GUIA_DESPLIEGUE_CPANEL.md.
* **Pasos al migrar:**
  1. Extraer en cPanel (public_html).
  2. Configurar base de datos en .env.
  3. Ejecutar: php artisan migrate --force y php artisan storage:link.
  4. Configurar Webhook en Meta for Developers: https://tudominio.bo/api/webhooks/whatsapp.


* **Registro Dinámico de Pasajeros (1 a 5 PAX):** Diseñado con formularios individuales consecutivos orquestados por el bot. Documento de arquitectura: PLAN_REGISTRO_DINAMICO_PASAJEROS.md.