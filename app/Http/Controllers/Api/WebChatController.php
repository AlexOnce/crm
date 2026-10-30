<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\BroadcastHelper;
use App\Services\WebChatBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebChatController extends Controller
{
    public function __construct(
        protected WebChatBotService $engine
    ) {}

    /**
     * Obtiene la conversación activa a partir del token del visitante.
     */
    protected function getConversationByToken(Request $request): ?Conversation
    {
        $token = $request->header('X-Visitor-Token') ?? $request->input('visitor_token');
        if (! $token || ! is_string($token) || strlen($token) < 20 || strlen($token) > 64) {
            return null;
        }

        return Conversation::where('channel', 'web')
            ->where('visitor_token', $token)
            ->first();
    }

    /**
     * Inicializar o reanudar sesión de Chat Web para un visitante.
     */
    public function init(Request $request): JsonResponse
    {
        $conversation = $this->getConversationByToken($request);

        if (! $conversation) {
            $token = Str::random(48);
            $ip = $request->ip();
            $ua = substr((string) $request->userAgent(), 0, 500);
            $name = $request->filled('name') ? strip_tags(trim((string) $request->input('name'))) : null;
            $phone = $request->filled('phone') ? preg_replace('/[^\d+]/', '', (string) $request->input('phone')) : null;

            // Generar identificador telefónico interno único para la sesión web
            $dummyPhone = 'web_'.substr(md5($token.microtime(true)), 0, 16);

            $conversation = Conversation::create([
                'phone' => $dummyPhone,
                'channel' => 'web',
                'visitor_token' => $token,
                'visitor_ip' => $ip,
                'visitor_user_agent' => $ua,
                'contact_name' => $name,
                'status' => 'bot_active',
                'current_state' => 'MENU_PRINCIPAL',
                'last_interaction_at' => now(),
                'session_data' => [
                    'visitor_token' => $token,
                    'contact_phone' => $phone,
                    'passenger_name' => $name,
                ],
            ]);

            // Enviar saludo inicial y menú automático del bot directamente con botones interactivos
            $this->engine->replyMenuPrincipal($conversation);
        } else {
            // Si ya existe la conversación pero se envían datos actualizados de identificación
            $updates = [];
            $session = $conversation->session_data ?? [];

            if ($request->filled('name')) {
                $cleanName = strip_tags(trim((string) $request->input('name')));
                if (! empty($cleanName) && $cleanName !== $conversation->contact_name) {
                    $updates['contact_name'] = $cleanName;
                    $session['passenger_name'] = $cleanName;
                }
            }
            if ($request->filled('phone')) {
                $cleanPhone = preg_replace('/[^\d+]/', '', (string) $request->input('phone'));
                if (! empty($cleanPhone)) {
                    $session['contact_phone'] = $cleanPhone;
                }
            }
            if (! empty($updates) || ! empty($session)) {
                $updates['session_data'] = $session;
                $conversation->update($updates);
            }
        }

        $messages = $conversation->messages()
            ->with('agent:id,name')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        return response()->json([
            'success' => true,
            'token' => $conversation->visitor_token,
            'conversation' => [
                'status' => $conversation->status,
                'contact_name' => $conversation->contact_name,
                'current_state' => $conversation->current_state,
                'assigned_agent' => $conversation->assignedUser?->name,
                'last_interaction_at' => $conversation->last_interaction_at,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Enviar mensaje desde la web hacia el CRM / Chatbot.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $conversation = $this->getConversationByToken($request);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión de chat inválida o expirada.',
            ], 401);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
            'button_id' => 'nullable|string|max:50',
            'button_title' => 'nullable|string|max:100',
            'file' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);

        $rawText = $validated['message'] ?? '';
        $buttonId = $validated['button_id'] ?? null;
        $buttonTitle = $validated['button_title'] ?? null;

        // Sanitización estricta anti-XSS
        $cleanText = trim(strip_tags($rawText));

        if (empty($cleanText) && empty($buttonId) && ! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'El mensaje no puede estar vacío.',
            ], 422);
        }

        $mediaUrl = null;
        $mediaType = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = 'web_'.time().'_'.Str::random(10).'.'.$ext;
            $path = $file->storeAs('uploads/webchat', $filename, 'public');
            $mediaUrl = asset("storage/{$path}");
            $mediaType = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'image' : 'document';
            if (empty($cleanText)) {
                $cleanText = $mediaType === 'image' ? '📷 Imagen adjunta' : '📎 Documento adjunto';
            }
        }

        // Si la conversación está en bot_active, procesar a través del motor
        if ($conversation->status === 'bot_active') {
            $this->engine->processIncomingMessage(
                conversation: $conversation,
                text: $cleanText ?: ($buttonTitle ?: (string) $buttonId),
                mediaUrl: $mediaUrl,
                mediaType: $mediaType,
                buttonId: $buttonId,
                buttonTitle: $buttonTitle
            );
        } else {
            // Conversación atendida por asesor humano o en espera (waiting_agent / human_live)
            // Verificar si el usuario solicita reiniciar al menú del bot
            $norm = Str::lower($cleanText);
            if (in_array($norm, ['menu', 'inicio', 'volver', 'bot']) || $buttonId === 'menu_volver') {
                $conversation->update([
                    'status' => 'bot_active',
                    'current_state' => 'MENU_PRINCIPAL',
                    'last_interaction_at' => now(),
                ]);
                BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'returned_to_bot'));
                $this->engine->processIncomingMessage(
                    conversation: $conversation,
                    text: 'menu',
                    buttonId: 'menu_volver',
                    buttonTitle: 'Menú Principal'
                );
            } else {
                $userMsg = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender' => 'user',
                    'message' => $cleanText ?: ($buttonTitle ?? 'Mensaje'),
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                ]);

                $conversation->update(['last_interaction_at' => now()]);

                // Notificar en vivo al panel del agente en CRM
                BroadcastHelper::safeBroadcast(new ChatMessageSent($userMsg, $conversation->id, $conversation));
                BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'new_message'));
            }
        }

        $freshConv = $conversation->fresh(['assignedUser']);
        $newMessages = $freshConv->messages()
            ->with('agent:id,name')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        return response()->json([
            'success' => true,
            'conversation' => [
                'status' => $freshConv->status,
                'contact_name' => $freshConv->contact_name,
                'current_state' => $freshConv->current_state,
                'assigned_agent' => $freshConv->assignedUser?->name,
                'last_interaction_at' => $freshConv->last_interaction_at,
            ],
            'messages' => $newMessages,
        ]);
    }

    /**
     * Obtener mensajes recientes (polling reactivo o sincronización).
     */
    public function getMessages(Request $request): JsonResponse
    {
        $conversation = $this->getConversationByToken($request);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no encontrada.',
            ], 401);
        }

        $afterId = (int) $request->query('after_id', 0);

        $query = $conversation->messages()
            ->with('agent:id,name')
            ->orderBy('id', 'asc');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->get()->map(fn ($m) => $this->formatMessage($m));

        $freshConv = $conversation->fresh(['assignedUser']);

        return response()->json([
            'success' => true,
            'conversation' => [
                'status' => $freshConv->status,
                'contact_name' => $freshConv->contact_name,
                'current_state' => $freshConv->current_state,
                'assigned_agent' => $freshConv->assignedUser?->name,
                'last_interaction_at' => $freshConv->last_interaction_at,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Actualizar datos del pasajero / visitante (Nombre, Teléfono).
     */
    public function updateContact(Request $request): JsonResponse
    {
        $conversation = $this->getConversationByToken($request);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no encontrada.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
        ]);

        $updates = [];
        $session = $conversation->session_data ?? [];

        if (! empty($validated['name'])) {
            $updates['contact_name'] = strip_tags(trim($validated['name']));
            $session['passenger_name'] = $updates['contact_name'];
        }

        if (! empty($validated['phone'])) {
            $cleanPhone = preg_replace('/[^\d+]/', '', $validated['phone']);
            $session['contact_phone'] = $cleanPhone;
        }

        $updates['session_data'] = $session;
        $conversation->update($updates);

        BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation->fresh(), 'contact_updated'));

        return response()->json([
            'success' => true,
            'conversation' => [
                'contact_name' => $conversation->contact_name,
                'phone' => $session['contact_phone'] ?? $conversation->phone,
            ],
        ]);
    }

    /**
     * Formatear el mensaje de forma segura para el frontend público.
     */
    protected function formatMessage(ChatMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'sender' => $msg->sender, // 'user', 'bot', 'agent'
            'message' => $msg->message,
            'media_url' => $msg->media_url,
            'media_type' => $msg->media_type,
            'buttons' => $msg->buttons,
            'agent_name' => $msg->agent?->name,
            'created_at' => $msg->created_at?->toIso8601String(),
        ];
    }
}

