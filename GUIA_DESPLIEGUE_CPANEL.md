# 🚀 Guía Definitiva de Despliegue en cPanel
## CRM Transaccional de Aerolínea (Laravel 11 + Vue 3 + Meta WhatsApp Cloud API)

Esta guía detalla los pasos exactos para publicar el sistema en producción bajo el dominio **`https://boot.tamep.bo`** utilizando el paquete comprimido autoconfigurado.

---

### 🟢 1. Estado de la Conexión con Meta Cloud API
El nuevo token proporcionado fue actualizado y verificado exitosamente mediante `php artisan meta:status`:
* **Phone Number ID:** `1428599850329740`
* **Número Asignado:** `+1 555-677-4453` (Test Number)
* **Calidad:** `GREEN` (Óptima)
* **WABA ID:** `1393041265542970`
* **Webhook Meta Registrado:** `https://boot.tamep.bo/webhook/whatsapp`
* **Verify Token:** `crm_airline_verify_token_2026`

---

### 📦 2. Paquete de Despliegue Generado (`crm_cpanel_deploy.zip`)
En la raíz de este proyecto se ha generado el archivo:
📁 **`crm_cpanel_deploy.zip`**

Este archivo contiene:
- Todo el backend de Laravel 11 optimizado.
- La carpeta `vendor/` completa (no requiere correr `composer install` en el servidor).
- La compilación de producción del frontend (`public/build/` con Vue 3 + Tailwind CSS + Lucide Icons).
- Archivo `.htaccess` en la raíz y en `public/` con protección contra descargas no autorizadas de `.env`, `.git` y backups.
- Plantilla `.env.production` pre-configurada con las credenciales de Meta y HTTPS.

---

### 🛠️ 3. Pasos de Instalación en cPanel

#### Paso 3.1: Subir y Extraer el Archivo
1. Ingresa a tu **cPanel**.
2. Abre el **Administrador de Archivos (File Manager)**.
3. Si `boot.tamep.bo` es un subdominio o dominio adicional:
   - Ve al directorio raíz asignado a dicho dominio (por ejemplo `/home/tu_usuario/boot.tamep.bo` o `/home/tu_usuario/public_html`).
4. Haz clic en **Cargar (Upload)** y sube `crm_cpanel_deploy.zip`.
5. Haz clic derecho sobre el archivo subido y selecciona **Extraer (Extract)**.

> [!TIP]
> **Configuración de DocumentRoot (Recomendado por Seguridad):**
> En **cPanel > Dominios**, edita el dominio `boot.tamep.bo` y haz que su DocumentRoot apunte directamente a la carpeta `/public` del proyecto (ej: `/home/tu_usuario/boot.tamep.bo/public`).
> *Nota: Si tu hosting no permite cambiar el DocumentRoot, el archivo `.htaccess` que incluimos en la raíz redirigirá el tráfico automáticamente a `/public`.*

---

#### Paso 3.2: Crear la Base de Datos MySQL en cPanel
1. En cPanel, dirígete a **Bases de datos MySQL**.
2. Crea una nueva base de datos (por ejemplo `usuario_tamepcrm`).
3. Crea un usuario MySQL (por ejemplo `usuario_tamepuser`) con una contraseña segura.
4. Asocia el usuario a la base de datos y marca la casilla **TODOS LOS PRIVILEGIOS (ALL PRIVILEGES)**.

---

