<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Models\BotMessage;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ChatbotEngineService
{
    public function __construct(
        protected MetaWhatsAppService $whatsapp,
        protected FlightMicroservice $flightService
    ) {}

    public function processIncomingMessage(
        Conversation $conversation,
        string $text,
        ?string $mediaUrl = null,
        ?string $mediaType = null,
        ?array $flowData = null,
        ?string $buttonId = null,
        ?string $buttonTitle = null
    ): void {
        $trimmed = trim($text);

        // Si no se pasó explícitamente $buttonId pero el texto coincide con un ID de botón conocido (ej: llamadas directas)
        if (empty($buttonId) && preg_match('/^(flight_\d+|menu_\w+|btn_\w+|date_[\w\-]+|ruta_\d+)$/i', $trimmed)) {
            $buttonId = $trimmed;
        }

        // Construir mensaje visual amigable para guardar en la BD y mostrar al agente
        $savedMessage = $trimmed;
        if (! empty($buttonId)) {
            if (! empty($buttonTitle) && ! preg_match('/^(flight_\d+|menu_\w+|btn_\w+|date_[\w\-]+|ruta_\d+)$/i', $buttonTitle)) {
                $savedMessage = "🔘 {$buttonTitle}";
            } else {
                if (preg_match('/^flight_(\d+)$/i', $buttonId, $fm)) {
                    $flightIdx = (int) $fm[1] - 1;
                    $available = $conversation->session_data['available_flights'] ?? [];
                    if (isset($available[$flightIdx])) {
                        $fl = $available[$flightIdx];
                        $dep = ! empty($fl['departure_time']) ? " ({$fl['departure_time']})" : '';
                        $savedMessage = '🔘 ✈️ Vuelo '.($flightIdx + 1).": {$fl['flight_number']}{$dep}";
                    } else {
                        $savedMessage = '🔘 ✈️ Vuelo #'.($flightIdx + 1).' seleccionado';
                    }
                } elseif ($buttonId === 'menu_rutas') {
                    $savedMessage = '🔘 ✈️ Buscar Vuelos / Rutas';
                } elseif ($buttonId === 'menu_carga') {
                    $savedMessage = '🔘 📦 Carga y Envíos';
                } elseif ($buttonId === 'menu_oficinas') {
                    $savedMessage = '🔘 🏢 Nuestras Oficinas';
                } elseif ($buttonId === 'menu_volver') {
                    $savedMessage = '🔘 🏠 Menú Principal';
                } elseif ($buttonId === 'btn_asesor') {
                    $savedMessage = '🔘 👤 Contactar Agente';
                } elseif ($buttonId === 'ver_itinerario') {
                    $savedMessage = '🔘 📅 Ver Itinerario';
                } elseif ($buttonId === 'btn_cotizar') {
                    $savedMessage = '🔘 💵 Cotizar Pasaje';
                } elseif (preg_match('/^date_([\w\-]+)$/i', $buttonId, $dm)) {
                    $savedMessage = "🔘 📅 Fecha: {$dm[1]}";
                } else {
                    $savedMessage = "🔘 {$buttonId}";
                }
            }
        }

        if (empty($savedMessage)) {
            if ($mediaType === 'audio') {
                $savedMessage = '🎤 Nota de voz';
            } elseif ($mediaType === 'image') {
                $savedMessage = '📷 Imagen';
            } elseif ($mediaType === 'document') {
                $savedMessage = '📎 Documento';
            } else {
                $savedMessage = 'Mensaje';
            }
        }
        $norm = $this->normalizeText($trimmed);

        // Guardar referencia del momento de la interacción previa
        $lastInteraction = $conversation->last_interaction_at ?? $conversation->updated_at;
        $inactivityLimitMinutes = (int) config('crm.bot_inactivity_minutes', 30);
        $diffMinutes = $lastInteraction ? Carbon::parse($lastInteraction)->diffInMinutes(now()) : 999;
        $sessionExpired = ($diffMinutes >= $inactivityLimitMinutes) || ($conversation->status === 'closed');

        // 1. Guardar mensaje entrante del usuario en la base de datos
        $chatMsg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $savedMessage,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        // Verificar si es la primera interacción (el bot nunca ha enviado un mensaje en esta conversación)
        $isFirstInteraction = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender', 'bot')
            ->doesntExist();

        // 2. Si la conversación está en manos de un asesor humano activo (< 30 min sin inactividad):
        if (! $sessionExpired && in_array($conversation->status, ['human_live', 'waiting_agent'])) {
            // Si el cliente pide explícitamente volver al menú del bot
            if (in_array($norm, ['menu', 'inicio', 'volver', 'cancelar', 'bot', 'reiniciar', 'salir']) || $trimmed === 'menu_volver' || $buttonId === 'menu_volver') {
                $session = $conversation->session_data ?? [];
                $cleanSession = ! empty($session['phone_number_id']) ? ['phone_number_id' => $session['phone_number_id']] : [];
                $conversation->update([
                    'status' => 'bot_active',
                    'current_state' => 'MENU_PRINCIPAL',
                    'session_data' => $cleanSession,
                    'last_interaction_at' => now(),
                ]);
                BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'returned_to_bot'));
                BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));
                $this->replyMenuPrincipal($conversation);

                return;
            }

            $conversation->update(['last_interaction_at' => now()]);
            BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));

            try {
                app(WebPushService::class)->notifyNewMessage($conversation, $chatMsg);
            } catch (\Throwable $e) {
                Log::warning('[WebPush] Error al enviar push de nuevo mensaje: '.$e->getMessage());
            }

            return;
        }

        // 3. Procesamiento prioritario de respuesta de formulario interactivo WhatsApp Flow
        if (! empty($flowData)) {
            $conversation->update(['last_interaction_at' => now()]);
            BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));

            if (! empty($flowData['origin']) && ! empty($flowData['destination'])) {
                $this->handleFlightSearchFlowResponse($conversation, $flowData);
            } else {
                $this->handleFlowResponse($conversation, $flowData);
            }

            return;
        }

        // 4. Manejo inteligente si el cliente envía una nota de voz al bot
        if ($mediaType === 'audio') {
            $conversation->update(['last_interaction_at' => now()]);
            BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));

            $this->reply(
                $conversation,
                BotMessage::getText(
                    'nota_voz_recibida',
                    'He recibido tu nota de voz. Para escuchar tu consulta y asistirte de la mejor manera, te estoy comunicando con un asesor de ventas.'
                )
            );
            $this->handoffToAgent($conversation, 'Pasajero envió nota de voz al bot', 'asesor');

            return;
        }

        // 5. Si la sesión expiró por inactividad (> 30 min o conversación cerrada):
        // EL BOT RESPONDE SIEMPRE CON EL MENÚ PRINCIPAL DESARROLLADO
        if ($sessionExpired) {
            $session = $conversation->session_data ?? [];
            $cleanSession = ! empty($session['phone_number_id']) ? ['phone_number_id' => $session['phone_number_id']] : [];
            $conversation->update([
                'status' => 'bot_active',
                'current_state' => 'MENU_PRINCIPAL',
                'session_data' => $cleanSession,
                'last_interaction_at' => now(),
            ]);

            BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'menu_principal_auto'));
            BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));

            $this->replyMenuPrincipal($conversation);

            return;
        }

        $conversation->update(['last_interaction_at' => now()]);
        BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));

        // 6. Comandos de reinicio o saludos iniciales: responden SIEMPRE con el Menú Principal
        $isGreetingOrMenu = in_array($norm, [
            'menu',
            'inicio',
            'hola',
            'cancelar',
            'volver',
            '0',
            'buenas',
            'buenos dias',
            'buenas tardes',
            'buenas noches',
            'buen dia',
            'saludos',
            'info',
            'ayuda',
            'comenzar',
            'empezar',
            'reset',
        ])
            || $trimmed === 'menu_volver'
            || $buttonId === 'menu_volver'
            || str_starts_with($norm, 'hola')
            || str_starts_with($norm, 'buen dia')
            || str_starts_with($norm, 'buenos dias')
            || str_starts_with($norm, 'buenas tardes')
            || str_starts_with($norm, 'buenas noches')
            || str_starts_with($norm, 'buenas');

        if ($isGreetingOrMenu && ($buttonId === 'menu_volver' || empty($buttonId))) {
            $session = $conversation->session_data ?? [];
            $cleanSession = ! empty($session['phone_number_id']) ? ['phone_number_id' => $session['phone_number_id']] : [];
            $conversation->update([
                'status' => 'bot_active',
                'current_state' => 'MENU_PRINCIPAL',
                'session_data' => $cleanSession,
            ]);
            $this->replyMenuPrincipal($conversation);

            return;
        }

        // 7. Detección global de solicitud de Asesor Humano o botón de asesor
        if ($this->isDirectAgentRequest($norm) || $trimmed === 'btn_asesor' || $buttonId === 'btn_asesor') {
            $this->handoffToAgent(
                $conversation,
                'Solicitud directa de hablar con un asesor',
                'asesor'
            );

            return;
        }

        // 8. Detección global de solicitud o botón de ver itinerario
        if ($trimmed === 'ver_itinerario' || $buttonId === 'ver_itinerario' || str_contains($norm, 'ver itinerario') || $norm === 'itinerario') {
            $this->handleVerItinerario($conversation);

            return;
        }

        // 9. Selección directa de fecha alternativa (ej: botón date_2026-10-07)
        $dateMatchSource = $buttonId ?? $trimmed;
        if (preg_match('/^date_(\d{4}-\d{2}-\d{2})$/i', $dateMatchSource, $m)) {
            $selectedDate = $m[1];
            $session = $conversation->session_data ?? [];
            $origin = $session['origin'] ?? 'VVI';
            $destination = $session['destination'] ?? 'LPB';
            $adults = (int) ($session['adults'] ?? 1);
            $children = (int) ($session['children'] ?? 0);
            $babies = (int) ($session['babies'] ?? 0);

            $this->displayFlightsForRouteAndDate($conversation, $origin, $destination, $selectedDate, $adults, $children, $babies);

            return;
        }

        // 10. Máquina de Estados del Bot
        switch ($conversation->current_state) {
            case 'MENU_INICIAL':
            case 'MENU_PRINCIPAL':
                $this->handleMenuPrincipal($conversation, $trimmed, $norm, $buttonId);
                break;
            case 'R1_SOLICITAR_RUTA':
                $this->handleSolicitarRuta($conversation, $trimmed);
                break;
            case 'R1_SOLICITAR_FECHA':
                $this->handleSolicitarFecha($conversation, $trimmed);
                break;
            case 'FECHAS_CERCANAS':
                $this->handleFechasCercanas($conversation, $trimmed, $buttonId);
                break;
            case 'VUELOS_MOSTRADOS':
                $this->handleVuelosMostrados($conversation, $trimmed, $norm, $buttonId);
                break;
            case 'R2_SOLICITAR_DATOS':
                $this->handleSolicitarDatos($conversation, $trimmed);
                break;
            case 'COLLECTING_COMPANIONS':
                $this->handleCollectingCompanions($conversation, $trimmed);
                break;
            default:
                $conversation->update(['current_state' => 'MENU_PRINCIPAL']);
                $this->replyMenuPrincipal($conversation);
                break;
        }
    }

    protected function handleMenuPrincipal(Conversation $conv, string $trimmed, string $norm, ?string $buttonId = null): void
    {
        // 1️⃣ Consultar Rutas y Disponibilidad (Opción 1 o Botón de Buscar Vuelos)
        $isOption1 = (
            $buttonId === 'menu_rutas'
            || $trimmed === 'menu_rutas'
            || $trimmed === '1'
            || $trimmed === '1️⃣'
            || $norm === '1'
            || $norm === 'opcion 1'
            || $norm === 'opcion uno'
            || $norm === 'rutas'
            || $norm === 'vuelos'
            || $norm === 'buscar vuelos'
            || $norm === 'consultar rutas'
            || $norm === 'consultar vuelos'
        );

        if ($isOption1) {
            $searchFlowId = (string) config('services.meta.flow_search_id', '');
            if (! empty($searchFlowId)) {
                $this->replyWithFlow(
                    $conv,
                    "✈️ *CONSULTA DE ITINERARIOS Y VUELOS*\n\n".
                    'Por favor presiona el botón a continuación 👇 para seleccionar tu ciudad de origen, destino, fecha y pasajeros en nuestro buscador interactivo.',
                    $searchFlowId,
                    '👉 Buscar Vuelos',
                    "flow_search_{$conv->id}_".time(),
                    '✈️ TAMep Línea Aérea',
                    'Búsqueda Oficial de Vuelos',
                    'FLIGHT_SEARCH'
                );

                return;
            }

            $conv->update(['current_state' => 'R1_SOLICITAR_RUTA']);
            $this->reply(
                $conv,
                "*CONSULTA DE ITINERARIOS*\n\n".
                "Por favor escribe la ciudad de Origen y Destino que deseas cotizar.\n".
                '_(Ejemplo: Santa Cruz a La Paz o VVI - LPB)_'
            );

            return;
        }

        // 2️⃣ Información sobre Carga (Opción 2 o Botón de Carga)
        $isOption2 = (
            $buttonId === 'menu_carga'
            || $trimmed === 'menu_carga'
            || $trimmed === '2'
            || $trimmed === '2️⃣'
            || $norm === '2'
            || $norm === 'opcion 2'
            || $norm === 'opcion dos'
            || $norm === 'carga'
            || $norm === 'encomiendas'
            || $norm === 'envios'
            || $norm === 'paquetes'
            || str_contains($norm, 'informacion sobre carga')
            || str_contains($norm, 'carga y envios')
        );

        if ($isOption2) {
            $this->replyInfoCarga($conv, $norm);

            return;
        }

        // 3️⃣ Nuestras Oficinas y Horarios (Opción 3 o Botón de Oficinas)
        $isOption3 = (
            $buttonId === 'menu_oficinas'
            || $trimmed === 'menu_oficinas'
            || $trimmed === '3'
            || $trimmed === '3️⃣'
            || $norm === '3'
            || $norm === 'opcion 3'
            || $norm === 'opcion tres'
            || $norm === 'oficinas'
            || $norm === 'horarios'
            || $norm === 'sucursales'
            || str_contains($norm, 'nuestras oficinas')
        );

        if ($isOption3) {
            $this->replyInfoOficinas($conv, $norm);

            return;
        }

        // 4️⃣ Cambios de Vuelo y Otras Líneas / Consultas
        $isOption4 = (
            $buttonId === 'menu_cambios'
            || $trimmed === 'menu_cambios'
            || $trimmed === '4'
            || $trimmed === '4️⃣'
            || $norm === '4'
            || $norm === 'opcion 4'
            || $norm === 'opcion cuatro'
            || str_contains($norm, 'cambio')
            || str_contains($norm, 'cambiar')
            || str_contains($norm, 'reprogramar')
            || str_contains($norm, 'reprogramacion')
            || str_contains($norm, 'otra consulta')
            || str_contains($norm, 'otras consultas')
            || str_contains($norm, 'call center')
        );

        if ($isOption4) {
            $this->replyCambioVuelo($conv);

            return;
        }

        // 5️⃣ Ver Itinerario Oficial
        if ($trimmed === 'ver_itinerario' || $buttonId === 'ver_itinerario' || $norm === 'itinerario' || $norm === 'ver itinerario') {
            $this->handleVerItinerario($conv);

            return;
        }

        // 5️⃣ Hablar con un Asesor
        if ($this->isDirectAgentRequest($norm) || $trimmed === 'btn_asesor' || $buttonId === 'btn_asesor') {
            $this->handoffToAgent($conv, 'Solicitud directa de hablar con un asesor', 'asesor');

            return;
        }

        // 6️⃣ Botón o comando volver
        if ($trimmed === 'menu_volver' || $buttonId === 'menu_volver' || in_array($norm, ['volver', 'menu', 'inicio'])) {
            $this->replyMenuPrincipal($conv);

            return;
        }

        // 7️⃣ Preguntas frecuentes específicas (Equipaje, Formas de Pago, etc.)
        if (str_contains($norm, 'equipaje') || str_contains($norm, 'maleta') || str_contains($norm, 'pago') || str_contains($norm, 'qr') || str_contains($norm, 'transferencia') || str_contains($norm, 'mascota')) {
            $faqAnswer = $this->handleFaq($norm);
            $buttons = [
                ['id' => 'menu_rutas', 'title' => '👉 Buscar Vuelos'],
                ['id' => 'menu_carga', 'title' => '📦 Carga y Envíos'],
                ['id' => 'menu_oficinas', 'title' => '🏢 Oficinas'],
            ];
            $this->replyWithButtons($conv, $faqAnswer, $buttons, '✈️ TAMep FAQ', 'Opciones Principales');

            return;
        }

        // 8️⃣ TODO MENSAJE QUE LLEGA DESDE EL PRIMER MOMENTO O SIN OPCIÓN VÁLIDA:
        // EL BOT RESPONDE SIEMPRE CON EL MENÚ PRINCIPAL DESARROLLADO
        $this->replyMenuPrincipal($conv);
    }

    protected function handleSolicitarRuta(Conversation $conv, string $trimmed): void
    {
        $parts = preg_split('/( a | - | y | al )/i', $trimmed);
        $origin = count($parts) > 1 ? trim($parts[0]) : 'VVI';
        $destination = count($parts) > 1 ? trim($parts[1]) : trim($trimmed);

        $session = $conv->session_data ?? [];
        $session['origin'] = $origin;
        $session['destination'] = $destination;

        $conv->update([
            'session_data' => $session,
            'current_state' => 'R1_SOLICITAR_FECHA',
        ]);

        $this->reply(
            $conv,
            "*FECHA DE VUELO*\n\n".
            "Ruta: *{$origin} ➔ {$destination}*\n\n".
            "Por favor indica la fecha de tu viaje (Ejemplo: 2026-10-15 o escribe 'mañana'):"
        );
    }

    protected function handleSolicitarFecha(Conversation $conv, string $trimmed): void
    {
        $session = $conv->session_data ?? [];
        $norm = mb_strtolower($trimmed);

        if (str_contains($norm, 'hoy')) {
            $flightDate = now()->format('Y-m-d');
        } elseif (str_contains($norm, 'mañana')) {
            $flightDate = now()->addDay()->format('Y-m-d');
        } else {
            try {
                $flightDate = Carbon::parse($trimmed)->format('Y-m-d');
            } catch (\Throwable) {
                $flightDate = now()->addDays(3)->format('Y-m-d');
            }
        }

        $origin = $session['origin'] ?? 'VVI';
        $destination = $session['destination'] ?? 'LPB';
        $adults = (int) ($session['adults'] ?? 1);
        $children = (int) ($session['children'] ?? 0);
        $babies = (int) ($session['babies'] ?? 0);

        $this->displayFlightsForRouteAndDate(
            $conv,
            $origin,
            $destination,
            $flightDate,
            $adults,
            $children,
            $babies
        );
    }

    protected function handleVuelosMostrados(Conversation $conv, string $trimmed, string $norm, ?string $buttonId = null): void
    {
        $session = $conv->session_data ?? [];
        $flights = $session['available_flights'] ?? [];

        $selectedIndex = null;
        $evalBtn = $buttonId ?? $trimmed;

        // 1. Identificador de botón interactivo (ej: "flight_1", "flight_2", "flight_3")
        if (preg_match('/^flight_(\d+)$/i', $evalBtn, $m)) {
            $selectedIndex = (int) $m[1] - 1;
        }
        // 2. Número de opción escrito por el pasajero ("1", "2", "3")
        elseif (is_numeric($trimmed) && (int) $trimmed >= 1 && (int) $trimmed <= count($flights)) {
            $selectedIndex = (int) $trimmed - 1;
        }
        // 3. Título del botón pulsado ej: "TM-204 (07:30)" o texto con número de vuelo ej: "TM-204", "204"
        else {
            foreach ($flights as $idx => $flight) {
                $fCode = mb_strtolower(str_replace(['-', ' '], '', $flight['flight_number']));
                $cleanInput = mb_strtolower(str_replace(['-', ' '], '', $trimmed));
                if (str_contains($cleanInput, $fCode) || str_contains($cleanInput, 'vuelo'.($idx + 1))) {
                    $selectedIndex = $idx;
                    break;
                }
            }
        }

        if ($selectedIndex === null || ! isset($flights[$selectedIndex])) {
            $this->reply($conv, 'Opción no válida. Por favor presiona uno de los botones interactivos o escribe 1, 2 o 3 según el vuelo deseado.');

            return;
        }

        $selectedFlight = $flights[$selectedIndex];
        $session['selected_flight'] = $selectedFlight;

        $conv->update([
            'session_data' => $session,
            'current_state' => 'R2_SOLICITAR_DATOS',
        ]);

        $paxCount = (int) ($session['passengers'] ?? $session['passengers_count'] ?? 1);
        $flowId = $this->getPassengerFlowIdForCount($paxCount);

        if (! empty($flowId)) {
            // Envío oficial de WhatsApp Flow para recopilación nativa y segura
            $this->replyWithFlow(
                $conv,
                "*DATOS PARA LA RESERVA*\n\n".
                "Has seleccionado el vuelo *{$selectedFlight['flight_number']}* (Salida: {$selectedFlight['departure_time']}).\n\n".
                'Por favor presiona el botón a continuación 👇 para completar los datos oficiales del pasajero en nuestro formulario seguro de WhatsApp.',
                $flowId,
                '👉 Llenar Formulario',
                "flow_conv_{$conv->id}_".time(),
                '✈️ TAMep Línea Aérea',
                'Formulario Oficial de Emisión'
            );
        } else {
            // Fallback seguro sin solicitar carnet en texto plano (en cumplimiento de las directivas de Meta)
            $this->reply(
                $conv,
                "*DATOS PARA LA RESERVA*\n\n".
                "Has seleccionado el vuelo *{$selectedFlight['flight_number']}* (Salida: {$selectedFlight['departure_time']}).\n\n".
                "Para continuar con tu cotización, escribe tu *Nombre Completo* y cuántos pasajeros viajarán.\n\n".
                '_(Ejemplo: Juan Pérez - 1 adulto)_'
            );
        }
    }

    protected function handleSolicitarDatos(Conversation $conv, string $trimmed): void
    {
        $session = $conv->session_data ?? [];
        $parts = preg_split('/( - | -|-|, |,| y )/', $trimmed);

        $name = trim($parts[0] ?? $conv->contact_name ?? 'Pasajero');
        $extra = trim($parts[1] ?? '');

        // Detección inteligente de número de pasajeros en texto libre
        // Ejemplos: "Juan Perez - 5 pasajeros", "Somos 5 personas", "5 boletos para Santa Cruz"
        $detectedPax = 1;
        if (preg_match('/(\d+)\s*(?:pasajero|persona|adulto|boleto|pax)/i', $trimmed, $m)) {
            $detectedPax = (int) $m[1];
        } elseif (preg_match('/\b(?:somos|para)\s+(\d+)\b/i', $trimmed, $m)) {
            $detectedPax = (int) $m[1];
        } elseif (is_numeric(trim($extra)) && (int) $extra >= 1 && (int) $extra <= 20) {
            $detectedPax = (int) $extra;
        }

        if ($detectedPax > 1) {
            $session['passengers'] = $detectedPax;
            $session['passengers_count'] = $detectedPax;
        }

        $session['passenger_name'] = $name;
        if (! empty($extra)) {
            $session['notes'] = $extra;
        }
        $conv->update(['session_data' => $session]);

        $this->handoffToAgent(
            $conv,
            "Cotización de vuelo iniciada por bot. Pasajero: {$name}".(! empty($extra) ? " ({$extra})" : ''),
            'compra_pasaje'
        );
    }

    /**
     * Handover a la cola de agentes humanos
     */
    public function handoffToAgent(Conversation $conv, string $reason, string $type = 'compra_pasaje'): void
    {
        $session = $conv->session_data ?? [];
        $selectedFlight = $session['selected_flight'] ?? [];

        // 1. Cambiar estado a espera de agente
        $conv->update([
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
        ]);

        // Notificar en tiempo real cambio de estado a la bandeja
        BroadcastHelper::safeBroadcast(new ConversationUpdated($conv, 'waiting_agent'));

        // Despachar alerta Web Push a los agentes (despierta Windows 11 o celular cerrado)
        try {
            app(WebPushService::class)->notifyWaitingPassenger($conv, "Solicita atención: {$reason}");
        } catch (\Throwable $e) {
            Log::warning('[WebPush] Error al enviar push de handoff: '.$e->getMessage());
        }

        // 2. Crear o actualizar Lead en el CRM para el panel de agentes
        $passengers = (int) (
            $session['passengers']
            ?? $session['passengers_count']
            ?? $session['cantidad_pasajeros']
            ?? 1
        );
        if ($passengers < 1) {
            $passengers = 1;
        }

        $flightNumber = ! empty($selectedFlight['flight_number']) ? $selectedFlight['flight_number'] : 'Por definir';
        $totalPrice = ! empty($selectedFlight['price']) ? (float) $selectedFlight['price'] : 0.00;

        $leadData = [
            'conversation_id' => $conv->id,
            'phone' => $conv->phone,
            'type' => $type,
            'passenger_name' => $session['passenger_name'] ?? $conv->contact_name,
            'passport_number' => $session['passport_number'] ?? null,
            'origin' => $session['origin'] ?? 'VVI',
            'destination' => $session['destination'] ?? 'LPB',
            'flight_date' => $session['flight_date'] ?? now()->format('Y-m-d'),
            'flight_number' => $flightNumber,
            'passengers_count' => $passengers,
            'total_price' => $totalPrice,
            'status' => 'nuevo',
            'details' => $reason,
            'bot_payload' => $session,
        ];

        try {
            Lead::create($leadData);
        } catch (\Throwable $e) {
            Log::warning('[LeadCreate handoff] '.$e->getMessage());
            unset($leadData['passengers_count']);
            try {
                Lead::create($leadData);
            } catch (\Throwable $e2) {
                Log::error('[LeadCreate handoff Fallback Error] '.$e2->getMessage());
            }
        }

        // 3. Notificar al pasajero en WhatsApp
        $this->reply(
            $conv,
            BotMessage::getText(
                'transferencia_asesor',
                "*CONECTANDO CON UN ASESOR DE VENTAS*\n\n".
                "¡Hemos transferido tu conversación a nuestro equipo de atención!\n\n".
                'Un asesor te atenderá en unos momentos para asistirte. ¡Agradecemos tu espera!'
            )
        );
    }

    protected function reply(Conversation $conv, string $message): void
    {
        $botMsg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $message,
        ]);

        // Transmitir mensaje del bot en vivo al CRM
        BroadcastHelper::safeBroadcast(new ChatMessageSent($botMsg, $conv->id, $conv));

        $this->whatsapp->sendMessage($conv->phone, $message);
    }

    protected function replyWithButtons(
        Conversation $conv,
        string $message,
        array $buttons,
        ?string $header = null,
        ?string $footer = null
    ): void {
        $botMsg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $message,
        ]);

        // Transmitir mensaje del bot en vivo al CRM
        BroadcastHelper::safeBroadcast(new ChatMessageSent($botMsg, $conv->id, $conv));

        $this->whatsapp->sendInteractiveButtons($conv->phone, $message, $buttons, $header, $footer);
    }

    /**
     * Envía un mensaje con botón interactivo que despliega un WhatsApp Flow nativo
     */
    protected function replyWithFlow(
        Conversation $conv,
        string $message,
        string $flowId,
        string $flowCta = '📝 Llenar Datos',
        string $flowToken = '',
        ?string $header = null,
        ?string $footer = null,
        string $screenId = 'PASSENGER_INFO'
    ): void {
        $botMsg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $message."\n\n_[Formulario interactivo de WhatsApp Flow enviado]_",
        ]);

        BroadcastHelper::safeBroadcast(new ChatMessageSent($botMsg, $conv->id, $conv));

        $this->whatsapp->sendFlowMessage(
            phone: $conv->phone,
            bodyText: $message,
            flowId: $flowId,
            flowCta: $flowCta,
            flowToken: ! empty($flowToken) ? $flowToken : "conv_{$conv->id}",
            headerText: $header,
            footerText: $footer,
            screenId: $screenId
        );
    }

    /**
     * Selecciona automáticamente el Flow adecuado según la cantidad de pasajeros (1 a 5 PAX).
     * Incluye fallback inteligente a flujos grupales o individuales si alguno específico no está en .env.
     */
    public function getPassengerFlowIdForCount(int $paxCount): string
    {
        $pax = max(1, $paxCount);

        // 1. Si existe un Flow configurado exactamente para esta cantidad (1 a 5)
        $exactKey = match ($pax) {
            1 => 'flow_passenger_1_id',
            2 => 'flow_passenger_2_id',
            3 => 'flow_passenger_3_id',
            4 => 'flow_passenger_4_id',
            default => 'flow_passenger_5_id',
        };
        $exactFlow = (string) config("services.meta.{$exactKey}", '');
        if (! empty($exactFlow)) {
            return $exactFlow;
        }

        // 2. Si son 2 o más pasajeros y se tiene configurado un Flow grupal genérico
        if ($pax > 1) {
            $groupFlow = (string) config('services.meta.flow_passenger_group_id', '');
            if (! empty($groupFlow)) {
                return $groupFlow;
            }

            // 3. Fallback descendente de flows de pasajeros existentes
            for ($i = min($pax, 5); $i >= 2; $i--) {
                $f = (string) config("services.meta.flow_passenger_{$i}_id", '');
                if (! empty($f)) {
                    return $f;
                }
            }
        }

        // 4. Fallback final: Formulario individual
        return (string) config('services.meta.flow_passenger_1_id', config('services.meta.flow_passenger_id', ''));
    }

    /**
     * Procesa los datos estructurados del Flow de Búsqueda de Vuelos
     */
    public function handleFlightSearchFlowResponse(Conversation $conv, array $flowData): void
    {
        $origin = $flowData['origin'] ?? 'VVI';
        $destination = $flowData['destination'] ?? 'LPB';
        $flightDate = $flowData['flight_date'] ?? now()->addDay()->format('Y-m-d');

        $adults = isset($flowData['adults']) ? max(1, min(70, (int) $flowData['adults'])) : null;
        $children = isset($flowData['children']) ? max(0, min(70, (int) $flowData['children'])) : 0;
        $babies = isset($flowData['babies']) ? max(0, min(70, (int) $flowData['babies'])) : 0;

        if ($adults === null) {
            $passengers = (int) ($flowData['passengers'] ?? $flowData['passengers_count'] ?? $flowData['cantidad_pasajeros'] ?? $flowData['pax'] ?? 1);
            $adults = max(1, $passengers);
            $children = 0;
            $babies = 0;
        }

        $this->displayFlightsForRouteAndDate(
            $conv,
            $origin,
            $destination,
            $flightDate,
            $adults,
            $children,
            $babies
        );
    }

    /**
     * Muestra vuelos reales de Transclick para una ruta y fecha.
     * Si no hay vuelos directos, busca y presenta automáticamente las fechas más cercanas disponibles.
     */
    public function displayFlightsForRouteAndDate(
        Conversation $conv,
        string $origin,
        string $destination,
        string $flightDate,
        int $adults = 1,
        int $children = 0,
        int $babies = 0
    ): void {
        $session = $conv->session_data ?? [];
        $passengers = $adults + $children + $babies;
        if ($passengers < 1) {
            $passengers = 1;
        }

        $session['origin'] = $origin;
        $session['destination'] = $destination;
        $session['flight_date'] = $flightDate;
        $session['passengers'] = $passengers;
        $session['passengers_count'] = $passengers;
        $session['adults'] = $adults;
        $session['children'] = $children;
        $session['babies'] = $babies;

        // Consultar vuelos disponibles en Transclick Solutions API
        $flights = $this->flightService->searchFlights(
            $origin,
            $destination,
            $flightDate,
            $adults,
            $children,
            $babies
        );

        $originCity = $this->flightService->getCityName($origin);
        $destCity = $this->flightService->getCityName($destination);
        $formattedDate = Carbon::parse($flightDate)->format('d/m/Y');

        if (! empty($flights)) {
            $session['available_flights'] = $flights;
            unset($session['nearby_dates']);

            $conv->update([
                'session_data' => $session,
                'current_state' => 'VUELOS_MOSTRADOS',
            ]);

            $paxDetails = [];
            $paxDetails[] = $adults === 1 ? '1 Adulto' : "{$adults} Adultos";
            if ($children > 0) {
                $paxDetails[] = $children === 1 ? '1 Niño' : "{$children} Niños";
            }
            if ($babies > 0) {
                $paxDetails[] = $babies === 1 ? '1 Bebé' : "{$babies} Bebés";
            }

            $baseLabel = $passengers > 1 ? "{$passengers} pasajeros" : '1 pasajero';
            $paxLabel = ($children > 0 || $babies > 0)
                ? "{$baseLabel} (".implode(', ', $paxDetails).')'
                : "{$baseLabel}";

            $msg = "✈️ *ITINERARIOS DISPONIBLES ({$formattedDate})*\n";
            $msg .= "Ruta: *{$originCity} ({$origin}) ➔ {$destCity} ({$destination})* ({$paxLabel})\n\n";

            $buttons = [];
            foreach ($flights as $index => $f) {
                $num = $index + 1;
                $aircraft = (! empty($f['aircraft']) && ! str_contains(strtolower($f['aircraft']), 'boeing'))
                    ? $f['aircraft']
                    : 'Avro RJ70 CP-3106';

                $msg .= "{$num}️⃣ *Vuelo {$f['flight_number']}*\n".
                    "   Salida: {$f['departure_time']} | Llegada: {$f['arrival_time']}\n".
                    "   Aeronave: {$aircraft}\n\n";

                if ($index < 3) {
                    $buttons[] = [
                        'id' => "flight_{$num}",
                        'title' => mb_substr("{$f['flight_number']} ({$f['departure_time']})", 0, 20),
                    ];
                }
            }
            $msg .= '_Selecciona tu vuelo presionando uno de los botones o escribe el número o código:_';

            $this->replyWithButtons($conv, $msg, $buttons, null, 'Vuelos y Reservas');

            return;
        }

        // SI NO HAY VUELOS: Buscar fechas más cercanas disponibles en Transclick
        $nearbyDates = $this->flightService->findNearbyFlights(
            $origin,
            $destination,
            $flightDate,
            $adults,
            $children,
            $babies,
            3
        );

        $session['available_flights'] = [];
        $session['nearby_dates'] = $nearbyDates;

        $conv->update([
            'session_data' => $session,
            'current_state' => ! empty($nearbyDates) ? 'FECHAS_CERCANAS' : 'MENU_PRINCIPAL',
        ]);

        $searchFlowId = (string) config('services.meta.flow_search_id', '');

        if (! empty($nearbyDates)) {
            $msg = "❌ *NO HAY VUELOS PARA EL {$formattedDate}*\n\n".
                "No disponemos de vuelos comerciales en la ruta *{$originCity} ({$origin}) ➔ {$destCity} ({$destination})* para la fecha seleccionada (*{$formattedDate}*).\n\n".
                "✨ *¡Pero encontramos vuelos disponibles en estas fechas cercanas!:*\n\n";

            $buttons = [];
            foreach ($nearbyDates as $idx => $item) {
                $dFormatted = $item['formatted'];
                $dayName = $item['day_name'];
                $diff = $item['offset_days'];
                $diffText = $diff > 0 ? "+{$diff} día(s)" : "{$diff} día(s)";

                $msg .= "📅 *{$dayName} {$dFormatted}* ({$diffText}):\n";
                foreach ($item['flights'] as $fl) {
                    $arrText = ! empty($fl['arrival_time']) ? " | Llegada: {$fl['arrival_time']}" : '';
                    $msg .= "   • *{$fl['flight_number']}* | Salida: {$fl['departure_time']}{$arrText}\n";
                }
                $msg .= "\n";

                if ($idx < 3) {
                    $buttons[] = [
                        'id' => "date_{$item['date']}",
                        'title' => mb_substr("📅 {$dFormatted}", 0, 20),
                    ];
                }
            }

            if (count($buttons) >= 3) {
                $msg .= "_Presiona el botón de la fecha deseada (o escribe 1, 2 o 3).\n".
                    'Si ninguna fecha te acomoda, escribe *ITINERARIO* para ver los vuelos programados o *ASESOR* para hablar con un agente._';
            } else {
                $msg .= '_Presiona uno de los botones abajo para seleccionar la fecha deseada o ver nuestro itinerario:_';
            }

            if (count($buttons) < 3) {
                $buttons[] = [
                    'id' => 'ver_itinerario',
                    'title' => '📅 Ver Itinerario',
                ];
            }

            $this->replyWithButtons($conv, $msg, $buttons, '📅 FECHAS CERCANAS', 'TAMep Vuelos');

            return;
        }

        // Si tampoco hay vuelos en fechas cercanas (ej. ruta no operada)
        $msg = BotMessage::getText(
            'sin_vuelos_disponibles',
            "❌ *NO SE ENCONTRARON VUELOS EN ESTA RUTA*\n\n".
            "No disponemos de vuelos programados en la ruta *{origen} ➔ {destino}* para la fecha *{fecha}* ni en los días cercanos.\n\n".
            'Te sugerimos consultar nuestro itinerario completo de vuelos o comunicarte con un asesor de ventas para itinerarios especiales.',
            [
                'origen' => "{$originCity} ({$origin})",
                'destino' => "{$destCity} ({$destination})",
                'fecha' => $formattedDate,
            ]
        );

        $buttons = [
            ['id' => 'ver_itinerario', 'title' => '📅 Ver Itinerario'],
            ['id' => 'btn_asesor', 'title' => '👤 Contactar Agente'],
            ['id' => 'menu_volver', 'title' => 'Menú Principal'],
        ];

        $this->replyWithButtons($conv, $msg, $buttons, '❌ SIN DISPONIBILIDAD', 'TAMep Línea Aérea');
    }

    /**
     * Maneja la selección de fechas cercanas cuando no hubo vuelo en la fecha original
     */
    protected function handleFechasCercanas(Conversation $conv, string $trimmed, ?string $buttonId = null): void
    {
        $session = $conv->session_data ?? [];
        $nearby = $session['nearby_dates'] ?? [];
        $origin = $session['origin'] ?? 'VVI';
        $destination = $session['destination'] ?? 'LPB';
        $adults = (int) ($session['adults'] ?? 1);
        $children = (int) ($session['children'] ?? 0);
        $babies = (int) ($session['babies'] ?? 0);
        $norm = mb_strtolower($trimmed, 'UTF-8');

        // Si el pasajero indica que ninguna opción le sirve o escribe no/cancelar
        if (
            str_contains($norm, 'ningun')
            || str_contains($norm, 'no me sirve')
            || str_contains($norm, 'no puedo')
            || str_contains($norm, 'no me gusta')
            || str_contains($norm, 'otra opcion')
            || str_contains($norm, 'otras fechas')
            || $norm === 'no'
            || $norm === 'cancelar'
        ) {
            $conv->update(['current_state' => 'MENU_PRINCIPAL']);
            $msg = "Entendido, lamentamos que ninguna de las fechas cercanas se ajuste a tus planes de viaje.\n\n".
                '¿Cómo te gustaría continuar?';
            $buttons = [
                ['id' => 'ver_itinerario', 'title' => '📅 Ver Itinerario'],
                ['id' => 'btn_asesor', 'title' => '👤 Contactar Agente'],
                ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
            ];
            $this->replyWithButtons($conv, $msg, $buttons, '✈️ TAMep Vuelos', 'Atención al Cliente');

            return;
        }

        // Si presionó "Ver Itinerario" o escribió itinerario
        if ($trimmed === 'ver_itinerario' || $buttonId === 'ver_itinerario' || str_contains($norm, 'itinerario')) {
            $this->handleVerItinerario($conv);

            return;
        }

        // Si presionó "Buscar otra fecha" o escribió buscar
        if ($trimmed === 'menu_rutas' || $buttonId === 'menu_rutas' || str_contains($norm, 'buscar') || str_contains($norm, 'otra fecha')) {
            $searchFlowId = (string) config('services.meta.flow_search_id', '');
            if (! empty($searchFlowId)) {
                $this->replyWithFlow(
                    $conv,
                    "✈️ *CONSULTA DE ITINERARIOS Y VUELOS*\n\n".
                    'Por favor presiona el botón a continuación 👇 para seleccionar una nueva fecha o ruta en nuestro buscador interactivo.',
                    $searchFlowId,
                    '👉 Buscar Vuelos',
                    "flow_search_{$conv->id}_".time(),
                    '✈️ TAMep Línea Aérea',
                    'Búsqueda Oficial de Vuelos',
                    'FLIGHT_SEARCH'
                );

                return;
            }

            $conv->update(['current_state' => 'R1_SOLICITAR_FECHA']);
            $this->reply(
                $conv,
                "Por favor escribe la nueva fecha en la que deseas consultar vuelos (Ejemplo: 2026-10-15 o escribe 'mañana'):"
            );

            return;
        }

        // Si escribió 1, 2 o 3 (opción de fecha cercana)
        if (is_numeric($trimmed) && isset($nearby[(int) $trimmed - 1])) {
            $date = $nearby[(int) $trimmed - 1]['date'];
            $this->displayFlightsForRouteAndDate($conv, $origin, $destination, $date, $adults, $children, $babies);

            return;
        }

        // Si envió texto con formato de fecha
        try {
            $parsed = Carbon::parse($trimmed)->format('Y-m-d');
            $this->displayFlightsForRouteAndDate($conv, $origin, $destination, $parsed, $adults, $children, $babies);

            return;
        } catch (\Throwable) {
            // No es fecha válida
        }

        $this->reply($conv, 'Por favor presiona uno de los botones con la fecha deseada, escribe el número de opción (1, 2 o 3), o escribe *ITINERARIO* para ver las rutas disponibles.');
    }

    /**
     * Envía la imagen del itinerario de vuelos oficial y/o el enlace web
     */
    protected function handleVerItinerario(Conversation $conv): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $itinerary = Setting::get('itinerary', []);

        $webUrl = ! empty($itinerary['web_url'])
            ? (string) $itinerary['web_url']
            : (string) config('services.airline.itinerario_web_url', 'https://tamep.bo/itinerarios');

        $configuredImageUrl = ! empty($itinerary['image_url'])
            ? (string) $itinerary['image_url']
            : (string) config('services.airline.itinerario_image_url', '');

        $localPath = (! empty($itinerary['local_path']) && file_exists($itinerary['local_path']))
            ? $itinerary['local_path']
            : null;

        $imageUrl = ! empty($configuredImageUrl) ? $configuredImageUrl : null;

        // Fallback a archivos locales si no hay ruta registrada en la base de datos
        if (! $localPath) {
            $localJpg = public_path('images/itinerario.jpg');
            $localPng = public_path('images/itinerario.png');
            $localWebp = public_path('images/itinerario.webp');

            if (file_exists($localJpg)) {
                $localPath = $localJpg;
                $imageUrl = $imageUrl ?: url('images/itinerario.jpg');
            } elseif (file_exists($localPng)) {
                $localPath = $localPng;
                $imageUrl = $imageUrl ?: url('images/itinerario.png');
            } elseif (file_exists($localWebp)) {
                $localPath = $localWebp;
                $imageUrl = $imageUrl ?: url('images/itinerario.webp');
            }
        }

        $customNote = ! empty($itinerary['caption']) ? "\n\n💡 ".trim($itinerary['caption']) : '';

        $caption = BotMessage::getText(
            'info_itinerario',
            "📅 *ITINERARIO OFICIAL DE VUELOS*\n\n".
            "Aquí puedes revisar las frecuencias semanales, horarios de salida y destinos programados.\n\n".
            '_Para cotizar una fecha del itinerario o consultar tarifas, comunícate con un asesor o usa nuestro buscador de vuelos._'
        );

        if (! empty($customNote) && ! str_contains($caption, trim($itinerary['caption']))) {
            $caption .= $customNote;
        }

        // Registrar mensaje en el historial del CRM
        $botMsg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $caption,
            'media_url' => $imageUrl,
            'media_type' => ($localPath || ! empty($configuredImageUrl)) ? 'image' : null,
        ]);
        BroadcastHelper::safeBroadcast(new ChatMessageSent($botMsg, $conv->id, $conv));

        // Enviar imagen por WhatsApp si existe, o mensaje de texto con el enlace
        $sentImage = false;
        if ($localPath && file_exists($localPath)) {
            $sentImage = $this->whatsapp->sendImage($conv->phone, $imageUrl, $caption, $localPath);
        } elseif (! empty($configuredImageUrl)) {
            $sentImage = $this->whatsapp->sendImage($conv->phone, $configuredImageUrl, $caption);
        }

        if (! $sentImage) {
            $this->whatsapp->sendMessage($conv->phone, $caption);
        }

        // Ofrecer botones de seguimiento para continuar la atención
        $followButtons = [
            ['id' => 'menu_rutas', 'title' => '👉 Buscar Vuelos'],
            ['id' => 'btn_asesor', 'title' => '👤 Contactar Agente'],
            ['id' => 'menu_volver', 'title' => 'Menú Principal'],
        ];

        $this->replyWithButtons(
            $conv,
            '¿Deseas consultar alguna fecha o comunicarte con un asesor?',
            $followButtons,
            '✈️ TAMep Línea Aérea',
            'Atención al Pasajero'
        );
    }

    /**
     * Procesa los datos estructurados recibidos desde un WhatsApp Flow
     */
    public function handleFlowResponse(Conversation $conv, array $flowData): void
    {
        $session = $conv->session_data ?? [];
        $firstName = trim($flowData['full_name'] ?? '');
        $lastName = trim($flowData['full_ape'] ?? '');
        $fullName = trim("{$firstName} {$lastName}") ?: ($conv->contact_name ?? 'Pasajero');
        $docType = $flowData['doc_type'] ?? 'CI';
        $docNumber = trim($flowData['doc_number'] ?? '');
        $docExp = $flowData['doc_exp'] ?? '';
        $fullDoc = (! empty($docExp) && $docExp !== 'EXT') ? "{$docNumber} {$docExp}" : $docNumber;
        $phone = $flowData['doc_telefonico'] ?? $flowData['phone'] ?? null;
        $email = $flowData['email'] ?? null;
        $birthDate = $flowData['birth_date'] ?? null;
        $gender = $flowData['gender'] ?? null;
        $passportNumber = trim($flowData['passport_number'] ?? '');
        $observation = trim($flowData['observation'] ?? '');

        // Desglose de factura oficial Transclick
        $invoiceRazon = trim($flowData['invoice_razon_social'] ?? '');
        $invoiceDocType = $flowData['invoice_doc_type'] ?? '';
        $invoiceNit = trim($flowData['invoice_nit'] ?? '');
        if (! empty($invoiceRazon) || ! empty($invoiceNit)) {
            $invParts = [];
            if (! empty($invoiceRazon)) {
                $invParts[] = "Razón Social: {$invoiceRazon}";
            }
            if (! empty($invoiceDocType)) {
                $invParts[] = "Tipo: {$invoiceDocType}";
            }
            if (! empty($invoiceNit)) {
                $invParts[] = "NIT/Doc: {$invoiceNit}";
            }
            $invoiceData = implode(' | ', $invParts);
        } else {
            $invoiceData = $flowData['invoice_data'] ?? null;
        }

        $rawCategory = $flowData['passenger_category'] ?? null;
        $category = match ($rawCategory) {
            'ADULT' => 'Adulto (+12 años)',
            'CHILD' => 'Menor / Niño (2 a 11 años)',
            'INFANT' => 'Infante / Bebé (< 2 años)',
            default => $rawCategory ?: null
        };

        if (! $category) {
            $adults = isset($session['adults']) ? (int) $session['adults'] : null;
            $children = isset($session['children']) ? (int) $session['children'] : 0;
            $babies = isset($session['babies']) ? (int) $session['babies'] : 0;
            if ($adults !== null && $adults > 0) {
                $catParts = [];
                $catParts[] = $adults === 1 ? '1 Adulto' : "{$adults} Adultos";
                if ($children > 0) {
                    $catParts[] = $children === 1 ? '1 Niño' : "{$children} Niños";
                }
                if ($babies > 0) {
                    $catParts[] = $babies === 1 ? '1 Bebé' : "{$babies} Bebés";
                }
                $category = implode(', ', $catParts);
            } else {
                $category = $session['passenger_category'] ?? 'Adulto (+12 años)';
            }
        }
        $luggage = $flowData['luggage_option'] ?? null;
        $passengers = (int) (
            $flowData['passengers']
            ?? $flowData['passengers_count']
            ?? $flowData['cantidad_pasajeros']
            ?? $flowData['pax']
            ?? $session['passengers']
            ?? $session['passengers_count']
            ?? $session['cantidad_pasajeros']
            ?? 1
        );
        if ($passengers < 1) {
            $passengers = 1;
        }

        $session['passengers'] = $passengers;
        $session['passengers_count'] = $passengers;
        $session['passenger_category'] = $category;
        $session['passenger_name'] = $fullName;
        $session['passport_number'] = ! empty($passportNumber) ? "PAS: {$passportNumber}" : "{$docType}: {$fullDoc}";
        if (! empty($gender)) {
            $session['gender'] = $gender;
        }
        if (! empty($observation)) {
            $session['observation'] = $observation;
        }
        if (! empty($phone)) {
            $session['contact_phone'] = $phone;
        }
        if (! empty($email)) {
            $session['contact_email'] = $email;
        }
        if (! empty($invoiceData)) {
            $session['invoice_data'] = $invoiceData;
        }
        if (! empty($birthDate)) {
            $session['birth_date'] = $birthDate;
        }
        $termsAccepted = ! empty($flowData['terms_accepted']) || ! empty($flowData['privacy_terms']);
        $privacyAccepted = ! empty($flowData['privacy_accepted']) || ! empty($flowData['privacy_terms']);
        $session['passenger_flow'] = [
            'full_name' => $fullName,
            'doc_type' => $docType,
            'doc_number' => $docNumber,
            'passport_number' => $passportNumber,
            'gender' => $gender,
            'phone' => $phone,
            'email' => $email,
            'birth_date' => $birthDate,
            'observation' => $observation,
            'invoice_data' => $invoiceData,
            'invoice_razon_social' => $invoiceRazon,
            'invoice_doc_type' => $invoiceDocType,
            'invoice_nit' => $invoiceNit,
            'passenger_category' => $category,
            'luggage_option' => $luggage,
            'passengers' => $passengers,
            'passengers_count' => $passengers,
            'terms_accepted' => $termsAccepted,
            'privacy_accepted' => $privacyAccepted,
        ];

        $selectedFlight = $session['selected_flight'] ?? [];
        $flightNumber = ! empty($selectedFlight['flight_number']) ? $selectedFlight['flight_number'] : null;
        $totalPrice = ! empty($selectedFlight['price']) ? (float) $selectedFlight['price'] : 0.00;

        // Detectar si vinieron acompañantes registrados en cualquiera de los formularios (2 a 5 PAX)
        $formCompanions = [];
        for ($i = 1; $i <= 10; $i++) {
            $cFirstName = trim($flowData["comp{$i}_name"] ?? $flowData["pax{$i}_name"] ?? '');
            $cLastName = trim($flowData["comp{$i}_ape"] ?? $flowData["pax{$i}_ape"] ?? '');
            $cName = trim("{$cFirstName} {$cLastName}") ?: $cFirstName;
            $cDoc = trim($flowData["comp{$i}_doc"] ?? $flowData["pax{$i}_doc"] ?? '');
            $cBirth = trim($flowData["comp{$i}_birth"] ?? $flowData["pax{$i}_birth"] ?? '');
            $cGen = trim($flowData["comp{$i}_gender"] ?? $flowData["pax{$i}_gender"] ?? '');
            $cPass = trim($flowData["comp{$i}_passport"] ?? $flowData["pax{$i}_passport"] ?? '');
            $cObs = trim($flowData["comp{$i}_observation"] ?? $flowData["pax{$i}_observation"] ?? '');

            if (! empty($cName)) {
                $compDoc = ! empty($cDoc) ? $cDoc : (! empty($cPass) ? "PAS: {$cPass}" : 'Por confirmar');
                $compItem = [
                    'name' => $cName,
                    'first_name' => $cFirstName,
                    'last_name' => $cLastName,
                    'doc' => $compDoc,
                    'raw' => "{$cName} - {$compDoc}",
                ];
                if (! empty($cBirth)) {
                    $compItem['birth_date'] = $cBirth;
                }
                if (! empty($cGen)) {
                    $compItem['gender'] = $cGen;
                }
                if (! empty($cPass)) {
                    $compItem['passport_number'] = $cPass;
                }
                if (! empty($cObs)) {
                    $compItem['observation'] = $cObs;
                }
                $formCompanions[] = $compItem;
            }
        }

        if (! empty($formCompanions)) {
            $session['companions'] = $formCompanions;
            $directPax = 1 + count($formCompanions);
            if ($passengers < $directPax) {
                $passengers = $directPax;
                $session['passengers'] = $passengers;
                $session['passengers_count'] = $passengers;
            }
        }

        // 1. Crear Lead con todos los datos del titular y acompañantes
        $docDisplay = ! empty($passportNumber) ? "CI: {$docNumber} / PAS: {$passportNumber}" : "{$docType} {$fullDoc}";
        $details = "Registro Transclick. Pasajero: {$fullName}, Género: {$gender}, Doc: {$docDisplay}, Pasajeros: {$passengers}";
        if (! empty($phone)) {
            $details .= ", Tel: {$phone}";
        }
        if (! empty($email)) {
            $details .= ", Email: {$email}";
        }
        if (! empty($birthDate)) {
            $details .= ", Nac: {$birthDate}";
        }
        if (! empty($observation)) {
            $details .= ", Obs: {$observation}";
        }
        if (! empty($invoiceData)) {
            $details .= ", Factura: [{$invoiceData}]";
        }
        if ($privacyAccepted) {
            $details .= ', Privacidad/Términos: Aceptados';
        }

        if (! empty($formCompanions)) {
            foreach ($formCompanions as $cIdx => $comp) {
                $pNum = $cIdx + 2;
                $genInfo = ! empty($comp['gender']) ? " ({$comp['gender']})" : '';
                $details .= " | Pasajero {$pNum}: {$comp['name']} (Doc: {$comp['doc']}{$genInfo})";
            }
        }

        $leadData = [
            'conversation_id' => $conv->id,
            'phone' => $conv->phone,
            'type' => 'compra_pasaje',
            'passenger_name' => $fullName,
            'passport_number' => "{$docType}: {$fullDoc}",
            'origin' => $session['origin'] ?? 'VVI',
            'destination' => $session['destination'] ?? 'LPB',
            'flight_date' => $session['flight_date'] ?? now()->format('Y-m-d'),
            'flight_number' => $flightNumber ?: 'Por definir',
            'passengers_count' => $passengers,
            'total_price' => $totalPrice,
            'status' => 'nuevo',
            'details' => $details,
            'bot_payload' => $session,
        ];

        try {
            Lead::create($leadData);
        } catch (\Throwable $e) {
            Log::warning('[LeadCreate flow] '.$e->getMessage());
            unset($leadData['passengers_count']);
            try {
                Lead::create($leadData);
            } catch (\Throwable $e2) {
                Log::error('[LeadCreate flow Fallback Error] '.$e2->getMessage());
            }
        }

        // Si se registraron todos los acompañantes directamente en el formulario (o viaja solo 1 persona):
        $neededComps = max(0, $passengers - 1);
        if ($neededComps > 0 && count($formCompanions) >= $neededComps) {
            $conv->update([
                'session_data' => $session,
                'contact_name' => $fullName,
                'status' => 'waiting_agent',
                'current_state' => 'AGENT_HANDOFF',
            ]);

            BroadcastHelper::safeBroadcast(new ConversationUpdated($conv, 'waiting_agent'));

            try {
                $flightDesc = $flightNumber ? "({$flightNumber})" : '(Por definir)';
                app(WebPushService::class)->notifyWaitingPassenger(
                    $conv,
                    "Nómina de {$passengers} pasajeros registrada en formulario: {$fullName} {$flightDesc}"
                );
            } catch (\Throwable $e) {
                Log::warning('[WebPush] Error al enviar push de flow response: '.$e->getMessage());
            }

            $vueloCode = (! empty($flightNumber) && $flightNumber !== 'Por definir') ? $flightNumber : 'seleccionado';
            $rosterText = "1️⃣ *{$fullName}* (Titular) - {$docType} {$fullDoc}\n";
            foreach ($formCompanions as $cIdx => $comp) {
                $pNum = $cIdx + 2;
                $rosterText .= "{$pNum}️⃣ *{$comp['name']}* - Doc: {$comp['doc']}\n";
            }

            $confirmMsg = "📋 *¡NÓMINA DE PASAJEROS REGISTRADA CON ÉXITO!*\n\n".
                "Hemos recibido los datos de los *{$passengers} pasajeros* para el vuelo *{$vueloCode}*:\n\n".
                $rosterText."\n".
                "Un asesor de TAMep está revisando tu reserva y la documentación para emitir tus boletos. ¡En unos instantes se comunicará contigo! ✈️🎫";

            $this->reply($conv, $confirmMsg);

            return;
        }

        // Si faltan acompañantes por registrar (ej: formulario con 0 acompañantes pero compra para N > 1):
        if ($passengers > 1) {
            $alreadyCount = count($formCompanions);
            $session['companions'] = $formCompanions;
            $session['current_companion_index'] = $alreadyCount + 1;

            $conv->update([
                'session_data' => $session,
                'contact_name' => $fullName,
                'status' => 'bot_active',
                'current_state' => 'COLLECTING_COMPANIONS',
            ]);

            BroadcastHelper::safeBroadcast(new ConversationUpdated($conv, 'collecting_companions'));

            $vueloCode = (! empty($flightNumber) && $flightNumber !== 'Por definir') ? $flightNumber : 'seleccionado';
            $nextIdx = $alreadyCount + 1;
            $nextPaxNum = $nextIdx + 1;
            $compPrompt = "✅ *¡Datos del titular registrados con éxito!*\n\n".
                "👥 Has solicitado boletos para *{$passengers} pasajeros* en el vuelo *{$vueloCode}*.\n".
                "El Pasajero 1 (Titular) es *{$fullName}*.\n\n".
                "Para completar la nómina de emisión, por favor escribe los datos de tus *{$neededComps} acompañante(s)*.\n\n".
                "👤 *Acompañante {$nextIdx} de {$neededComps} (Pasajero {$nextPaxNum}/{$passengers}):*\n".
                "Escribe su *Nombre completo* y su *N° de Documento / CI*:\n".
                "_(Ejemplo: Juan Pérez Gómez - 7891234 LP)_";

            $this->reply($conv, $compPrompt);

            return;
        }

        // Caso de 1 solo pasajero: entrega inmediata al asesor
        $conv->update([
            'session_data' => $session,
            'contact_name' => $fullName,
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
        ]);

        // Notificar en vivo en el CRM
        BroadcastHelper::safeBroadcast(new ConversationUpdated($conv, 'waiting_agent'));

        // Alerta Web Push para despertar a los asesores
        try {
            $flightDesc = $flightNumber ? "({$flightNumber})" : '(Por definir)';
            app(WebPushService::class)->notifyWaitingPassenger(
                $conv,
                "Pasajero completó formulario WhatsApp: {$fullName} {$flightDesc}"
            );
        } catch (\Throwable $e) {
            Log::warning('[WebPush] Error al enviar push de flow response: '.$e->getMessage());
        }

        // Enviar confirmación cordial al pasajero por WhatsApp
        $vueloCode = (! empty($flightNumber) && $flightNumber !== 'Por definir') ? $flightNumber : 'seleccionado';
        $confirmMsg = BotMessage::getText(
            'confirmacion_datos_recibidos',
            "✅ *¡DATOS DE PASAJERO RECIBIDOS CON ÉXITO!*\n\n".
            "Muchas gracias, *{nombre}*. Hemos registrado de manera segura tus datos para el vuelo *{vuelo}*.\n\n".
            'Un asesor de TAMep está revisando tu solicitud. ¡En unos instantes se comunicará contigo! ✈️',
            [
                'nombre' => $fullName,
                'vuelo' => $vueloCode,
            ]
        );
        $this->reply($conv, $confirmMsg);
    }


    /**
     * Recopila interactivamente los datos de los acompañantes en compras múltiples (PAX > 1)
     */
    protected function handleCollectingCompanions(Conversation $conv, string $trimmed): void
    {
        $session = $conv->session_data ?? [];
        $passengers = (int) ($session['passengers'] ?? $session['passengers_count'] ?? 1);
        $needed = max(1, $passengers - 1);
        $companions = $session['companions'] ?? [];

        // Soporte para una o varias líneas (si el cliente envía varios nombres juntos)
        $rawLines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $trimmed)));
        if (empty($rawLines)) {
            $rawLines = [$trimmed];
        }

        foreach ($rawLines as $line) {
            if (count($companions) >= $needed) {
                break;
            }

            // Limpieza de prefijos tipo "1.", "1-", "2)", etc.
            $cleanLine = preg_replace('/^\d+[\.\)\-\:\s]+\s*/', '', $line);

            // Separar nombre y documento
            $parts = preg_split('/(?:\s*-\s*|\s*,\s*|\s+CI[:\s]+|\s+DNI[:\s]+)/iu', $cleanLine, 2);
            $name = trim($parts[0] ?? $cleanLine);
            $doc = trim($parts[1] ?? '');

            if (empty($doc)) {
                if (preg_match('/^(.*?)\s+(\d{5,}[\w\-]*)$/u', $cleanLine, $m)) {
                    $name = trim($m[1]);
                    $doc = trim($m[2]);
                } else {
                    $doc = 'Por confirmar';
                }
            }

            $companions[] = [
                'name' => $name,
                'doc' => $doc,
                'raw' => $line,
            ];
        }

        $session['companions'] = $companions;
        $currentCount = count($companions);

        // ¿Faltan más acompañantes por registrar?
        if ($currentCount < $needed) {
            $nextIdx = $currentCount + 1;
            $nextPaxNum = $nextIdx + 1;
            $session['current_companion_index'] = $nextIdx;
            $conv->update(['session_data' => $session]);

            $prevName = $companions[$currentCount - 1]['name'] ?? 'Registrado';
            $prevDoc = $companions[$currentCount - 1]['doc'] ?? '';

            $msg = "✅ *Acompañante {$currentCount} registrado:* {$prevName} ({$prevDoc})\n\n".
                   "👤 *Acompañante {$nextIdx} de {$needed} (Pasajero {$nextPaxNum}/{$passengers}):*\n".
                   "Por favor escribe su *Nombre completo* y su *N° de Documento / CI*:\n".
                   "_(Ejemplo: Maria Lopez Soto - 6543210 CB)_";
            $this->reply($conv, $msg);

            return;
        }

        // ¡Nómina completa recopilada!
        $fullName = $session['passenger_name'] ?? 'Titular';
        $docNumber = $session['passport_number'] ?? 'Sin Documento';
        $selectedFlight = $session['selected_flight'] ?? [];
        $flightNumber = ! empty($selectedFlight['flight_number']) ? $selectedFlight['flight_number'] : 'Por definir';

        // Construir detalles consolidados para el Lead y el CRM
        $details = "Registro Formulario con Nómina ({$passengers} PAX). Titular: {$fullName}, Doc: {$docNumber}";
        $rosterListText = "1️⃣ *{$fullName}* (Titular) - {$docNumber}\n";

        foreach ($companions as $idx => $comp) {
            $pNum = $idx + 2;
            $details .= " | Pasajero {$pNum}: {$comp['name']} (Doc: {$comp['doc']})";
            $rosterListText .= "{$pNum}️⃣ *{$comp['name']}* - Doc: {$comp['doc']}\n";
        }

        // Actualizar el Lead con la nómina en details y bot_payload
        $lead = Lead::where('conversation_id', $conv->id)->latest('id')->first();
        if ($lead) {
            $lead->update([
                'details' => $details,
                'bot_payload' => $session,
            ]);
        }

        // Pasar la conversación a AGENT_HANDOFF
        $conv->update([
            'session_data' => $session,
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
            'last_interaction_at' => now(),
        ]);

        // Notificar en vivo en el CRM
        BroadcastHelper::safeBroadcast(new ConversationUpdated($conv, 'waiting_agent'));

        // Alerta Web Push
        try {
            app(WebPushService::class)->notifyWaitingPassenger(
                $conv,
                "Nómina completa de {$passengers} pasajeros registrada: {$fullName}"
            );
        } catch (\Throwable $e) {
            Log::warning('[WebPush] Error al enviar push: '.$e->getMessage());
        }

        // Enviar confirmación al pasajero con la lista completa
        $confirmMsg = "📋 *¡NÓMINA DE PASAJEROS REGISTRADA CON ÉXITO!*\n\n".
                      "Hemos registrado a los *{$passengers} pasajeros* para el vuelo *{$flightNumber}*:\n\n".
                      $rosterListText."\n".
                      "Un asesor de TAMep está revisando tu reserva y la documentación para emitir tus boletos. ¡En unos instantes se comunicará contigo! ✈️🎫";

        $this->reply($conv, $confirmMsg);
    }

    protected function replyMenuPrincipal(Conversation $conv): void
    {
        $searchFlowId = (string) config('services.meta.flow_search_id', '');

        $defaultMenuFlow = "¡Hola! 👋 Te damos una cordial bienvenida a *TAMep* ✈️\n\n".
            "Presiona el botón a continuación 👇 para abrir nuestro buscador interactivo y consultar rutas, fechas y asientos disponibles:\n\n".
            "💡 *Otras opciones directas:*\n".
            "📦 Para información de carga escribe *CARGA* o el número *2*\n".
            "🏢 Para oficinas y horarios escribe *OFICINAS* o el número *3*\n\n".
            "🔄 ¿NECESITAS CAMBIAR TU VUELO?\n".
            "Solicita el cambio de vuelo o cambio de ruta mediante nuestros canales oficiales de WhatsApp:\n".
            "📱 73748234 (https://wa.me/59173748234) | 57075796 (https://wa.me/59157075796)";

        if (! empty($searchFlowId)) {
            $text = BotMessage::getText('menu_principal_flow', $defaultMenuFlow);

            $this->replyWithFlow(
                $conv,
                $text,
                $searchFlowId,
                '👉 Buscar Vuelos',
                "flow_search_{$conv->id}_".time(),
                '✈️ TAMep Línea Aérea',
                'Bienvenido a bordo',
                'FLIGHT_SEARCH'
            );

            return;
        }

        $defaultMenuText = "¡Hola! 👋 Te damos una cordial bienvenida a *TAMep* ✈️\n\n".
            "✨ *MENÚ PRINCIPAL*\n".
            "Estamos aquí para ayudarte a planificar tu viaje o gestionar tus envíos de manera rápida y segura.\n\n".
            "Elige una de las siguientes opciones:\n\n".
            "1️⃣ *Consultar Rutas y Vuelos*\n".
            "2️⃣ *Información sobre Carga*\n".
            "3️⃣ *Nuestras Oficinas y Horarios*\n\n".
            "🔄 ¿NECESITAS CAMBIAR TU VUELO?\n".
            "Solicita el cambio de vuelo o cambio de ruta mediante nuestros canales oficiales de WhatsApp:\n".
            "📱 73748234 (https://wa.me/59173748234) | 57075796 (https://wa.me/59157075796)\n\n".
            "_Presiona uno de los botones abajo o escribe el número (1-3):_";

        $text = BotMessage::getText('menu_principal_text', $defaultMenuText);

        $buttons = [
            ['id' => 'menu_rutas', 'title' => '👉 Buscar Vuelos'],
            ['id' => 'menu_carga', 'title' => '📦 Carga y Envíos'],
            ['id' => 'menu_oficinas', 'title' => '🏢 Oficinas'],
        ];

        $this->replyWithButtons($conv, $text, $buttons, '✈️ TAMep Línea Aérea', 'Bienvenido a bordo');
    }

    /**
     * Responde con información oficial para cambios de vuelo y derivación a otras líneas
     */
    protected function replyCambioVuelo(Conversation $conv): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $msg = "✈️ *¡VUELA CON TAMEP!*\n".
               "¡Ahora es más fácil comprar y gestionar tu pasaje!\n\n".
               "🎫 *COMPRA TU PASAJE*\n".
               "Rápido, fácil y sin complicaciones a través de nuestro Call Center y Chatbot.\n".
               "📞 72223334\n\n".
               "🔄 *¿NECESITAS CAMBIAR TU VUELO?*\n".
               "Solicita el cambio de vuelo o cambio de ruta mediante nuestros canales oficiales de WhatsApp:\n".
               "📱 73748234 (https://wa.me/59173748234) | 57075796 (https://wa.me/59157075796)\n\n".
               "TAMEP\n".
               "Tu viaje comienza aquí. ✈️";

        $buttons = [
            ['id' => 'menu_rutas', 'title' => '👉 Buscar Vuelos'],
            ['id' => 'menu_oficinas', 'title' => '🏢 Oficinas'],
            ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
        ];

        $this->replyWithButtons($conv, $msg, $buttons, '🔄 Otras Consultas', 'Atención Oficial');
    }

    protected function replyInfoCarga(Conversation $conv, string $norm = ''): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $msg = BotMessage::getText(
            'info_carga',
            "✈️📦 *EMPRESA PÚBLICA DE TRANSPORTE AÉREO MILITAR – TAMEP* 🛬\n".
            "📦 *SERVICIOS DE CARGA AÉREA*\n".
            "Atendemos consultas relacionadas con:\n".
            "• Cotización y tarifas de carga\n".
            "• Envíos de carga a destinos nacionales\n".
            "• Seguimiento y estado de la carga vía Web https://www.tamep.bo\n".
            "• Requisitos y documentación para el envío de carga especial y mercancías sujetas a condiciones particulares como animales vivos y féretros o restos humanos\n\n".
            "✈️📦 *TAMEP CARGO A TU SERVICIO* 📦✈️\n\n".
            "*ENVIAMOS TUS ENCOMIENDAS Y CARGA A DIFERENTES DESTINOS DE BOLIVIA*\n\n".
            "Para brindarte una mejor atención, ponemos a tu disposición nuestras *OFICINAS REGIONALES*, donde podrás realizar tus envíos, recibir información y consultar sobre nuestros servicios de *CARGA Y ENCOMIENDAS*.\n\n".
            "📍 *REGIONAL AV. MONTES – LA PAZ*\n".
            "📌 Av. Montes esq. José María Serrano N° 734\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "📞 *72005633*\n".
            "📍 Ubicación: https://maps.app.goo.gl/uNhcw3m1bhizLs2u7\n\n".
            "📍 *REGIONAL EL ALTO*\n".
            "📌 Av. Juan Pablo II, lado Polideportivo Héroes de Octubre – Transporte Aéreo Militar (TAM)\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *72593881*\n".
            "📍 Ubicación: https://maps.app.goo.gl/g62pQMn4DE9xvzca6\n\n".
            "📍 *REGIONAL COBIJA*\n".
            "📌 Av. 9 de febrero frente al regimiento policial, *TAMEP CARGO*\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *71494215*\n".
            "📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n".
            "📍 *REGIONAL COCHABAMBA*\n".
            "📌 Aeropuerto Internacional Jorge Wilstermann\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *67406524*\n".
            "📍 Ubicación: https://maps.app.goo.gl/iRpueTMRb495XB926\n\n".
            "📍 *REGIONAL SANTA CRUZ*\n".
            "📌 Aeropuerto El Trompillo – ingreso al aeropuerto, al fondo, *TAMEP CARGO*\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *71343233*\n".
            "📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n".
            "📍 *REGIONAL TRINIDAD*\n".
            "📌 Aeropuerto Teniente Jorge Henrich Arauz (TTD)\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *72059370*\n".
            "📍 Ubicación: https://maps.app.goo.gl/HBGy7sk2oE2egDaF6\n\n".
            "📍 *REGIONAL RIBERALTA*\n".
            "📌 Aeropuerto de Riberalta\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *72053313*\n".
            "📍 Ubicación: https://maps.app.goo.gl/XPiwwLSWxcLcKQjZ7\n\n".
            "📍 *REGIONAL GUAYARAMERÍN*\n".
            "📌 Aeropuerto de Guayaramerín\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 13:00\n".
            "📞 *75898364*\n".
            "📍 Ubicación: https://maps.app.goo.gl/4YQQsNRnmdrLTnTA8\n\n".
            "📍 Acércate a cualquiera de nuestras oficinas regionales y recibe información sobre nuestros servicios de *envío de carga y encomiendas*.\n\n".
            '_Escribe *MENU* para volver al inicio o *BUSCAR* para consultar vuelos._'
        );

        $this->reply($conv, $msg);
    }

    protected function replyInfoOficinas(Conversation $conv, string $norm = ''): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $msg = BotMessage::getText(
            'info_oficinas',
            "🇧🇴✈️ *TAMEP CONECTA BOLIVIA*\n\n".
            "Para brindarte una atención más cercana, ponemos a tu disposición nuestras *OFICINAS REGIONALES*:\n\n".
            "📍 *REGIONAL LA PAZ*\n".
            "📌 Av. Montes esq. José María Serrano N.º 734\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/uNhcw3m1bhizLs2u7\n\n".
            "📍 *REGIONAL EL ALTO*\n".
            "📌 Av. Juan Pablo II, lado Polideportivo Héroes de Octubre – Transporte Aéreo Militar (TAM)\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/g62pQMn4DE9xvzca6\n\n".
            "📍 *REGIONAL COBIJA*\n".
            "📌 Av. 9 de Febrero, frente al Regimiento Policial\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n".
            "📍 *REGIONAL COCHABAMBA*\n".
            "📌 Aeropuerto Internacional Jorge Wilstermann\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/iRpueTMRb495XB926\n\n".
            "📍 *REGIONAL SANTA CRUZ*\n".
            "📌 Aeropuerto El Trompillo – ingreso al aeropuerto, al fondo\n".
            "📌 Aeropuerto Internacional de Viru Viru\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/evjdQxK9kgyaxiKP9?g_st=aw\n".
            "📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n".
            "📍 *REGIONAL TRINIDAD*\n".
            "📌 Aeropuerto Teniente Jorge Henrich Arauz (TTD)\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/HBGy7sk2oE2egDaF6\n\n".
            "📍 *REGIONAL RIBERALTA*\n".
            "📌 Aeropuerto de Riberalta\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/XPiwwLSWxcLcKQjZ7\n\n".
            "📍 *REGIONAL GUAYARAMERÍN*\n".
            "📌 Aeropuerto de Guayaramerín\n".
            "🕐 Lunes a viernes: 08:00 – 16:00\n".
            "🕐 Sábados: 08:00 – 12:00\n".
            "📞 73748234 - 71593829\n".
            "📍 Ubicación: https://maps.app.goo.gl/4YQQsNRnmdrLTnTA8\n\n".
            '_Escribe *MENU* para volver al inicio o *BUSCAR* para consultar vuelos._'
        );

        $this->reply($conv, $msg);
    }

    protected function replyMenuInicial(Conversation $conv): void
    {
        $this->replyMenuPrincipal($conv);
    }

    protected function isDirectAgentRequest(string $text): bool
    {
        $keywords = ['asesor', 'agente', 'humano', 'vendedor', 'persona real', 'ejecutivo', 'operador', 'hablar con alguien', 'comprar'];
        foreach ($keywords as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function handleFaq(string $text): string
    {
        // 1. Equipaje
        if (str_contains($text, 'equipaje') || str_contains($text, 'maleta') || str_contains($text, 'kilo') || str_contains($text, 'peso') || str_contains($text, 'carry on')) {
            return BotMessage::getText(
                'politica_equipaje',
                "🧳 *POLÍTICA DE EQUIPAJE - TAMEP*\n\n".
                "• *Equipaje de Mano (Cabina):* 1 pieza de hasta 5 kg sin costo adicional.\n".
                "• *Equipaje Facturado (Bodega):* 1 pieza de hasta 20 kg incluida en tu boleto.\n".
                '• *Exceso de Equipaje:* Sujeto a disponibilidad de peso en aeronave con cobro por kg adicional en mostrador.'
            );
        }

        // 2. Formas de pago
        if (str_contains($text, 'pago') || str_contains($text, 'pagar') || str_contains($text, 'qr') || str_contains($text, 'transferencia') || str_contains($text, 'tarjeta') || str_contains($text, 'efectivo')) {
            return BotMessage::getText(
                'formas_pago',
                "💳 *FORMAS DE PAGO ACEPTADAS*\n\nPuedes abonar tus boletos y envíos de carga mediante:\n• *Código QR Simple:* Válido desde cualquier aplicación bancaria de Bolivia.\n• *Transferencia Bancaria Directa.*\n• *Tarjeta de Débito/Crédito y Efectivo:* En cualquiera de nuestras oficinas comerciales."
            );
        }

        // 3. Teléfonos y canales de contacto
        if (str_contains($text, 'telefono') || str_contains($text, 'celular') || str_contains($text, 'contacto') || str_contains($text, 'llamar') || str_contains($text, 'numero')) {
            return BotMessage::getText(
                'telefonos_contacto',
                "📞 *CANALES DE ATENCIÓN OFICIAL - TAMEP*\n\n• *Teléfonos de Atención:* 73748234 - 71593829\n• *Oficinas Presenciales:* Mostradores en aeropuertos y agencias regionales a nivel nacional."
            );
        }

        // 4. Mascotas
        if (str_contains($text, 'mascota') || str_contains($text, 'perro') || str_contains($text, 'gato')) {
            return BotMessage::getText(
                'transporte_mascotas',
                "🐾 *TRANSPORTE DE MASCOTAS (PETC)*\n\nSe permite el viaje de perros y gatos en cabina hasta 7 kg (incluyendo kennel o bolso blando impermeable con ventilación).\n\n📋 *Requisitos:*\n• Certificado de salud emitido por médico veterinario.\n• Carnet de vacunación antirrábica vigente."
            );
        }

        // 5. Niños y menores
        if (str_contains($text, 'menor') || str_contains($text, 'nino') || str_contains($text, 'bebe') || str_contains($text, 'infante')) {
            return BotMessage::getText(
                'viaje_menores',
                "👶 *VIAJE CON MENORES DE EDAD*\n\n• *Infantes (0 a 23 meses):* Pagan tarifa reducida y viajan en brazos de un adulto responsable.\n• *Niños (2 a 11 años):* Cuentan con tarifa especial de menor con asiento individual.\n• *Requisitos:* Cédula de Identidad o Certificado de Nacimiento original. Si viaja solo o con uno de los padres, se requiere permiso emitido por la Defensoría de la Niñez."
            );
        }

        // 6. Cancelaciones y reembolsos
        if (str_contains($text, 'cancelar') || str_contains($text, 'reembolso') || str_contains($text, 'cambio de fecha')) {
            return BotMessage::getText(
                'cambios_reembolsos',
                "🔄 *POLÍTICA DE CAMBIOS Y REEMBOLSOS*\n\n• Los cambios de fecha están permitidos según la clase tarifaria adquirida (aplicando penalidad y diferencia tarifaria si corresponde).\n• Para gestionar una modificación o reembolso, escribe *ASESOR* para que un agente de ventas te asista personalmente."
            );
        }

        // 7. Precios / Tarifas generales
        if (str_contains($text, 'precio') || str_contains($text, 'cuanto cuesta') || str_contains($text, 'tarifa') || str_contains($text, 'costo')) {
            return BotMessage::getText(
                'consulta_tarifas',
                "✈️ *CONSULTA DE TARIFAS Y VUELOS*\n\nNuestras tarifas varían según la ruta y fecha de viaje. Puedes consultar itinerarios y disponibilidad en tiempo real seleccionando la opción *1 (Rutas y Vuelos)* de nuestro menú principal."
            );
        }

        return BotMessage::getText(
            'faq_fallback',
            'Gracias por tu mensaje. Para asistirte de forma inmediata, puedes elegir una de las opciones de nuestro menú principal o escribir *ASESOR* para hablar con nuestro equipo de atención.'
        );
    }

    public function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $unwanted = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'à' => 'a',
            'è' => 'e',
            'ì' => 'i',
            'ò' => 'o',
            'ù' => 'u',
            'â' => 'a',
            'ê' => 'e',
            'î' => 'i',
            'ô' => 'o',
            'û' => 'u',
        ];
        $text = strtr($text, $unwanted);
        $text = preg_replace('/[¿?¡!.,;:_()\-]/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
