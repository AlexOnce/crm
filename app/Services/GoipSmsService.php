<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoipSmsService
{
    /**
     * Umbral máximo recomendado por Entel para evitar bloqueo preventivo (SMS por hora por línea).
     */
    public const MAX_RECOMMENDED_HOURLY_SMS = 120;

    /**
     * Umbral máximo recomendado por Entel para evitar suspensión diaria (SMS por día por línea).
     */
    public const MAX_RECOMMENDED_DAILY_SMS = 800;

    /**
     * Host o IP del Gateway GoIP.
     */
    protected string $host;

    /**
     * Puerto HTTP del Gateway GoIP.
     */
    protected int $port;

    /**
     * Usuario de autenticación GoIP.
     */
    protected string $username;

    /**
     * Contraseña de autenticación GoIP.
     */
    protected string $password;

    /**
     * Línea / Slot GSM predeterminado.
     */
    protected int $defaultLine;

    /**
     * Timeout en segundos para la conexión HTTP.
     */
    protected int $timeout;

    /**
     * Tiempo base de delay entre envíos (segundos).
     */
    protected int $baseDelay;

    public function __construct()
    {
        $this->host = (string) config('services.goip.host', env('GOIP_HOST', '192.168.1.100'));
        $this->port = (int) config('services.goip.port', env('GOIP_PORT', 80));
        $this->username = (string) config('services.goip.user', env('GOIP_USER', 'admin'));
        $this->password = (string) config('services.goip.password', env('GOIP_PASSWORD', 'admin'));
        $this->defaultLine = (int) config('services.goip.line', env('GOIP_LINE', 1));
        $this->timeout = (int) config('services.goip.timeout', env('GOIP_TIMEOUT_SECONDS', 20));
        $this->baseDelay = (int) config('services.goip.delay', env('GOIP_DELAY_SECONDS', 15));
    }

    /**
     * Retorna las líneas GSM activas configuradas para el GoIP (por defecto [1, 2]).
     *
     * @return array<int>
     */
    public function getActiveLines(): array
    {
        $lines = config('services.goip.lines', [1, 2]);

        return ! empty($lines) ? $lines : [$this->defaultLine];
    }

    /**
     * Retorna la siguiente línea en ciclo alternado (Round-Robin) entre el puerto 1 y 2.
     */
    public function getNextAlternatingLine(): int
    {
        $lines = $this->getActiveLines();
        if (count($lines) <= 1) {
            return (int) ($lines[0] ?? $this->defaultLine);
        }

        $counter = (int) Cache::increment('goip_round_robin_line_counter');
        $index = ($counter - 1) % count($lines);

        return (int) $lines[$index];
    }

    /**
     * Envía un SMS a través del Gateway GoIP de forma directa y síncrona.
     *
     * @param  string  $phone  Número celular de destino.
     * @param  string  $message  Cuerpo del mensaje en texto plano.
     * @param  int|null  $line  Línea/Slot GSM en el GoIP (null usa intercalado automático entre 1 y 2).
     * @param  bool  $applyAntiBanDelay  Si es true, aplica la pausa con jitter anti-baneo tras el envío.
     * @return array{
     *     success: bool,
     *     phone: string,
     *     message_id: ?string,
     *     raw_response: string,
     *     http_status: int,
     *     elapsed_ms: int,
     *     line: int,
     *     segments: int,
     *     chars_count: int,
     *     error: ?string,
     *     anti_ban: array{jitter_delay_applied: int, hourly_sent: int, daily_sent: int, safe_quota: bool}
     * }
     */
    public function sendSms(
        string $phone,
        string $message,
        ?int $line = null,
        bool $applyAntiBanDelay = false,
        bool $isInterleaved = false
    ): array {
        $startTime = microtime(true);
        $slotLine = $line ?? $this->getNextAlternatingLine();

        $cleanPhone = $this->sanitizePhoneNumber($phone);
        $cleanMessage = $this->sanitizeGsm7($message);
        $segmentsData = $this->calculateSegments($cleanMessage);

        if (empty($cleanPhone)) {
            return [
                'success' => false,
                'phone' => $phone,
                'message_id' => null,
                'raw_response' => '',
                'http_status' => 422,
                'elapsed_ms' => 0,
                'line' => $slotLine,
                'segments' => $segmentsData['segments'],
                'chars_count' => $segmentsData['chars_count'],
                'error' => 'Número de teléfono inválido o vacío para la red móvil de Bolivia.',
                'anti_ban' => $this->getAntiBanStats($slotLine, 0),
            ];
        }

        if (empty($cleanMessage)) {
            return [
                'success' => false,
                'phone' => $cleanPhone,
                'message_id' => null,
                'raw_response' => '',
                'http_status' => 422,
                'elapsed_ms' => 0,
                'line' => $slotLine,
                'segments' => 0,
                'chars_count' => 0,
                'error' => 'El contenido del mensaje quedó vacío tras la sanitización GSM-7.',
                'anti_ban' => $this->getAntiBanStats($slotLine, 0),
            ];
        }

        $endpoint = "http://{$this->host}:{$this->port}/default/en_US/send.html";

        try {
            Log::info("GoipSmsService: Enviando SMS a {$cleanPhone} (Línea {$slotLine})", [
                'phone' => $cleanPhone,
                'chars' => $segmentsData['chars_count'],
                'segments' => $segmentsData['segments'],
                'line' => $slotLine,
            ]);

            $response = Http::timeout($this->timeout)
                ->get($endpoint, [
                    'u' => $this->username,
                    'p' => $this->password,
                    'l' => $slotLine,
                    'n' => $cleanPhone,
                    'm' => $cleanMessage,
                ]);

            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $body = trim($response->body());

            if (! $response->successful() || $this->hasGoipError($body)) {
                Log::error("GoipSmsService: Error GoIP al enviar a {$cleanPhone}", [
                    'status' => $statusCode,
                    'response' => $body,
                    'elapsed_ms' => $elapsedMs,
                ]);

                return [
                    'success' => false,
                    'phone' => $cleanPhone,
                    'message_id' => null,
                    'raw_response' => $body,
                    'http_status' => $statusCode,
                    'elapsed_ms' => $elapsedMs,
                    'line' => $slotLine,
                    'segments' => $segmentsData['segments'],
                    'chars_count' => $segmentsData['chars_count'],
                    'error' => "Fallo de respuesta del GoIP: {$body}",
                    'anti_ban' => $this->getAntiBanStats($slotLine, 0),
                ];
            }

            $messageId = null;
            if (preg_match('/ID:([a-zA-Z0-9]+)/', $body, $matches)) {
                $messageId = $matches[1];
            }

            // Registrar envío en contadores anti-ban de la línea
            $this->recordSmsSent($slotLine);

            $jitterDelay = 0;
            if ($applyAntiBanDelay) {
                $jitterDelay = $this->applyAntiBanJitterDelay($slotLine, $isInterleaved);
            }

            return [
                'success' => true,
                'phone' => $cleanPhone,
                'message_id' => $messageId,
                'raw_response' => $body,
                'http_status' => $statusCode,
                'elapsed_ms' => $elapsedMs,
                'line' => $slotLine,
                'segments' => $segmentsData['segments'],
                'chars_count' => $segmentsData['chars_count'],
                'error' => null,
                'anti_ban' => $this->getAntiBanStats($slotLine, $jitterDelay),
            ];
        } catch (\Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);
            Log::error("GoipSmsService: Excepción de conexión con el GoIP: {$e->getMessage()}", [
                'phone' => $cleanPhone,
                'elapsed_ms' => $elapsedMs,
            ]);

            return [
                'success' => false,
                'phone' => $cleanPhone,
                'message_id' => null,
                'raw_response' => '',
                'http_status' => 500,
                'elapsed_ms' => $elapsedMs,
                'line' => $slotLine,
                'segments' => $segmentsData['segments'],
                'chars_count' => $segmentsData['chars_count'],
                'error' => "No se pudo conectar con el Gateway GoIP ({$this->host}:{$this->port}): {$e->getMessage()}",
                'anti_ban' => $this->getAntiBanStats($slotLine, 0),
            ];
        }
    }

    /**
     * Aplica un retardo aleatorio con Jitter entre envíos para evitar detección de patrones robóticos por Entel.
     * Si se usan ambos puertos en intercalado, el retardo entre despachos se reduce a ~7-10s,
     * permitiendo enviar el doble de rápido en total, manteniendo un reposo seguro de 14-20s para cada chip.
     */
    public function applyAntiBanJitterDelay(int $line, bool $isInterleaved = false): int
    {
        $base = $isInterleaved ? max(6, (int) round($this->baseDelay / 2)) : $this->baseDelay;
        $minDelay = max(5, $base - 3);
        $maxDelay = max($minDelay + 3, $base + 3);

        $sleepSeconds = random_int($minDelay, $maxDelay);

        // Cada 12 mensajes consecutivos por la misma línea, añadir una pausa de enfriamiento (cooldown)
        // para dar respiro al buffer GSM del chip y evitar sospecha en la BTS de Entel.
        $consecutiveKey = "goip_consecutive_sent_line_{$line}";
        $consecutive = (int) Cache::get($consecutiveKey, 0) + 1;
        Cache::put($consecutiveKey, $consecutive, now()->addMinutes(10));

        if ($consecutive % 12 === 0) {
            $cooldown = random_int(20, 30);
            Log::info("GoipSmsService: Pausa de enfriamiento (Cooldown) Anti-Baneo Entel de {$cooldown}s activada tras {$consecutive} SMS continuos en Línea {$line}.");
            $sleepSeconds += $cooldown;
        }

        sleep($sleepSeconds);

        return $sleepSeconds;
    }

    /**
     * Comprueba la conectividad con el Gateway GoIP y su respuesta general.
     *
     * @return array{
     *     online: bool,
     *     host: string,
     *     port: int,
     *     line: int,
     *     status_message: string,
     *     latency_ms: int,
     *     compliance: array{
     *         hourly_sent: int,
     *         daily_sent: int,
     *         max_hourly: int,
     *         max_daily: int,
     *         safe_status: string
     *     }
     * }
     */
    public function checkGatewayHealth(?int $line = null): array
    {
        $slotLine = $line ?? $this->defaultLine;
        $start = microtime(true);
        $endpoint = "http://{$this->host}:{$this->port}/default/en_US/tools.html";

        try {
            $response = Http::timeout(5)
                ->withBasicAuth($this->username, $this->password)
                ->get($endpoint);

            $latency = (int) round((microtime(true) - $start) * 1000);
            $stats = $this->getUsageStats($slotLine);

            $safeStatus = 'safe';
            if ($stats['hourly'] >= self::MAX_RECOMMENDED_HOURLY_SMS || $stats['daily'] >= self::MAX_RECOMMENDED_DAILY_SMS) {
                $safeStatus = 'danger';
            } elseif ($stats['hourly'] >= (self::MAX_RECOMMENDED_HOURLY_SMS * 0.75)) {
                $safeStatus = 'warning';
            }

            $activeLines = $this->getActiveLines();
            $linesStats = [];
            foreach ($activeLines as $l) {
                $lStats = $this->getUsageStats((int) $l);
                $linesStats[] = [
                    'line' => (int) $l,
                    'port_name' => 'Puerto '.(int) $l,
                    'hourly_sent' => $lStats['hourly'],
                    'daily_sent' => $lStats['daily'],
                    'max_hourly' => self::MAX_RECOMMENDED_HOURLY_SMS,
                    'max_daily' => self::MAX_RECOMMENDED_DAILY_SMS,
                ];
            }

            return [
                'online' => $response->successful() || $response->status() === 401 || $response->status() === 200,
                'host' => $this->host,
                'port' => $this->port,
                'line' => $slotLine,
                'active_lines' => $activeLines,
                'lines' => $linesStats,
                'interleaving_enabled' => count($activeLines) > 1,
                'status_message' => 'Gateway GoIP alcanzable en red local/VPN.',
                'latency_ms' => $latency,
                'compliance' => [
                    'hourly_sent' => $stats['hourly'],
                    'daily_sent' => $stats['daily'],
                    'max_hourly' => self::MAX_RECOMMENDED_HOURLY_SMS,
                    'max_daily' => self::MAX_RECOMMENDED_DAILY_SMS,
                    'safe_status' => $safeStatus,
                ],
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $stats = $this->getUsageStats($slotLine);
            $activeLines = $this->getActiveLines();
            $linesStats = [];
            foreach ($activeLines as $l) {
                $lStats = $this->getUsageStats((int) $l);
                $linesStats[] = [
                    'line' => (int) $l,
                    'port_name' => 'Puerto '.(int) $l,
                    'hourly_sent' => $lStats['hourly'],
                    'daily_sent' => $lStats['daily'],
                    'max_hourly' => self::MAX_RECOMMENDED_HOURLY_SMS,
                    'max_daily' => self::MAX_RECOMMENDED_DAILY_SMS,
                ];
            }

            return [
                'online' => false,
                'host' => $this->host,
                'port' => $this->port,
                'line' => $slotLine,
                'active_lines' => $activeLines,
                'lines' => $linesStats,
                'interleaving_enabled' => count($activeLines) > 1,
                'status_message' => 'No se pudo comunicar con el GoIP: '.$e->getMessage(),
                'latency_ms' => $latency,
                'compliance' => [
                    'hourly_sent' => $stats['hourly'],
                    'daily_sent' => $stats['daily'],
                    'max_hourly' => self::MAX_RECOMMENDED_HOURLY_SMS,
                    'max_daily' => self::MAX_RECOMMENDED_DAILY_SMS,
                    'safe_status' => 'unknown',
                ],
            ];
        }
    }

    /**
     * Valida el cumplimiento de la normativa boliviana (ATT y Entel) para prevenir clasificaciones de spam.
     *
     * @return array{
     *     compliant: bool,
     *     has_sender_identity: bool,
     *     has_suspicious_links: bool,
     *     has_spam_words: bool,
     *     warnings: array<int, string>,
     *     recommendations: array<int, string>
     * }
     */
    public function validateEntelCompliance(string $message): array
    {
        $warnings = [];
        $recommendations = [];
        $lower = mb_strtolower($message);

        // 1. Identificación institucional obligatoria (ATT Art. 40 / Políticas de operadora)
        $hasSenderIdentity = str_contains($lower, 'tamep')
            || str_contains($lower, 'eptam')
            || str_contains($lower, 'tam ep')
            || str_contains($lower, 'transporte aereo militar');

        if (! $hasSenderIdentity) {
            $warnings[] = 'Falta el identificador institucional del remitente (ej: "TAMep Informa:" o "EPTAM:"). Enviar SMS anónimos o sin marca activa filtros anti-spam de Entel.';
            $recommendations[] = 'Inicie siempre el mensaje con "TAMep Informa:" o "Sr/Sra [APELLIDO]: TAMep informa...".';
        }

        // 2. Enlaces sospechosos o acortadores (Entel bloquea inmediatamente por Smishing)
        $hasSuspiciousLinks = (bool) preg_match('/(bit\.ly|tinyurl\.com|t\.co|goo\.gl|is\.gd|cutt\.ly|ow\.ly)/i', $message);
        if ($hasSuspiciousLinks) {
            $warnings[] = 'El mensaje contiene acortadores de enlaces conocidos. Entel suspende automáticamente líneas que difunden enlaces de acortador por prevención de phishing bancario.';
            $recommendations[] = 'Elimine el enlace acortado o coloque únicamente teléfonos oficiales de atención o el dominio completo oficial.';
        }

        // 3. Palabras de spam comercial / señuelo
        $spamKeywords = [
            'ganaste', 'premio', 'sorteo', 'felicidades ha sido seleccionado', 'deposito urgente',
            'clic aqui para reclamar', 'recarga gratis', 'banco union', 'bcp', 'bisa',
        ];
        $hasSpamWords = false;
        foreach ($spamKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                $hasSpamWords = true;
                $warnings[] = "El mensaje contiene la frase sensible '{$keyword}', asociada frecuentemente a filtros heurísticos de fraude.";
            }
        }

        return [
            'compliant' => empty($warnings),
            'has_sender_identity' => $hasSenderIdentity,
            'has_suspicious_links' => $hasSuspiciousLinks,
            'has_spam_words' => $hasSpamWords,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Sanitiza y codifica el texto en GSM-7 estándar puro de 7 bits.
     * Evita que el mensaje conmute a UCS-2 (ahorra costo y previene ráfagas de fragmentación).
     */
    public function sanitizeGsm7(string $text): string
    {
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
        ];
        $text = strtr($text, $accents);
        $text = str_replace(['ñ', 'Ñ'], ['n', 'N'], $text);

        $symbols = [
            '°' => ' ', 'º' => ' ', 'ª' => ' ', '@' => ' at ',
            '¿' => '', '¡' => '',
            '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
            '«' => '"', '»' => '"', '–' => '-', '—' => '-', '…' => '...',
        ];
        $text = strtr($text, $symbols);

        // Solo caracteres ASCII imprimibles estándar (0x20 a 0x7E) y saltos de línea
        $text = preg_replace('/[^\x20-\x7E\r\n]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Sanitiza y normaliza números celulares de Bolivia (8 dígitos directos o prefijo 591).
     */
    public function sanitizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // Si viene con prefijo internacional de Bolivia (591 + 8 dígitos)
        if (str_starts_with($digits, '591') && strlen($digits) === 11) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Calcula métricas de caracteres y fragmentos (segmentos) GSM-7.
     *
     * @return array{chars_count: int, segments: int, encoding: string}
     */
    public function calculateSegments(string $message): array
    {
        $len = mb_strlen($message);
        if ($len <= 0) {
            return ['chars_count' => 0, 'segments' => 1, 'encoding' => 'GSM-7'];
        }

        // SMS único hasta 160 caracteres
        if ($len <= 160) {
            return ['chars_count' => $len, 'segments' => 1, 'encoding' => 'GSM-7'];
        }

        // SMS concatenado (153 caracteres por segmento por cabecera UDH)
        $segments = (int) ceil($len / 153);

        return ['chars_count' => $len, 'segments' => $segments, 'encoding' => 'GSM-7 Concatenated'];
    }

    /**
     * Registra un SMS despachado en las métricas horarias y diarias de la línea.
     */
    protected function recordSmsSent(int $line): void
    {
        $hourKey = 'goip_sms_hour_'.now()->format('YmdH')."_line_{$line}";
        $dayKey = 'goip_sms_day_'.now()->format('Ymd')."_line_{$line}";

        $hourly = (int) Cache::get($hourKey, 0) + 1;
        Cache::put($hourKey, $hourly, now()->addHours(2));

        $daily = (int) Cache::get($dayKey, 0) + 1;
        Cache::put($dayKey, $daily, now()->addDays(2));
    }

    /**
     * Obtiene el uso de SMS registrado en la última hora y en el día actual para una línea.
     *
     * @return array{hourly: int, daily: int}
     */
    public function getUsageStats(int $line): array
    {
        $hourKey = 'goip_sms_hour_'.now()->format('YmdH')."_line_{$line}";
        $dayKey = 'goip_sms_day_'.now()->format('Ymd')."_line_{$line}";

        return [
            'hourly' => (int) Cache::get($hourKey, 0),
            'daily' => (int) Cache::get($dayKey, 0),
        ];
    }

    /**
     * Estructura información de control anti-baneo para respuestas.
     *
     * @return array{jitter_delay_applied: int, hourly_sent: int, daily_sent: int, safe_quota: bool}
     */
    protected function getAntiBanStats(int $line, int $jitterDelay): array
    {
        $stats = $this->getUsageStats($line);

        return [
            'jitter_delay_applied' => $jitterDelay,
            'hourly_sent' => $stats['hourly'],
            'daily_sent' => $stats['daily'],
            'safe_quota' => ($stats['hourly'] < self::MAX_RECOMMENDED_HOURLY_SMS && $stats['daily'] < self::MAX_RECOMMENDED_DAILY_SMS),
        ];
    }

    /**
     * Detecta si la respuesta textual del GoIP contiene errores del firmware.
     */
    protected function hasGoipError(string $body): bool
    {
        $lower = strtolower(trim($body));

        if (str_starts_with($lower, 'error')) {
            return true;
        }

        $errorKeywords = [
            'password error', 'user or password', 'account error', 'login error',
            'error: line', 'not logged in', 'channel not ready', 'gsm busy', 'remain 0',
        ];

        foreach ($errorKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
