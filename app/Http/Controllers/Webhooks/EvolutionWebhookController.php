<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatbotEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolutionWebhookController extends Controller
{
    public function handle(Request $request, ChatbotEngineService $engine): JsonResponse
    {
        $payload = $request->all();

        // 1. Validar que sea un evento de mensajes entrantes
        $event = $payload['event'] ?? '';
        if ($event !== 'messages.upsert') {
            return response()->json(['status' => 'ignored', 'reason' => 'event_not_upsert'], 200);
        }

        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];

        // 2. Ignorar mensajes enviados por la propia instancia o bot
        if ($key['fromMe'] ?? false) {
            return response()->json(['status' => 'ignored', 'reason' => 'message_from_me'], 200);
        }

        // 3. Extraer el teléfono del remitente (descartando sufijos @s.whatsapp.net o @g.us de grupos)
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        if (str_contains($remoteJid, '@g.us')) {
            // Ignorar grupos por defecto
            return response()->json(['status' => 'ignored', 'reason' => 'group_message'], 200);
        }

        $phone = explode('@', $remoteJid)[0];
        $phone = preg_replace('/[^\d]/', '', $phone);

        if (empty($phone)) {
            return response()->json(['status' => 'error', 'message' => 'Número inválido'], 400);
        }

        // 4. Extraer el texto del mensaje en cualquiera de sus formatos compatibles
        $messageContent = $data['message'] ?? [];
        $text = $messageContent['conversation']
            ?? $messageContent['extendedTextMessage']['text']
            ?? $messageContent['buttonsResponseMessage']['selectedDisplayText']
            ?? $messageContent['listResponseMessage']['title']
            ?? '';

        // Si el usuario envió una imagen o documento (ej: pasaporte o comprobante)
        $mediaUrl = null;
        if (isset($data['mediaUrl']) || isset($messageContent['imageMessage']) || isset($messageContent['documentMessage'])) {
            $mediaUrl = $data['mediaUrl'] ?? null;
            if (empty($text)) {
                $text = '[Documento / Adjunto recibido]';
            }
        }

        $contactName = $data['pushName'] ?? null;

        // 5. Encontrar o crear la conversación en la base de datos
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

        // 6. Enviar a la máquina de estados y CRM
        $engine->processIncomingMessage($conversation, (string) $text);

        return response()->json([
            'status' => 'processed',
            'conversation_id' => $conversation->id,
            'phone' => $phone,
        ], 200);
    }
}
