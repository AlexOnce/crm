<?php

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Services\GoipSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        public ?int $line = null,
        public ?int $broadcastRecipientId = null
    ) {
        // Asignar preferentemente a una cola dedicada de SMS
        $this->onQueue('sms');
    }

    /**
     * Ejecuta el envío del SMS a través del Gateway GoIP.
     */
    public function handle(GoipSmsService $goipService): void
    {
        $slotLine = $this->line ?? $goipService->getNextAlternatingLine();

        $recipient = $this->broadcastRecipientId ? BroadcastRecipient::find($this->broadcastRecipientId) : null;
        if ($recipient && ($recipient->status === 'cancelled' || $recipient->campaign?->status === 'cancelled')) {
            Log::info("SendGoipSmsNotification: Despacho cancelado para {$this->phone} (Destinatario o Campaña cancelada).");

            return;
        }

        $isInterleaved = ($recipient?->campaign?->filters['goip_line'] ?? 'both') === 'both';

        try {
            // Envío con retardo dinámico aleatorio (Jitter) para cumplimiento con Entel
            $result = $goipService->sendSms($this->phone, $this->message, $slotLine, true, $isInterleaved);

            if (! $result['success']) {
                throw new \RuntimeException($result['error'] ?? 'Fallo desconocido en Gateway GoIP');
            }

            $dispatchId = $result['message_id'];

            // Actualizar registro de destinatario en campaña masiva si aplica
            if ($this->broadcastRecipientId) {
                $recipient = BroadcastRecipient::find($this->broadcastRecipientId);
                if ($recipient) {
                    $recipient->update([
                        'status' => 'sent',
                        'channel_used' => "sms_l{$slotLine}",
                        'gateway_message_id' => "L{$slotLine}:{$dispatchId}",
                        'sent_at' => now(),
                    ]);
                    $recipient->campaign?->increment('sent_count');
                    $this->checkCampaignCompletion($recipient->campaign);
                }
            }

            Log::info("SendGoipSmsNotification: SMS enviado con éxito a {$this->phone}", [
                'goip_id' => $dispatchId,
                'lead_id' => $this->leadId,
                'broadcast_recipient_id' => $this->broadcastRecipientId,
            ]);
        } catch (\Throwable $e) {
            Log::error('SendGoipSmsNotification: Excepción al comunicarse con el hardware GoIP: '.$e->getMessage(), [
                'phone' => $this->phone,
                'lead_id' => $this->leadId,
                'attempt' => $this->attempts(),
            ]);

            // Si se agotaron los intentos o falló definitivamente, marcar destinatario como fallido
            if ($this->broadcastRecipientId && $this->attempts() >= $this->tries) {
                $recipient = BroadcastRecipient::find($this->broadcastRecipientId);
                if ($recipient) {
                    $recipient->update([
                        'status' => 'failed',
                        'channel_used' => "sms_l{$slotLine}",
                        'error_message' => $e->getMessage(),
                    ]);
                    $recipient->campaign?->increment('failed_count');
                    $this->checkCampaignCompletion($recipient->campaign);
                }
            }

            // Permitir que el sistema de colas maneje los reintentos o marque como fallido
            throw $e;
        }
    }

    /**
     * Verifica si todos los destinatarios de la campaña terminaron para marcarla como completada.
     */
    private function checkCampaignCompletion(?BroadcastCampaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        $pending = $campaign->recipients()->whereIn('status', ['pending', 'processing'])->count();
        if ($pending === 0) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
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
