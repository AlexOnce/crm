# 📘 Guía Oficial de WhatsApp Flows para TAMep CRM

Esta guía detalla el procedimiento para configurar, probar y publicar el formulario nativo de **WhatsApp Flows** para la captura segura y legal de datos de pasajeros en cumplimiento con las normativas de Meta y la DGAC.

---

## 📋 ¿Por qué WhatsApp Flows en TAMep?

Meta prohíbe taxativamente en su *WhatsApp Business Messaging Policy* solicitar identificadores sensibles (Carnet de Identidad, Cédula, DNI o Pasaporte) en texto plano a través del chat tradicional. 

Con **WhatsApp Flows**, el pasajero completa un formulario nativo interactivo dentro de la app sin salir de WhatsApp, con validación de campos, selector de fecha con calendario y listas desplegables oficiales.

---

## 🛠️ Paso a Paso: Publicación del Flow en Meta

### Paso 1: Ingresar al Administrador de WhatsApp
1. Accede a [Meta Business Suite](https://business.facebook.com/).
2. En el menú lateral izquierdo, ve a **Configuración del Negocio** o directamente a **Administrador de WhatsApp** (*WhatsApp Manager*).
3. Selecciona tu cuenta de WhatsApp Business (WABA) de TAMep.

### Paso 2: Crear el Flujo
1. En el menú de navegación de la izquierda, haz clic en **Herramientas de la cuenta** (*Account tools*) > **Flows (Flujos)**.
2. Haz clic en el botón azul **Crear Flow** (*Create Flow*).
3. Completa los datos iniciales:
   * **Nombre del Flow:** `tamep_registro_pasajero`
   * **Categorías:** Selecciona `Atención al cliente` (*Customer Support*) o `Iniciar sesión / Registrarse` (*Sign in / Register*).
4. Haz clic en **Crear**.

### Paso 3: Importar el Esquema JSON de TAMep
1. En la esquina superior derecha del editor visual de Flows, haz clic en el icono de código `{ }` o en el menú de tres puntos `...` y selecciona **Importar JSON** (*Import JSON*).
2. Abre en tu editor el archivo que generamos en el CRM:
   👉 [`resources/flows/tamep_passenger_flow.json`](file:///c:/laragon/www/CRM%20WHATSAPP/resources/flows/tamep_passenger_flow.json)
3. Copia todo su contenido y pégalo en el editor de Meta.
4. Presiona **Guardar** (*Save*).

### Paso 4: Previsualizar y Probar en el Simulador
* En el panel derecho de Meta verás el simulador interactivo de teléfono móvil con el formulario oficial de TAMep:
  - 👤 **Nombre y Apellidos completos**
  - 📄 **Tipo de Documento:** Cédula de Identidad (C.I.), Pasaporte, Cédula de Extranjero
  - 🔢 **Número de Documento**
  - 🏛️ **Lugar de Expedición:** LP, SC, CB, TJ, BE, PD, CH, OR, PO, EXT
  - 🎂 **Fecha de Nacimiento (Selector de fecha interactivo)**
  - ✈️ **Categoría de Pasajero:** Adulto, Niño (2-11 años), Infante (<2 años)
  - 🧳 **Equipaje / Requerimientos:** Estándar, Maleta extra bodega, Mascota en cabina (PETC), Asistencia / Silla de ruedas
  - 🔘 Botón: **Confirmar y Enviar Datos**
* Prueba completar los campos y presiona el botón para verificar que no haya errores de validación.

### Paso 5: Publicar el Flow
1. Haz clic en el botón **Publicar** (*Publish*) en la esquina superior derecha.
2. Una vez publicado, Meta le asignará un **ID numérico único** (por ejemplo: `108492019382101`).
3. Copia ese número de **Flow ID**.

---

## 🔑 Paso 6: Configurar el Flow ID en el CRM

Abre el archivo `.env` de tu proyecto TAMep CRM y coloca el ID obtenido:

```env
META_FLOW_PASSENGER_ID=108492019382101
```

Luego, en tu terminal o servidor cPanel, borra la caché de configuración:

```bash
php artisan config:clear
```

---

## 🚀 ¿Cómo Funciona la Experiencia Completa?

1. **Selección de Vuelo:**
   El pasajero consulta un itinerario (ej: Santa Cruz a La Paz) y presiona el botón interactivo de su vuelo (ej: `TM-204`).
2. **Despliegue del Formulario:**
   El bot detecta la configuración de `META_FLOW_PASSENGER_ID` y envía un mensaje interactivo con el botón **`[ 📝 Completar Datos ]`**.
3. **Llenado en WhatsApp:**
   El cliente pulsa el botón, se abre la ventana emergente nativa y envía sus datos en menos de 30 segundos.
4. **Recepción en Laravel (MetaWebhookController):**
   Meta envía un evento `interactive` con `nfm_reply`. El CRM deserializa el JSON y:
   * Genera un mensaje formateado en el chat para el historial:
     ```text
     📋 DATOS DE PASAJERO REGISTRADOS (FLOW)
     👤 Pasajero: Juan Pérez
     📄 Documento: CI 8493021 LP
     🎂 F. Nacimiento: 1990-05-14
     ✈️ Categoría: Adulto
     🧳 Equipaje/Req: Equipaje Estándar (5kg mano + 20kg bodega)
     ```
   * Guarda los datos estructurados en la tabla `leads` y en la sesión de la conversación.
   * Dispara una alerta **Web Push VAPID** a los asesores para atender de inmediato.
   * Envía confirmación cordial automática al pasajero indicándole que su solicitud ya está con un asesor.

---

## 🛡️ Mecanismo de Respaldo (Fallback de Seguridad)

Si por alguna razón:
* Aún no has configurado `META_FLOW_PASSENGER_ID` en el `.env`, o
* El cliente utiliza una versión muy antigua de WhatsApp en un teléfono que no soporta Flows:

El sistema cambia automáticamente a **Modo Seguro sin Carnet**:
> *"Para continuar con tu cotización, escribe tu Nombre Completo y cuántos pasajeros viajarán. (Ejemplo: Juan Pérez - 1 adulto)"*

De esta manera, **tu cuenta de WhatsApp Business nunca correrá riesgo de sanciones o penalizaciones por parte de Meta**.
