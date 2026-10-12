# Guía de Trabajo Remoto (Oficina ⇄ Casa) - CRM+

Esta guía detalla los pasos para configurar el proyecto en tu computadora de casa y el flujo diario de sincronización con Git.

---

## 1. Antes de salir de la oficina (Una sola vez)

> [!IMPORTANT]
> El archivo `.env` **no está en GitHub** por motivos de seguridad (está ignorado para proteger tokens y contraseñas).

1. **Respaldar el archivo `.env`:**
   - Copia el archivo `.env` de esta máquina (`c:\laragon\www\CRM+\.env`) a un USB, Google Drive personal o chat privado. Contiene los tokens de Meta WhatsApp, la clave de la aplicación y la configuración de servicios.
2. **Exportar la Base de Datos (Opcional):**
   - Si deseas tener los mismos registros de prueba y conversaciones en casa:
   - Abre **HeidiSQL** o **phpMyAdmin** en Laragon.
   - Selecciona la base de datos `kqlurhnh_crm_whatsapp` y expórtala a un archivo `.sql`.

---

## 2. Configuración inicial en la PC de Casa (Solo la primera vez)

### Requisitos previos
- **Laragon** instalado (con PHP 8.2 u 8.3, MySQL y Node.js/npm).
- **Git** instalado.

### Pasos de instalación:
1. Abre la terminal (PowerShell o Git Bash) en tu directorio de proyectos de Laragon:
   ```powershell
   cd C:\laragon\www
   ```

2. Clona el repositorio con tu token de GitHub:
   ```powershell
   git clone https://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@github.com/AlexOnce/crm.git CRM+
   ```

3. Entra a la carpeta del proyecto:
   ```powershell
   cd CRM+
   ```

4. **Coloca tu archivo `.env`:**
   - Pega en la raíz de la carpeta `CRM+` el archivo `.env` que trajiste de la oficina.

5. Instala las dependencias de backend y frontend:
   ```powershell
   composer install
   npm install
   npm run build
   ```

6. Configura el almacenamiento y la base de datos:
   - En Laragon (HeidiSQL o phpMyAdmin), crea la base de datos llamada `kqlurhnh_crm_whatsapp`.
   - Si trajiste el archivo `.sql`, impórtalo. Si prefieres empezar desde cero, ejecuta:
     ```powershell
     php artisan migrate
     ```
   - Crea el enlace simbólico para archivos multimedia:
     ```powershell
     php artisan storage:link
     ```

---

## 3. Flujo Diario de Trabajo (Sincronización)

### A. Al comenzar a trabajar en casa
Antes de tocar cualquier archivo, descarga las últimas modificaciones hechas en la oficina:
```powershell
git pull
```

---

### B. Al terminar la jornada en casa
Para guardar y subir todo lo que hiciste hacia GitHub:
```powershell
git add .
git commit -m "avance desde casa: resumen de lo trabajado"
git push
```

---

### C. Al volver a la oficina al día siguiente
Antes de comenzar a programar en la oficina, descarga los cambios hechos en casa:
```powershell
git pull
```

---

## 4. Comandos Útiles

| Acción | Comando |
| :--- | :--- |
| Ver estado de archivos modificados | `git status` |
| Descargar cambios del repositorio | `git pull` |
| Subir cambios al repositorio | `git push` |
| Limpiar cachés de Laravel si algo no carga | `php artisan optimize:clear` |
| Recompilar frontend si haces cambios en vistas/JS | `npm run build` |