#### Paso 3.3: Configurar el archivo `.env`
1. En el Administrador de Archivos de cPanel, asegúrate de activar **"Mostrar archivos ocultos (dotfiles)"** en la configuración de la esquina superior derecha.
2. Abre y edita el archivo **`.env`** (o renombra `.env.production` a `.env`):
   ```env
   APP_NAME="CRM WhatsApp Aerolínea"
   APP_ENV=production
   APP_KEY=base64:MTtagDKHs7EGce7V6sAfJ3tsXHKrqce57859KRCKM5U=
   APP_DEBUG=false
   APP_URL=https://boot.tamep.bo

   # Base de Datos creada en el Paso 3.2
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=usuario_tamepcrm
   DB_USERNAME=usuario_tamepuser
   DB_PASSWORD=tu_contraseña_creada

   # Conexión Meta WhatsApp (Ya viene configurada)
   META_WHATSAPP_TOKEN=EAAZAu3Cz60W0BSiVdZBCj4srvuPhwNcIPlmg5k2pi5PloY2NzwCL2XYaTZBMpP65bapZBIZCDb2Jm14Y4X9pSZByZAGGCUEklL58m1vOsZBRNB1Jf58I8ab0UAEZAnm8A1GqYeNZBZAX0EaJlfRxXiWL1UU8aI64ZCUgLEbG3ZCrwQD2sbZAwSIaBWWsa4W94tZC8grXYGq5BZCvKMZCWQRzkdJqfcR5m7QZCrPaV7hMhKvxQaR73ScYOjZCA468ToCRWszpMqZAp4vwnZBZAGTRzOzDIy8ugEELVpOWzN
   META_PHONE_NUMBER_ID=1428599850329740
   META_WABA_ID=1393041265542970
   META_VERIFY_TOKEN=crm_airline_verify_token_2026
   META_WHATSAPP_VERSION=v20.0

   # WebSockets en Tiempo Real con Pusher (Opcional - Si se dejan vacíos, el sistema usa Smart-Sync automático)
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=tu_app_id
   PUSHER_APP_KEY=tu_app_key
   PUSHER_APP_SECRET=tu_app_secret
   PUSHER_APP_CLUSTER=us2
   VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
   VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
   ```
3. Guarda los cambios.

---

#### Paso 3.4: Ejecutar Migraciones y Enlace Simbólico
Abre la herramienta **Terminal** en cPanel (o conéctate vía SSH) y ejecuta:

```bash
# 1. Navegar a la carpeta del proyecto
cd /home/tu_usuario/boot.tamep.bo

# 2. Ejecutar migraciones e inicializar usuarios administradores y de prueba
php artisan migrate --seed --force

# 3. Crear enlace simbólico para que los boletos PDF emitidos sean accesibles
php artisan storage:link

# 4. Optimizar cachés de Laravel para producción (máxima velocidad)
php artisan optimize
```

> [!NOTE]
> **Usuarios y Contraseñas Creados por el Seeder:**
> - **Administrador General:** `admin@aerolinea.com` / `password123`
> - **Agente de Ventas:** `agente@aerolinea.com` / `password123`
> *(Puedes cambiar las contraseñas inmediatamente desde el panel o con `php artisan agent:password`).*

---

#### Paso 3.5: Configurar Cron Job de Laravel
Para que los procesos automáticos y colas de Laravel funcionen periódicamente:
1. En cPanel, busca el módulo **Tareas Cron (Cron Jobs)**.
2. Añade una nueva tarea programada con frecuencia de **cada minuto (`* * * * *`)**:
   ```bash
   * * * * * cd /home/tu_usuario/boot.tamep.bo && php artisan schedule:run >> /dev/null 2>&1
   ```

---

### 🌐 4. Validación Final con Meta for Developers
1. Ingresa a [developers.facebook.com](https://developers.facebook.com) > Tu Aplicación de WhatsApp.
2. En el menú lateral ve a **WhatsApp > Configuración**.
3. En la sección **Webhooks**:
   - **URL de Callback:** `https://boot.tamep.bo/webhook/whatsapp`
   - **Token de Verificación:** `crm_airline_verify_token_2026`
   - Haz clic en **Verificar y Guardar**.
4. En **Campos del Webhook**, asegúrate de que **`messages`** esté suscrito.
5. ¡Listo! Envía un mensaje desde cualquier WhatsApp personal al número **`+1 555-677-4453`** para iniciar el flujo interactivo.
