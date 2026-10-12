<?php

namespace App\Services;

use App\Events\SystemVerificationCodeReceived;
use App\Models\Conversation;
use App\Models\SystemVerificationCode;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookService
{
    /**
     * Patrones Regex para detectar códigos OTP de confirmación (5 a 8 dígitos)
     * enviados por Meta para vinculación de Facebook Pages, Instagram o WhatsApp.
     */
    protected const OTP_PATTERNS = [
        // "Tu código de confirmación de Facebook es 582193" o "Código: 582193"
        '/(?:c[oó]digo|code|clave)\s*(?:de)?\s*(?:confirmaci[oó]n|verificaci[oó]n|seguridad)?(?:\s+de\s+(?:facebook|meta))?\s*(?:es|:)?\s*[:\s]*\b(\d{5,8})\b/iu',
        // "582193 es tu código de confirmación de Facebook"
        '/\b(\d{5,8})\b\s*(?:es\s+(?:tu|su)\s+c[oó]digo|is\s+your\s+(?:verification|confirmation)\s+code)/iu',
        // Meta Linking: contiene palabras clave de Meta y un código de 5 a 8 dígitos
        '/(?=.*\b(?:facebook|meta)\b)(?=.*\b(?:c[oó]digo|code|verificaci[oó]n|confirmaci[oó]n)\b).*?\b(\d{5,8})\b/ius',
    ];

    /**
     * Determina si el texto corresponde a un código de verificación/OTP
     * y opcionalmente extrae el código numérico.
     */
    public function isVerificationCode(string $body, ?string &$extractedCode = null): bool
    {
        $trimmed = trim($body);
        if (empty($trimmed)) {
            return false;
        }

        $normalized = mb_strtolower($trimmed);

        // Descartar falsos positivos habituales en agencias de viajes / mensajería
        if (
            str_contains($normalized, 'postal')
            || str_contains($normalized, 'reserva')
            || str_contains($normalized, 'ticket')
            || str_contains($normalized, 'boleto')
            || str_contains($normalized, 'vuelo')
        ) {
            return false;
        }

        foreach (self::OTP_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $extractedCode = $matches[1] ?? null;

                return true;
            }
        }

        // Fallback: Si contiene referencias explícitas a Facebook, Meta o verificación de seguridad
        if (
            str_contains($normalized, 'facebook')
            || str_contains($normalized, 'meta')
            || str_contains($normalized, 'confirmaci')
            || str_contains($normalized, 'verificaci')
            || (
                (str_contains($normalized, 'codigo') || str_contains($normalized, 'código'))
                && (str_contains($normalized, 'seguridad') || str_contains($normalized, 'otp') || str_contains($normalized, 'clave'))
            )
        ) {
            if (preg_match('/\b(\d{5,8})\b/', $trimmed, $matches)) {
                $extractedCode = $matches[1];

                return true;
            }
        }

        return false;
    }

    /**
     * Intercepta mensajes que contengan códigos de verificación del sistema (Meta/Facebook),
     * los audita en base de datos y despacha el evento WebSocket para el administrador.
     */
    public function detectAndCaptureVerificationCode(
        string $body,
        string $senderPhone,
        array $metadata = []
    ): ?SystemVerificationCode {
        $extractedCode = null;
        if (! $this->isVerificationCode($body, $extractedCode)) {
            return null;
        }

        $trimmed = trim($body);

        // Determinar proveedor del servicio
        $service = 'facebook_page_linking';
        if (str_contains(mb_strtolower($trimmed), 'instagram')) {
            $service = 'instagram_linking';
        } elseif (str_contains(mb_strtolower($trimmed), 'whatsapp')) {
            $service = 'whatsapp_business_verify';
        }

        // Almacenar en base de datos para auditoría y trazabilidad
        $verification = SystemVerificationCode::create([
            'code' => $extractedCode,
            'service' => $service,
            'sender_phone' => $senderPhone,
            'full_message' => $body,
            'metadata' => $metadata,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);

        Log::info("WhatsAppWebhookService: Código de verificación interceptado [{$extractedCode}] de {$senderPhone} para {$service}");

        // Emitir evento WebSocket en tiempo real hacia el panel de administración
        BroadcastHelper::safeBroadcast(new SystemVerificationCodeReceived($verification));

        return $verification;
    }

    /**
     * Extrae de forma segura el objeto 'referral' inyectado por Meta
     * (Facebook Ads / Botones nativos de Facebook Pages) y calcula la ventana de 72h gratuitas.
     */
    public function extractAndApplyReferral(Conversation $conversation, array $messageObj): ?array
    {
        $referral = isset($messageObj['referral']) && is_array($messageObj['referral'])
            ? $messageObj['referral']
            : (isset($messageObj['source_type']) || isset($messageObj['headline']) ? $messageObj : null);

        if (empty($referral) || ! is_array($referral)) {
            return null;
        }

        $sourceType = $referral['source_type'] ?? 'ad';
        $sourceId = $referral['source_id'] ?? null;
        $sourceUrl = $referral['source_url'] ?? null;
        $headline = $referral['headline'] ?? null;
        $body = $referral['body'] ?? null;

        // Meta otorga exactamente 72 horas gratuitas sin costo de sesión desde el click del anuncio
        $freeWindowExpiresAt = now()->addHours(72);

        $conversation->update([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_url' => $sourceUrl,
            'headline' => $headline,
            'body' => $body,
            'referral_data' => $referral,
            'free_window_expires_at' => $freeWindowExpiresAt,
        ]);

        Log::info("WhatsAppWebhookService: Referral de Facebook Ads detectado en conversación #{$conversation->id}. Tipo: {$sourceType}. Ventana 72h hasta: {$freeWindowExpiresAt}");

        return $referral;
    }
}
