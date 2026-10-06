<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webpush:vapid {--show : Solo mostrar las llaves sin modificar .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera un nuevo par de llaves criptográficas VAPID para Web Push y las guarda en .env';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generando llaves criptográficas VAPID...');

        // Configurar OPENSSL_CONF en Windows si existe en Laragon/PHP
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $possibleCnf = [
                'C:\\laragon\\bin\\php\\php-8.3.31\\extras\\ssl\\openssl.cnf',
                'C:\\laragon\\bin\\apache\\httpd-2.4.54-win64-VS16\\conf\\openssl.cnf',
            ];
            foreach ($possibleCnf as $cnf) {
                if (file_exists($cnf)) {
                    putenv("OPENSSL_CONF={$cnf}");
                    break;
                }
            }
        }

        try {
            try {
                $keys = VAPID::createVapidKeys();
            } catch (\Throwable $ex) {
                // En Windows, si OpenSSL carece de config EC, recurrir a npx web-push
                $output = shell_exec('npx -y web-push generate-vapid-keys --json 2>&1');
                $json = json_decode($output, true);
                if (isset($json['publicKey'], $json['privateKey'])) {
                    $keys = [
                        'publicKey' => $json['publicKey'],
                        'privateKey' => $json['privateKey'],
                    ];
                } else {
                    throw $ex;
                }
            }

            $publicKey = $keys['publicKey'];
            $privateKey = $keys['privateKey'];

            $this->newLine();
            $this->line("<comment>VAPID_PUBLIC_KEY:</comment>  {$publicKey}");
            $this->line("<comment>VAPID_PRIVATE_KEY:</comment> {$privateKey}");
            $this->line('<comment>VAPID_SUBJECT:</comment>    mailto:soporte@tamep.bo');
            $this->newLine();

            if ($this->option('show')) {
                return self::SUCCESS;
            }

            // Guardar automáticamente en el archivo .env
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);

                $updatedContent = $this->setEnvValue($envContent, 'VAPID_PUBLIC_KEY', $publicKey);
                $updatedContent = $this->setEnvValue($updatedContent, 'VAPID_PRIVATE_KEY', $privateKey);
                $updatedContent = $this->setEnvValue($updatedContent, 'VAPID_SUBJECT', 'mailto:soporte@tamep.bo');
                $updatedContent = $this->setEnvValue($updatedContent, 'VITE_VAPID_PUBLIC_KEY', $publicKey);

                file_put_contents($envPath, $updatedContent);
                $this->info('✓ Llaves VAPID guardadas exitosamente en .env');
            } else {
                $this->warn('No se encontró el archivo .env. Copia las llaves manualmente.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error generando llaves VAPID: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Actualiza o añade una variable en el contenido del archivo .env
     */
    private function setEnvValue(string $envContent, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*/m";
        $entry = "{$key}=\"{$value}\"";

        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, $entry, $envContent);
        }

        return rtrim($envContent)."\n".$entry."\n";
    }
}
