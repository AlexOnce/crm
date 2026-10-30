<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\BroadcastHelper;
use App\Services\ChatbotEngineService;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CrmChatController extends Controller
{
    /**
     * Lista de conversaciones para la bandeja del agente
     */
    public function conversations(Request $request): JsonResponse
    {
        $status = $request->query('status'); // 'waiting_agent', 'human_live', 'bot_active', 'closed'
        $search = $request->query('search');

        $query = Conversation::with(['latestMessage', 'latestLead', 'assignedUser'])
            ->orderByDesc('last_interaction_at');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $trimmed = trim($search);
            $query->where(function ($q) use ($search, $trimmed) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('session_data->contact_phone', 'like', "%{$search}%");

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
                    $q->orWhereDate('last_interaction_at', $trimmed)
                        ->orWhereDate('created_at', $trimmed);
                } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $trimmed, $m)) {
                    $formattedDate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                    $q->orWhereDate('last_interaction_at', $formattedDate)
                        ->orWhereDate('created_at', $formattedDate);
                }
            });
        }

        $conversations = $query->paginate(30);

        $counts = [
            'waiting_agent' => Conversation::where('status', 'waiting_agent')->count(),
            'human_live' => Conversation::where('status', 'human_live')->count(),
            'bot_active' => Conversation::where('status', 'bot_active')->count(),
        ];

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'counts' => $counts,
        ]);
    }

    /**
     * Obtener el historial completo y detalles de un chat
     */
    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load(['assignedUser', 'latestLead', 'leads.attachments']);

        $messages = $conversation->messages()
            ->with('agent:id,name')
            ->get();

        $leads = $conversation->leads()
            ->with('attachments')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'messages' => $messages,
            'lead' => $conversation->latestLead,
            'leads' => $leads,
        ]);
    }

    /**
     * Asignar la conversación al agente actual
     */
    public function take(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->update([
            'assigned_user_id' => $request->user()->id,
            'status' => 'human_live',
            'last_interaction_at' => now(),
        ]);

        // Sincronizar el lead asociado a la conversación para que pase a "contactado" (En Proceso)
        $conversation->leads()->where('status', 'nuevo')->update(['status' => 'contactado']);

        $freshConv = $conversation->fresh(['assignedUser', 'latestLead']);
        BroadcastHelper::safeBroadcast(new ConversationUpdated($freshConv, 'taken'));

        return response()->json([
            'success' => true,
            'message' => 'Conversación tomada con éxito.',
            'conversation' => $freshConv,
        ]);
    }

    /**
     * Reasignar o transferir la conversación a otro agente
     */
    public function assign(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $targetAgent = User::findOrFail($validated['agent_id']);

        $conversation->update([
            'assigned_user_id' => $targetAgent->id,
            'status' => 'human_live',
            'last_interaction_at' => now(),
        ]);

        // Sincronizar el lead asociado a la conversación para que pase a "contactado" (En Proceso)
        $conversation->leads()->where('status', 'nuevo')->update(['status' => 'contactado']);

        $transferMsg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'agent_id' => $request->user()->id,
            'message' => "↪️ Conversación transferida a {$targetAgent->name}.",
        ]);

        $freshConv = $conversation->fresh(['assignedUser', 'latestLead']);
        BroadcastHelper::safeBroadcast(new ChatMessageSent($transferMsg, $conversation->id, $freshConv));
        BroadcastHelper::safeBroadcast(new ConversationUpdated($freshConv, 'assigned'));

        return response()->json([
            'success' => true,
            'message' => "Conversación transferida a {$targetAgent->name} exitosamente.",
            'conversation' => $freshConv,
        ]);
    }

    /**
     * El agente envía un mensaje en tiempo real al pasajero
     */
    public function replyToCustomer(Request $request, Conversation $conversation, MetaWhatsAppService $whatsapp): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf,mp3,ogg,wav,m4a|max:16384',
        ]);

        if (empty($validated['message']) && ! $request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => 'Debes ingresar un mensaje o adjuntar un archivo (imagen, PDF o audio).',
            ], 422);
        }

        $mediaUrl = null;
        $mediaType = null;
        $localPath = null;
        $messageText = $validated['message'] ?? '';

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = 'media_'.time().'_'.Str::random(6).'.'.$ext;
            $path = $file->storeAs('uploads', $filename, 'public');
            $mediaUrl = asset("storage/{$path}");
            $localPath = Storage::disk('public')->path($path);

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $mediaType = 'image';
                if (empty($messageText)) {
                    $messageText = '📷 Imagen';
                }
            } elseif (in_array($ext, ['mp3', 'ogg', 'wav', 'm4a'])) {
                $mediaType = 'audio';
                if (empty($messageText)) {
                    $messageText = '🎤 Audio';
                }
            } else {
                $mediaType = 'document';
                if (empty($messageText)) {
                    $messageText = '📎 Documento';
                }
            }
        }

        // 1. Guardar mensaje enviado por el agente
        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'agent_id' => $request->user()->id,
            'message' => $messageText,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        // 2. Actualizar estado de la conversación a atendida por humano
        $conversation->update([
            'status' => 'human_live',
            'assigned_user_id' => $request->user()->id,
            'last_interaction_at' => now(),
        ]);

        // Emitir mensaje en vivo vía WebSockets
        BroadcastHelper::safeBroadcast(new ChatMessageSent($message, $conversation->id, $conversation));

        // 3. Enviar a través de Meta WhatsApp Cloud API si es canal WhatsApp
        $sent = true;
        if ($conversation->channel !== 'web') {
            if (! empty($conversation->session_data['phone_number_id'])) {
                $whatsapp->setPhoneNumberId($conversation->session_data['phone_number_id']);
            }

            if ($mediaType === 'image') {
                $sent = $whatsapp->sendImage($conversation->phone, $mediaUrl, $validated['message'] ?? '', $localPath);
            } elseif ($mediaType === 'audio') {
                $sent = $whatsapp->sendAudio($conversation->phone, $mediaUrl, $localPath);
            } elseif ($mediaType === 'document') {
                $sent = $whatsapp->sendDocument($conversation->phone, $mediaUrl, basename($mediaUrl), $validated['message'] ?? '');
            } else {
                $sent = $whatsapp->sendMessage($conversation->phone, $messageText);
            }
        }

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'message' => $message->load('agent:id,name'),
        ]);
    }

    /**
     * Cerrar atención humana y devolver el control al bot
     */
    public function releaseToBot(Conversation $conversation, MetaWhatsAppService $whatsapp): JsonResponse
    {
        $conversation->update([
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
            'session_data' => [],
            'last_interaction_at' => now(),
        ]);

        $farewell = "✅ *Atención Finalizada*\n\n".
                    'Tu consulta ha sido atendida por nuestro ejecutivo de ventas. Si requieres nueva información o deseas realizar otra cotización, escribe *MENU* en cualquier momento.';

        $farewellMsg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'bot',
            'message' => $farewell,
        ]);

        BroadcastHelper::safeBroadcast(new ChatMessageSent($farewellMsg, $conversation->id, $conversation));
        BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'released'));

        if ($conversation->channel !== 'web') {
            $whatsapp->sendMessage($conversation->phone, $farewell);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversación devuelta al bot correctamente.',
        ]);
    }

    /**
     * El agente envía un formulario interactivo WhatsApp Flow al pasajero
     */
    /**
     * El agente envía un formulario interactivo WhatsApp Flow al pasajero
     */
    public function sendFlowToCustomer(Request $request, Conversation $conversation, MetaWhatsAppService $whatsapp): JsonResponse
    {
        $flowType = $request->input('flow_type', 'passenger');

        if ($flowType === 'search' || $flowType === 'flight_search') {
            $flowId = (string) config('services.meta.flow_search_id', '');
            if (empty($flowId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'META_FLOW_SEARCH_ID no está configurado en el archivo .env.',
                ], 422);
            }

            $bodyText = $request->input('body_text')
                ?: "✈️ *CONSULTA DE ITINERARIOS Y VUELOS*\n\nPor favor presiona el botón a continuación 👇 para seleccionar tu ciudad de origen, destino, fecha y pasajeros en nuestro buscador interactivo.";
            $flowCta = $request->input('flow_cta') ?: '👉 Buscar Vuelos';
            $headerText = '✈️ TAMep Línea Aérea';
            $footerText = 'Búsqueda de Vuelos';
            $screenId = 'FLIGHT_SEARCH';
        } elseif ($flowType === 'group' || $flowType === 'passenger_group') {
            $flowId = (string) config('services.meta.flow_passenger_group_id', '');
            if (empty($flowId)) {
                $flowId = (string) config('services.meta.flow_passenger_id', '');
            }
            if (empty($flowId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'META_FLOW_PASSENGER_GROUP_ID no está configurado en el archivo .env.',
                ], 422);
            }

            $bodyText = $request->input('body_text')
                ?: "✈️ *Formulario de Nómina Grupal TAMep*\n\nPor favor presiona el botón a continuación 👇 para registrar los datos del titular y acompañantes de manera segura.";
            $flowCta = $request->input('flow_cta') ?: '👉 Llenar Formulario';
            $headerText = '✈️ TAMep Línea Aérea';
            $footerText = 'Nómina Oficial de Pasajeros';
            $screenId = 'PASSENGER_INFO';
        } else {
            // Detección de cantidad según flow_type explícito (passenger_1 a passenger_5) o según sesión/lead
            if (preg_match('/^passenger_([1-5])$/', $flowType, $matches)) {
                $pax = (int) $matches[1];
            } else {
                $pax = (int) ($conversation->latestLead?->passengers_count
                    ?? $conversation->session_data['passengers']
                    ?? $conversation->session_data['passengers_count']
                    ?? 1);
            }

            $chatbot = app(ChatbotEngineService::class);
            $flowId = $chatbot->getPassengerFlowIdForCount($pax);
            $footerText = $pax > 1 ? "Nómina Oficial ({$pax} Pasajeros)" : 'Formulario Seguro de Pasajero';

            if (empty($flowId)) {
                return response()->json([
                    'success' => false,
                    'message' => "El Flow para {$pax} pasajero(s) no está configurado en el archivo .env (META_FLOW_PASSENGER_{$pax}_ID o META_FLOW_PASSENGER_ID).",
                ], 422);
            }

            $bodyText = $request->input('body_text')
                ?: ($pax > 1
                    ? "✈️ *Formulario de Nómina ({$pax} Pasajeros) - TAMep*\n\nPor favor presiona el botón a continuación 👇 para ingresar los datos del titular y acompañantes de manera segura para la emisión."
                    : "✈️ *Formulario de Emisión de Pasaje - TAMep*\n\nPor favor presiona el botón a continuación 👇 para ingresar tus datos personales de manera segura para la emisión del boleto.");
            $flowCta = $request->input('flow_cta') ?: '👉 Llenar Formulario';
            $headerText = '✈️ TAMep Línea Aérea';
            $screenId = 'PASSENGER_INFO';
        }

        // Enviar WhatsApp Flow
        $sent = $whatsapp->sendFlowMessage(
            phone: $conversation->phone,
            bodyText: $bodyText,
            flowId: $flowId,
            flowCta: $flowCta,
            flowToken: "conv_{$conversation->id}_".time(),
            headerText: $headerText,
            footerText: $footerText,
            mode: 'published',
            screenId: $screenId
        );

        // Guardar mensaje en el historial del chat
        $chatMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'agent_id' => $request->user()?->id,
            'message' => $bodyText."\n\n_[Formulario interactivo de WhatsApp enviado al cliente]_",
        ]);

        $conversation->update(['last_interaction_at' => now()]);

        BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMessage, $conversation->id, $conversation));

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'message' => 'Formulario interactivo de WhatsApp enviado al pasajero.',
            'chat_message' => $chatMessage->load('agent:id,name'),
        ]);
    }
}
