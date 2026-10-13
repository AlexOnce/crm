<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityAuditCommand extends Command
{
    protected $signature = 'crm:security-audit {--env-file=.env : Archivo .env a auditar}';

    protected $description = 'Ejecuta una auditoría estricta de seguridad y preparación para despliegue en producción (cPanel/HTTPS)';

    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('🛡️  AUDITORÍA DE SEGURIDAD Y DESPLIEGUE - CRM LARAVEL');
        $this->info('====================================================');

        $envFile = $this->option('env-file');
        $issues = 0;

        // 1. Auditoría del archivo de entorno (.env)
        $this->newLine();
        $this->line("<fg=yellow>1. Verificando Configuración de Entorno ({$envFile})...</>");

        if (! File::exists(base_path($envFile))) {
            $this->error("❌ El archivo {$envFile} no existe en la raíz del proyecto.");

            return Command::FAILURE;
        }

        $envContent = File::get(base_path($envFile));
        $envVars = [];
        foreach (explode("\n", $envContent) as $line) {
            $line = trim($line);
            if ($line && ! str_starts_with($line, '#') && str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $envVars[trim($key)] = trim(trim($val), '"\'');
            }
        }

        // APP_DEBUG
        $appDebug = strtolower($envVars['APP_DEBUG'] ?? 'true');
        if ($appDebug === 'true') {
            $this->error('  ❌ CRÍTICO: APP_DEBUG está en true. En producción debe ser false para no exponer stacktraces ni credenciales.');
            $issues++;
        } else {
            $this->info('  ✅ APP_DEBUG está en false.');
        }

        // APP_ENV
        $appEnv = strtolower($envVars['APP_ENV'] ?? 'local');
        if ($appEnv !== 'production') {
            $this->warn("  ⚠️ APP_ENV es '{$appEnv}'. En el servidor de producción debe ser 'production'.");
        } else {
            $this->info('  ✅ APP_ENV está configurado en production.');
        }

        // APP_URL y HTTPS
        $appUrl = $envVars['APP_URL'] ?? '';
        if (! str_starts_with($appUrl, 'https://')) {
            $this->error("  ❌ APP_URL ({$appUrl}) no utiliza HTTPS. En producción debe comenzar con https://");
            $issues++;
        } else {
            $this->info("  ✅ APP_URL utiliza HTTPS: {$appUrl}");
        }

        // SESSION_SECURE_COOKIE
        $secureCookie = strtolower($envVars['SESSION_SECURE_COOKIE'] ?? 'false');
        if ($secureCookie !== 'true') {
            $this->warn('  ⚠️ SESSION_SECURE_COOKIE no está en true. Se recomienda agregar SESSION_SECURE_COOKIE=true para forzar cookies solo por HTTPS.');
            $issues++;
        } else {
            $this->info('  ✅ SESSION_SECURE_COOKIE está activo.');
        }

        // SANCTUM_STATEFUL_DOMAINS
        $sanctumDomains = $envVars['SANCTUM_STATEFUL_DOMAINS'] ?? '';
        if (empty($sanctumDomains) && ! empty($appUrl)) {
            $host = parse_url($appUrl, PHP_URL_HOST);
            $this->warn("  ⚠️ Se recomienda definir SANCTUM_STATEFUL_DOMAINS={$host} para autenticación API/SPA segura.");
        } else {
            $this->info("  ✅ SANCTUM_STATEFUL_DOMAINS configurado: {$sanctumDomains}");
        }

        // 2. Auditoría de Código Fuente (dd, dump, tokens quemados)
        $this->newLine();
        $this->line('<fg=yellow>2. Verificando Código Fuente (Búsqueda de fugas y debug)...</>');

        $sourceFiles = File::allFiles(app_path());
        $debugFunctions = ['dd(', 'dump(', 'var_dump(', 'print_r('];
        $foundDebugs = [];

        foreach ($sourceFiles as $file) {
            if ($file->getFilename() === 'SecurityAuditCommand.php') {
                continue;
            }

            $content = $file->getContents();
            foreach ($debugFunctions as $fn) {
                if (str_contains($content, $fn)) {
                    $foundDebugs[] = [
                        'file' => $file->getRelativePathname(),
                        'match' => $fn,
                    ];
                }
            }
        }

        if (count($foundDebugs) > 0) {
            $this->warn('  ⚠️ Se encontraron '.count($foundDebugs).' llamadas de depuración activas en app/:');
            foreach (array_slice($foundDebugs, 0, 5) as $d) {
                $this->line("     - {$d['file']} contiene {$d['match']}");
            }
            if (count($foundDebugs) > 5) {
                $this->line('     - (... y '.(count($foundDebugs) - 5).' más)');
            }
        } else {
            $this->info('  ✅ No se detectaron funciones de depuración (dd, dump, etc.) en app/.');
        }

        // 3. Verificación de Permisos en Carpetas Críticas
        $this->newLine();
        $this->line('<fg=yellow>3. Verificando Directorios Críticos de Escritura...</>');

        $storagePath = storage_path();
        $cachePath = base_path('bootstrap/cache');

        if (is_writable($storagePath)) {
            $this->info('  ✅ Directorio storage/ tiene permisos de escritura.');
        } else {
            $this->error('  ❌ Directorio storage/ no tiene permisos de escritura.');
            $issues++;
        }

        if (is_writable($cachePath)) {
            $this->info('  ✅ Directorio bootstrap/cache tiene permisos de escritura.');
        } else {
            $this->error('  ❌ Directorio bootstrap/cache no tiene permisos de escritura.');
            $issues++;
        }

        // Resumen
        $this->newLine();
        $this->info('====================================================');
        if ($issues === 0) {
            $this->info('🎉 RESUMEN: Proyecto en estado ÓPTIMO para producción.');

            return Command::SUCCESS;
        } else {
            $this->warn("⚠️  RESUMEN: Se detectaron {$issues} advertencias/observaciones de seguridad.");

            return Command::INVALID;
        }
    }
}
