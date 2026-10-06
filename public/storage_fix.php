<?php

/**
 * Script de reparación directa del enlace simbólico en cPanel
 * URL de ejecución: https://boot.tamep.bo/storage_fix.php
 */
header('Content-Type: application/json; charset=utf-8');

$publicDir = __DIR__;
$publicStorage = $publicDir.'/storage';
$targetStorage = dirname($publicDir).'/storage/app/public';

$log = [];

// 1. Asegurar que storage/app/public exista
if (! file_exists($targetStorage)) {
    if (@mkdir($targetStorage, 0755, true)) {
        $log[] = "Directorio de almacenamiento creado: {$targetStorage}";
    } else {
        $log[] = "No se pudo crear directorio de almacenamiento: {$targetStorage}";
    }
} else {
    $log[] = "Directorio destino existe: {$targetStorage}";
}

// 2. Si public/storage ya existe
if (is_link($publicStorage) || file_exists($publicStorage)) {
    if (is_link($publicStorage)) {
        $oldTarget = @readlink($publicStorage);
        $log[] = "Enlace existente detectado (apuntaba a: {$oldTarget})";
        if (@unlink($publicStorage)) {
            $log[] = 'Enlace previo roto eliminado correctamente.';
        } else {
            $log[] = 'No se pudo eliminar el enlace previo con unlink().';
        }
    } elseif (is_dir($publicStorage)) {
        $files = array_diff(scandir($publicStorage) ?: [], ['.', '..']);
        if (empty($files)) {
            if (@rmdir($publicStorage)) {
                $log[] = 'Carpeta vacía public/storage eliminada para permitir symlink.';
            }
        } else {
            $log[] = 'public/storage es un directorio físico con '.count($files).' archivos.';
        }
    }
}

// 3. Crear nuevo enlace simbólico
$success = false;

// Intento A: Enlace relativo (ideal para cPanel / Linux)
if (! is_link($publicStorage) && ! file_exists($publicStorage)) {
    if (@symlink('../storage/app/public', $publicStorage)) {
        $success = true;
        $log[] = '✅ ÉXITO: Enlace simbólico relativo creado: public/storage -> ../storage/app/public';
    }
}

// Intento B: Enlace absoluto
if (! $success && ! is_link($publicStorage) && ! file_exists($publicStorage)) {
    if (@symlink($targetStorage, $publicStorage)) {
        $success = true;
        $log[] = "✅ ÉXITO: Enlace simbólico absoluto creado hacia: {$targetStorage}";
    }
}

// 4. Muestreo de archivos de audio existentes
$audios = [];
if (file_exists($targetStorage.'/audio')) {
    $audios = array_values(array_diff(scandir($targetStorage.'/audio') ?: [], ['.', '..']));
}

echo json_encode([
    'success' => $success || is_link($publicStorage),
    'is_symlink' => is_link($publicStorage),
    'symlink_target' => is_link($publicStorage) ? @readlink($publicStorage) : null,
    'public_storage_exists' => file_exists($publicStorage),
    'audios_in_storage' => $audios,
    'log' => $log,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
