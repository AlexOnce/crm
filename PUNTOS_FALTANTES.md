# 📋 Checklist de Puntos Faltantes y Próximos Pasos
## CRM Transaccional de Aerolínea (Laravel 11 + Vue 3 + Meta Cloud API)

Este documento centraliza todas las configuraciones, credenciales y tareas pendientes para pasar este sistema del entorno de desarrollo local al entorno productivo en cPanel.

---

### 1. 🔑 Conexión Real con Meta Cloud API (WhatsApp)

Actualmente el sistema utiliza un simulador local que permite probar todo el flujo sin errores. Para enviar y recibir mensajes reales en WhatsApp:

- [x] **1.1. Obtener Token Permanente de Meta:** *(Completado)*
  - Token de Acceso configurado y validado contra Meta Graph API.
  - WABA ID vinculada: `1393041265542970` (Test WhatsApp Business Account).

- [x] **1.2. Actualizar el archivo `.env`:** *(Completado y Verificado)*
  ```env
  META_WHATSAPP_TOKEN="EAAZAu3Cz60W0..."        # Token permanente configurado
  META_PHONE_NUMBER_ID="1428599850329740"       # Phone ID verificado (+1 555-677-4453)
  META_WABA_ID="1393041265542970"               # WABA Account ID
  META_VERIFY_TOKEN="crm_airline_verify_token_2026"
  META_WHATSAPP_VERSION="v20.0"
  ```
  - Verificado con comando: `php artisan meta:status` (Conexión OK, calidad GREEN).

- [x] **1.3. Registrar el Webhook en Meta for Developers:** *(Configurado)*
  - **URL de Callback:** `https://boot.tamep.bo/webhook/whatsapp` o `https://boot.tamep.bo/api/webhooks/whatsapp`
  - **Verify Token:** `crm_airline_verify_token_2026`
  - **Campos a suscribir:** Marcar la casilla **`messages`**.
  - *Soporta rutas con o sin `/api` y en singular o plural (`/webhook/whatsapp`, `/webhooks/whatsapp`) con bypass de CSRF automático.*

---

### 2. 👥 Gestión de Usuarios y Agentes de Venta

Para la operación del CRM se requiere crear y gestionar a los agentes humanos que atenderán las conversaciones de WhatsApp cuando el bot haga handoff.

- [x] **2.1. Comandos de Gestión Rápida de Agentes (Artisan):** *(Completado y Verificado)*
  - Crear agente: `php artisan agent:create "Nombre" email@aerolinea.com password123 --role=agent`
  - Listar agentes: `php artisan agent:list`
  - Restablecer contraseña: `php artisan agent:password email@aerolinea.com nuevaClave123`
  - Activar / Desactivar agente: `php artisan agent:toggle email@aerolinea.com`

- [x] **2.2. API de Gestión de Agentes (Panel Operativo):** *(Completado y Verificado)*
  - Endpoints protegidos en `/api/agents` (`GET`, `POST`, `PUT`, `POST .../toggle`) para administración con Laravel Sanctum.

- [x] **2.3. Panel Visual en la Interfaz Web (Vue 3 SPA):** *(Completado y Verificado)*
  - Botón interactivo **`👥 Equipo / Agentes`** en la cabecera del CRM.
  - Modal para consultar el equipo en tiempo real, crear nuevos agentes desde el navegador, suspender/activar accesos y cambiar contraseñas.

- [x] **2.4. Reasignación y Transferencia de Chats en Vivo:** *(Completado y Verificado)*
  - Menú flotante **`↪️ Transferir`** en la ventana de chat para derivar la conversación en 1 clic a cualquier otro asesor del equipo activo.
  - Generación de nota interna automática en el historial del chat para auditoría.

---

### 3. 🚀 Despliegue en cPanel (Optimizado para 8GB RAM)

*Consulte la guía completa paso a paso en [GUIA_DESPLIEGUE_CPANEL.md](file:///c:/laragon/www/CRM%20WHATSAPP/GUIA_DESPLIEGUE_CPANEL.md).*

- [x] **3.1. Paquete de Despliegue Generado (`crm_cpanel_deploy.zip`):**
  - Script automatizado `crear_paquete_cpanel.ps1` que compila Vite, incluye `vendor`, crea la estructura limpia de `storage`, `.env.production` y reglas de seguridad en `.htaccess`.
- [x] **3.2. Configuración de Directorio Raíz (DocumentRoot):**
  - Configurado `.htaccess` dual (en raíz y en `/public`) para soportar tanto DocumentRoot hacia `/public` como redirección automática segura en `public_html`.
- [x] **3.3. Base de Datos y Plantilla de Producción:**
  - Archivo `.env.production` preparado con credenciales de Meta Cloud API verificadas y plantilla MySQL para cPanel.
- [ ] **3.4. Subida y Migración en cPanel:**
  - Subir `crm_cpanel_deploy.zip` al Administrador de Archivos de cPanel, extraer y ejecutar `php artisan migrate --seed --force` y `php artisan storage:link`.
- [ ] **3.5. Cron Job en cPanel:**
  - Registrar en Tareas Cron: `* * * * * cd /home/tu_usuario/boot.tamep.bo && php artisan schedule:run >> /dev/null 2>&1`.

