<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendGoipSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos permitidos para el Job.
     */
    public int $tries = 3;

    /**
     * Tiempo máximo de ejecución del Job en segundos.
     */
    public int $timeout = 60;

    /**
     * Tiempo de espera en segundos antes de reintentar en caso de fallo temporal.
     */
    public int $backoff = 30;

    /**
     * Crea una nueva instancia del Job.
     *
     * @param  string  $phone  Número de destino del pasajero/cliente.
     * @param  string  $message  Mensaje en texto plano a enviar.
     * @param  int|null  $leadId  ID del Lead o registro asociado para trazabilidad (opcional).
     * @param  int|null  $line  Línea/Slot GSM en el GoIP (por defecto 1).
     */
    public function __construct(
        public string $phone,
        public string $message,
        public ?int $leadId = null,
        public ?int $line = null
    ) {
        // Asignar preferentemente a una cola dedicada de SMS
        $this->onQueue('sms');
    }

    /**
     * Ejecuta el envío del SMS a través del Gateway GoIP.
     */
    public function handle(): void
    {
        // 1. Obtener parámetros de configuración del GoIP
        $host = (string) config('services.goip.host', env('GOIP_HOST', '192.168.1.100'));
        $port = (string) config('services.goip.port', env('GOIP_PORT', '80'));
        $username = (string) config('services.goip.user', env('GOIP_USER', 'admin'));
        $password = (string) config('services.goip.password', env('GOIP_PASSWORD', 'admin'));
        $slotLine = $this->line ?? (int) config('services.goip.line', env('GOIP_LINE', 1));
        $httpTimeout = (int) config('services.goip.timeout', env('GOIP_TIMEOUT_SECONDS', 20));
        $delaySeconds = (int) config('services.goip.delay', env('GOIP_DELAY_SECONDS', 15));

        // 2. Sanitizar datos: GSM-7 para el cuerpo y normalización numérica del teléfono
        $cleanMessage = $this->sanitizeGsm7($this->message);
        $cleanPhone = $this->sanitizePhoneNumber($this->phone);

        if (empty($cleanPhone)) {
            Log::warning('SendGoipSmsNotification: Número de teléfono inválido o vacío para SMS.', [
                'original_phone' => $this->phone,
                'lead_id' => $this->leadId,
            ]);

            return;
        }

        if (empty($cleanMessage)) {
            Log::warning('SendGoipSmsNotification: Contenido del mensaje vacío tras la sanitización.', [
                'phone' => $cleanPhone,
                'lead_id' => $this->leadId,
            ]);

            return;
        }

        // 3. Estructurar la URL del API HTTP nativo de GoIP
        $endpoint = "http://{$host}:{$port}/default/en_US/send.html";

        try {
            Log::info("SendGoipSmsNotification: Despachando SMS a {$cleanPhone} vía GoIP (Línea {$slotLine})", [
                'phone' => $cleanPhone,
                'chars_count' => mb_strlen($cleanMessage),
                'lead_id' => $this->leadId,
            ]);

            // Petición HTTP GET nativa de Laravel
            $response = Http::timeout($httpTimeout)
                ->get($endpoint, [
                    'u' => $username,
                    'p' => $password,
                    'l' => $slotLine,
                    'n' => $cleanPhone,
                    'm' => $cleanMessage,
                ]);

            $statusCode = $response->status();
            $responseBody = trim($response->body());

            // 4. Validar respuesta HTTP y respuestas textuales de error nativas del GoIP
            if (! $response->successful() || $this->hasGoipError($responseBody)) {
                Log::error('SendGoipSmsNotification: GoIP devolvió un error o respuesta inesperada.', [
                    'phone' => $cleanPhone,
                    'http_status' => $statusCode,
                    'response_body' => $responseBody,
                    'lead_id' => $this->leadId,
                ]);

                // Forzar reintento si aún quedan intentos disponibles
                throw new \RuntimeException("Fallo GoIP (HTTP {$statusCode}): {$responseBody}");
            }

            // Extraer ID de despacho generado por el GoIP (ej: "Sending,L1 Send SMS to:74844348; ID:6ace9b89")
            $dispatchId = null;
            if (preg_match('/ID:([a-zA-Z0-9]+)/', $responseBody, $matches)) {
                $dispatchId = $matches[1];
            }

            Log::info("SendGoipSmsNotification: SMS enviado con éxito a {$cleanPhone}", [
                'goip_id' => $dispatchId,
                'response_summary' => substr(strip_tags($responseBody), 0, 100),
                'lead_id' => $this->leadId,
            ]);
        } catch (\Throwable $e) {
            Log::error('SendGoipSmsNotification: Excepción al comunicarse con el hardware GoIP: '.$e->getMessage(), [
                'phone' => $cleanPhone,
                'lead_id' => $this->leadId,
                'attempt' => $this->attempts(),
            ]);

            // Permitir que el sistema de colas maneje los reintentos o marque como fallido
            throw $e;
        } finally {
            // 5. Control Anti-Spam / Throttling: Pausa obligatoria entre envíos para la operadora Entel
            // Garantiza que el canal GSM quede liberado y previene bloqueo del chip por ráfagas
            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }
        }
    }

    /**
     * Limpia y optimiza el texto para el estándar GSM-7 / ASCII.
     * Reemplaza tildes, ñ, caracteres especiales y símbolos para evitar
     * que el SMS conmute a UCS-2 (ahorro de saldo de 160 a 70 caracteres por SMS).
     */
    private function sanitizeGsm7(string $text): string
    {
        // 1. Reemplazo de vocales con tildes y diéresis
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
        ];
        $text = strtr($text, $accents);

        // 2. Reemplazo de la letra 'ñ' y 'Ñ'
        $text = str_replace(['ñ', 'Ñ'], ['n', 'N'], $text);

        // 3. Símbolos especiales, puntuación regional y caracteres de grado
        $symbols = [
            '°' => ' ',
            'º' => ' ',
            'ª' => ' ',
            '@' => ' at ',
            '¿' => '',
            '¡' => '',
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
            '«' => '"',
            '»' => '"',
            '–' => '-',
            '—' => '-',
            '…' => '...',
        ];
        $text = strtr($text, $symbols);

        // 4. Eliminar emojis y cualquier carácter fuera del rango estándar ASCII imprimible (0x20 a 0x7E) y salto de línea
        $text = preg_replace('/[^\x20-\x7E\r\n]/u', '', $text) ?? $text;

        // 5. Normalizar saltos de línea y espacios duplicados
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Sanitiza y normaliza el número de teléfono para la red Entel y el Gateway GoIP.
     */
    private function sanitizePhoneNumber(string $phone): string
    {
        // Conservar solo dígitos
        $digits = preg_replace('/\D/', '', $phone);

        // Si el número incluye prefijo de Bolivia (591) seguido de 8 dígitos (ej: 59171234567)
        // GoIP con SIM local de Entel generalmente marca números móviles directos de 8 dígitos (7XXXXXXX o 6XXXXXXX)
        // pero se adapta si se requiere formato local o internacional según el dial plan configurado.
        if (str_starts_with($digits, '591') && strlen($digits) === 11) {
            // Puedes retornar los 8 dígitos locales si el Dial Plan de GoIP no usa 591:
            // return substr($digits, 3);
            return $digits;
        }

        return $digits;
    }

    /**
     * Detecta si la respuesta HTML o textual del GoIP contiene errores comunes del firmware.
     */
    private function hasGoipError(string $body): bool
    {
        $lower = strtolower(trim($body));

        // Si empieza directamente con "error" (ej: "ERROR,user or password error")
        if (str_starts_with($lower, 'error')) {
            return true;
        }

        $errorKeywords = [
            'password error',
            'user or password',
            'account error',
            'login error',
            'error: line',
            'not logged in',
            'channel not ready',
            'gsm busy',
            'remain 0',
        ];

        foreach ($errorKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
