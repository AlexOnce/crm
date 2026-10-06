# Credenciales de Acceso - TAMep CRM

> [!WARNING]
> **Documento Confidencial:** Este archivo contiene los accesos predeterminados del sistema. No lo compartas públicamente ni lo subas a repositorios abiertos. Se recomienda cambiar las contraseñas una vez desplegado en producción.

---

## 👥 Cuentas de Acceso Predeterminadas

| Rol | Nombre | Correo Electrónico | Contraseña | Permisos y Panel |
| :--- | :--- | :--- | :--- | :--- |
| **Administrador** | Administrador General | `admin@tamep.bo` | `password123` | **Panel Admin (`/admin`)**: Gestión y alta de agentes, monitoreo de métricas, auditoría de conversaciones y reportes. |
| **Agente de Ventas** | Carlos Mendoza | `agente@tamep.bo` | `password123` | **Panel CRM (`/`)**: Bandeja de chats en vivo de WhatsApp, notas de voz, cotización y emisión de boletos. |
| **Agente de Ventas** | Laura Morales | `laura@tamep.bo` | `password123` | **Panel CRM (`/`)**: Atención concurrente de pasajeros, traspaso de conversaciones y seguimiento de leads. |

---

## 🔐 Cómo Cambiar las Contraseñas en Producción

### Opción 1: Desde el Panel Administrativo (Recomendada)
1. Inicia sesión como Administrador en `https://tudominio.com/login` con `admin@tamep.bo`.
2. Ve a la sección **Administración de Agentes**.
3. Edita cualquier usuario o crea nuevos agentes con contraseñas seguras y personalizadas.

### Opción 2: Desde la Terminal de cPanel (vía Tinker)
Si necesitas cambiar la contraseña del Administrador directamente en el servidor:
```bash
php artisan tinker
```
Luego ejecuta:
```php
$user = App\Models\User::where('email', 'admin@tamep.bo')->first();
$user->password = bcrypt('TuNuevoPasswordSeguro2026!');
$user->save();
exit;
```

---

## 🚀 Inicialización en cPanel
Para que estos usuarios queden creados automáticamente en la base de datos de tu hosting:
```bash
php artisan migrate --force
php artisan db:seed --force
```
