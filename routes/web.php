<?php

use App\Http\Controllers\Api\FlightBookingController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Webhook endpoints compatibles para Meta Cloud API (cuando no se incluye el prefijo /api)
Route::get('/webhook/whatsapp', [MetaWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [MetaWebhookController::class, 'handle']);
Route::get('/webhooks/whatsapp', [MetaWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [MetaWebhookController::class, 'handle']);

// 1. Endpoint auxiliar para crear y auto-reparar el enlace simbólico de storage en cPanel vía web
Route::get('/create-storage-link', function (Request $request) {
    // Protección de seguridad: requiere clave de autorización en producción
    $providedKey = (string) ($request->query('key') ?: $request->header('X-Maintenance-Key'));
    $appKey = (string) config('app.key');
    $isAuthorized = (! empty($appKey) && ! empty($providedKey) && hash_equals($appKey, $providedKey))
        || app()->environment('local');

    if (! $isAuthorized) {
        abort(403, 'Acceso denegado: Se requiere autorización de mantenimiento.');
    }

    $publicStorage = public_path('storage');
    $targetStorage = storage_path('app/public');
    $log = [];

    // Asegurar que la carpeta destino exista
    if (! file_exists($targetStorage)) {
        mkdir($targetStorage, 0755, true);
        $log[] = "Directorio destino creado: {$targetStorage}";
    }

    // Si ya existe algo en public/storage (symlink roto o carpeta vacía), removerlo para poder recrearlo
    if (is_link($publicStorage) || file_exists($publicStorage)) {
        if (is_link($publicStorage)) {
            $prevTarget = @readlink($publicStorage);
            @unlink($publicStorage);
            $log[] = "Enlace previo removido (apuntaba a: {$prevTarget})";
        } elseif (is_dir($publicStorage)) {
            // Verificar si es un directorio real
            $files = array_diff(scandir($publicStorage) ?: [], ['.', '..']);
            if (empty($files)) {
                @rmdir($publicStorage);
                $log[] = 'Carpeta vacía public/storage removida para permitir symlink.';
            } else {
                $log[] = 'Aviso: public/storage es un directorio real con '.count($files).' archivos.';
            }
        }
    }

    // Intentar crear el enlace simbólico relativo
    $created = false;
    try {
        Artisan::call('storage:link', [
            '--relative' => true,
            '--force' => true,
        ]);
        $artisanOutput = trim(Artisan::output());
        $log[] = "Artisan storage:link output: {$artisanOutput}";
        $created = is_link($publicStorage) && file_exists($publicStorage);
    } catch (Throwable $e) {
        $log[] = 'Error Artisan: '.$e->getMessage();
    }

    // Si Artisan no pudo crearlo, intentar con la función nativa symlink de PHP
    if (! is_link($publicStorage) && ! file_exists($publicStorage)) {
        try {
            // Enlace relativo: desde public/ hasta ../storage/app/public
            if (@symlink('../storage/app/public', $publicStorage)) {
                $created = true;
                $log[] = 'Symlink nativo relativo de PHP creado exitosamente hacia ../storage/app/public';
            } elseif (@symlink($targetStorage, $publicStorage)) {
                $created = true;
                $log[] = 'Symlink nativo absoluto de PHP creado exitosamente.';
            }
        } catch (Throwable $e) {
            $log[] = 'Error PHP symlink: '.$e->getMessage();
        }
    }

    // Verificar archivos existentes en storage/app/public
    $existingAudios = [];
    if (file_exists($targetStorage.'/audio')) {
        $existingAudios = array_slice(array_diff(scandir($targetStorage.'/audio') ?: [], ['.', '..']), 0, 10);
    }

    return response()->json([
        'success' => $created || file_exists($publicStorage),
        'is_link' => is_link($publicStorage),
        'link_target' => is_link($publicStorage) ? @readlink($publicStorage) : null,
        'public_storage_exists' => file_exists($publicStorage),
        'target_exists' => file_exists($targetStorage),
        'audios_sample' => $existingAudios,
        'log' => $log,
    ]);
});

// 2. Failsafe para servir archivos de storage público (boletos PDF, audios, fotos) en cPanel si el symlink no existe
Route::get('/storage/{path}', function (string $path) {
    // Mitigación estricta contra Path Traversal (Directory Traversal / LFI)
    if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
        abort(404, 'Ruta de archivo no válida.');
    }

    $basePublicStorage = realpath(storage_path('app/public'));
    if ($basePublicStorage) {
        $candidatePath = realpath($basePublicStorage.DIRECTORY_SEPARATOR.$path);
        if ($candidatePath && str_starts_with($candidatePath, $basePublicStorage) && file_exists($candidatePath) && ! is_dir($candidatePath)) {
            return response()->file($candidatePath);
        }
    }

    $publicFolderStorage = realpath(public_path('storage'));
    if ($publicFolderStorage) {
        $candidatePath = realpath($publicFolderStorage.DIRECTORY_SEPARATOR.$path);
        if ($candidatePath && str_starts_with($candidatePath, $publicFolderStorage) && file_exists($candidatePath) && ! is_dir($candidatePath)) {
            return response()->file($candidatePath);
        }
    }

    abort(404, 'Recurso no encontrado.');
})->where('path', '.*');

// 3. Visualización y descarga/impresión oficial de Boletos Electrónicos (E-Tickets)
Route::get('/tickets/view/{lead}', [FlightBookingController::class, 'viewTicketHtml'])->name('tickets.view');

// 4. Cualquier ruta que no sea de la API, Webhooks, Storage o Tickets se envía a la SPA de Vue 3
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|webhook|webhooks|storage|create-storage-link|tickets).*$');
