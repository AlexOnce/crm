<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatbotEngineService;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Verificación del Webhook requerida por Meta for Developers Console (GET)
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $expectedKey = (string) config('services.meta.verify_token', 'crm_airline_verify_token_2026');

        if ($mode === 'subscribe' && $token && hash_equals($expectedKey, (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Token de verificación inválido.', 403);
    }

    /**
     * Recepción de eventos y mensajes en tiempo real desde Meta Cloud API (POST)
     */
    public function handle(Request $request, ChatbotEngineService $engine, MetaWhatsAppService $whatsapp): Response
    {
        // 0. Verificación Criptográfica de la Firma (X-Hub-Signature-256) si app_secret está configurado
        $appSecret = (string) config('services.meta.app_secret');
        if (! empty($appSecret)) {
            $signatureHeader = $request->header('X-Hub-Signature-256');
            if (! $signatureHeader || ! str_starts_with($signatureHeader, 'sha256=')) {
                return response('Firma de webhook ausente o inválida.', 401);
            }

            $rawContent = $request->getContent();
            $expectedSignature = 'sha256='.hash_hmac('sha256', $rawContent, $appSecret);

            if (! hash_equals($expectedSignature, $signatureHeader)) {
                return response('Firma criptográfica inválida.', 401);
            }
        }

        $payload = $request->all();

        // 1. Extraer datos del cambio
        $changeValue = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (! $changeValue || empty($changeValue['messages'][0])) {
            // Confirmaciones de entrega (statuses: sent, delivered, read) se ignoran pero se devuelve 200
            return response('EVENT_RECEIVED', 200);
        }

        // Filtrar para responder ÚNICAMENTE a la línea oficial activa en .env
        $incomingPhoneNumberId = (string) ($changeValue['metadata']['phone_number_id'] ?? '');
        $configuredPhoneNumberId = (string) config('services.meta.phone_number_id');

        if (! empty($configuredPhoneNumberId) && ! empty($incomingPhoneNumberId) && $incomingPhoneNumberId !== $configuredPhoneNumberId) {
            if (config('services.meta.filter_secondary_numbers', false) || ! app()->environment('testing')) {
                Log::info("MetaWebhookController: Ignorando mensaje recibido en número secundario ({$incomingPhoneNumberId}) porque la línea activa es ({$configuredPhoneNumberId})");

                return response('EVENT_RECEIVED', 200);
            }
        }

        if (! empty($incomingPhoneNumberId)) {
            $whatsapp->setPhoneNumberId($incomingPhoneNumberId);
        }

        $messageObj = $changeValue['messages'][0];
        $wamId = (string) ($messageObj['id'] ?? '');

        // 1.1 Deduplicación e Idempotencia (Meta Cloud API Webhook Retries)
        // Si Meta reintenta el mismo mensaje (por timeout de red o reintento automático), ignorar y responder 200 OK inmediatamente
        if (! empty($wamId)) {
            $dedupKey = "meta_msg_dedup_{$wamId}";
            if (Cache::has($dedupKey)) {
                Log::info("MetaWebhookController: Mensaje duplicado/reintento de Meta ignorado ({$wamId})");

                return response('EVENT_RECEIVED', 200);
            }
            Cache::put($dedupKey, true, now()->addMinutes(15));
        }

        $phone = preg_replace('/[^\d]/', '', (string) ($messageObj['from'] ?? ''));

        if (empty($phone)) {
            return response('EVENT_RECEIVED', 200);
        }

        // 2. Extraer texto y multimedia según el tipo de mensaje recibido
        $text = '';
        $mediaUrl = null;
        $mediaType = null;
        $flowData = null;
        $buttonId = null;
        $buttonTitle = null;
        $type = $messageObj['type'] ?? 'text';

        switch ($type) {
            case 'text':
                $text = $messageObj['text']['body'] ?? '';
                break;
            case 'audio':
                $mediaType = 'audio';
                $text = '🎤 Nota de voz';
                $mediaId = $messageObj['audio']['id'] ?? null;
                if ($mediaId) {
                    $mediaUrl = $whatsapp->downloadMedia($mediaId, 'audio');
                }
                break;
            case 'image':
                $mediaType = 'image';
                $text = $messageObj['image']['caption'] ?? '📷 Imagen';
                $mediaId = $messageObj['image']['id'] ?? null;
                if ($mediaId) {
                    $mediaUrl = $whatsapp->downloadMedia($mediaId, 'images');
                }
                break;
            case 'document':
                $mediaType = 'document';
                $text = $messageObj['document']['caption'] ?? $messageObj['document']['filename'] ?? '📎 Documento';
                $mediaId = $messageObj['document']['id'] ?? null;
                if ($mediaId) {
                    $mediaUrl = $whatsapp->downloadMedia($mediaId, 'documents');
                }
                break;
            case 'button':
                $text = $messageObj['button']['text'] ?? '';
                $buttonTitle = $text;
                $buttonId = $messageObj['button']['payload'] ?? null;
                break;
            case 'interactive':
                $interactive = $messageObj['interactive'] ?? [];
                $interactiveType = $interactive['type'] ?? '';

                if ($interactiveType === 'nfm_reply') {
                    $nfmReply = $interactive['nfm_reply'] ?? [];
                    $rawJson = $nfmReply['response_json'] ?? '{}';
                    $flowData = json_decode($rawJson, true) ?? [];

                    if (! empty($flowData['origin']) && ! empty($flowData['destination'])) {
                        $orig = $flowData['origin'];
                        $dest = $flowData['destination'];
                        $date = $flowData['flight_date'] ?? 'N/A';
                        $adults = isset($flowData['adults']) ? (int) $flowData['adults'] : null;
                        $children = isset($flowData['children']) ? (int) $flowData['children'] : 0;
                        $babies = isset($flowData['babies']) ? (int) $flowData['babies'] : 0;

                        if ($adults !== null) {
                            $totalPax = $adults + $children + $babies;
                            $paxDetails = [];
                            $paxDetails[] = $adults === 1 ? '1 Adulto' : "{$adults} Adultos";
                            if ($children > 0) {
                                $paxDetails[] = $children === 1 ? '1 Niño' : "{$children} Niños";
                            }
                            if ($babies > 0) {
                                $paxDetails[] = $babies === 1 ? '1 Bebé' : "{$babies} Bebés";
                            }
                            $pax = "{$totalPax} (".implode(', ', $paxDetails).')';
                        } else {
                            $pax = $flowData['passengers'] ?? $flowData['passengers_count'] ?? $flowData['cantidad_pasajeros'] ?? $flowData['pax'] ?? '1';
                        }

                        $text = "✈️ *BÚSQUEDA DE ITINERARIO (FORMULARIO)*\n"
                              ."🛫 *Origen:* {$orig}\n"
                              ."🛬 *Destino:* {$dest}\n"
                              ."📅 *Fecha:* {$date}\n"
                              ."👥 *Pasajeros:* {$pax}";
                    } else {
                        $firstName = trim($flowData['full_name'] ?? '');
                        $lastName = trim($flowData['full_ape'] ?? '');
                        $fullName = trim("{$firstName} {$lastName}") ?: ($flowData['full_name'] ?? 'Pasajero');
                        $docType = $flowData['doc_type'] ?? 'DOC';
                        $docNumber = $flowData['doc_number'] ?? '';
                        $docExp = ! empty($flowData['doc_exp']) ? " ({$flowData['doc_exp']})" : '';
                        $flowPhone = $flowData['doc_telefonico'] ?? $flowData['phone'] ?? null;
                        $email = $flowData['email'] ?? null;
                        $birthDate = $flowData['birth_date'] ?? null;
                        $flowPax = $flowData['passengers'] ?? $flowData['passengers_count'] ?? $flowData['cantidad_pasajeros'] ?? $flowData['pax'] ?? null;
                        $category = match ($flowData['passenger_category'] ?? '') {
                            'ADULT' => 'Adulto (+12 años)',
                            'CHILD' => 'Niño / Menor (2 a 11 años)',
                            'INFANT' => 'Infante / Bebé (< 2 años)',
                            default => $flowData['passenger_category'] ?? null
                        };
                        $luggage = match ($flowData['luggage_option'] ?? '') {
                            'STANDARD' => 'Equipaje Estándar (5kg mano + 20kg bodega)',
                            'EXTRA_BAG' => 'Equipaje adicional en bodega',
                            'PETC' => 'Mascota en cabina (PETC)',
                            'WHEELCHAIR' => 'Asistencia / Silla de ruedas',
                            default => $flowData['luggage_option'] ?? null
                        };

                        $text = "📋 *DATOS DE PASAJERO REGISTRADOS (FORMULARIO)*\n"
                              ."👤 *Pasajero:* {$fullName}\n"
                              ."📄 *Documento:* {$docType} {$docNumber}{$docExp}\n";

                        if (! empty($flowPax)) {
                            $text .= "👥 *Pasajeros:* {$flowPax}\n";
                        }
                        if (! empty($flowPhone)) {
                            $text .= "📱 *Teléfono:* {$flowPhone}\n";
                        }
                        if (! empty($email)) {
                            $text .= "✉️ *Email:* {$email}\n";
                        }
                        if (! empty($birthDate)) {
                            $text .= "🎂 *F. Nacimiento:* {$birthDate}\n";
                        }
                        $invoiceData = $flowData['invoice_data'] ?? null;
                        if (! empty($invoiceData)) {
                            $text .= "🧾 *Factura:* {$invoiceData}\n";
                        }
                        if (! empty($category)) {
                            $text .= "✈️ *Categoría:* {$category}\n";
                        }
                        if (! empty($luggage)) {
                            $text .= "🧳 *Equipaje/Req:* {$luggage}\n";
                        }

                        $comps = [];
                        for ($i = 1; $i <= 3; $i++) {
                            if (! empty($flowData["comp{$i}_name"])) {
                                $cDoc = ! empty($flowData["comp{$i}_doc"]) ? " ({$flowData["comp{$i}_doc"]})" : '';
                                $comps[] = ($i + 1).". {$flowData["comp{$i}_name"]}{$cDoc}";
                            }
                        }
                        if (! empty($comps)) {
                            $text .= "\n👥 *Acompañantes en Formulario:*\n" . implode("\n", $comps) . "\n";
                        }

                        $termsAccepted = ! empty($flowData['terms_accepted']) || ! empty($flowData['privacy_terms']);
                        $privacyAccepted = ! empty($flowData['privacy_accepted']) || ! empty($flowData['privacy_terms']);
                        if ($termsAccepted && $privacyAccepted) {
                            $text .= "\n🔒 *Términos y Privacidad:* Aceptados ✅";
                        } elseif ($termsAccepted) {
                            $text .= "\n🔒 *Términos:* Aceptados ✅";
                        } elseif ($privacyAccepted) {
                            $text .= "\n🔒 *Privacidad:* Aceptada ✅";
                        }
                    }
                } else {
                    $btnId = $interactive['button_reply']['id'] ?? $interactive['list_reply']['id'] ?? '';
                    $btnTitle = $interactive['button_reply']['title'] ?? $interactive['list_reply']['title'] ?? '';
                    $buttonId = ! empty($btnId) ? $btnId : null;
                    $buttonTitle = ! empty($btnTitle) ? $btnTitle : null;
                    $text = $buttonTitle ?: ($buttonId ?: '');
                }
                break;
            default:
                $text = $messageObj['text']['body'] ?? '';
                break;
        }

        // 3. Extraer nombre de perfil de WhatsApp
        $contactName = $changeValue['contacts'][0]['profile']['name'] ?? null;

        // 4. Obtener o crear conversación en base de datos
        $conversation = Conversation::firstOrCreate(
            ['phone' => $phone],
            [
                'contact_name' => $contactName,
                'current_state' => 'MENU_PRINCIPAL',
                'status' => 'bot_active',
                'last_interaction_at' => now(),
            ]
        );

        if ($contactName && empty($conversation->contact_name)) {
            $conversation->update(['contact_name' => $contactName]);
        }

        if (! empty($incomingPhoneNumberId)) {
            $session = $conversation->session_data ?? [];
            if (($session['phone_number_id'] ?? '') !== $incomingPhoneNumberId) {
                $session['phone_number_id'] = $incomingPhoneNumberId;
                $conversation->update(['session_data' => $session]);
            }
        }

        // 5. Procesar por el motor del chatbot y CRM con multimedia y datos de flow
        try {
            $engine->processIncomingMessage(
                $conversation,
                (string) $text,
                $mediaUrl,
                $mediaType,
                $flowData,
                $buttonId,
                $buttonTitle
            );
        } catch (\Throwable $e) {
            Log::error("MetaWebhookController: Error procesando mensaje ({$wamId}): ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
