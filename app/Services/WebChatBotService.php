<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebChatBotService
{
    /**
     * Catálogo de ciudades oficiales de TAMep
     */
    protected array $cities = [
        'VVI' => 'Santa Cruz',
        'LPB' => 'La Paz',
        'CBB' => 'Cochabamba',
        'CIJ' => 'Cobija',
        'RBQ' => 'Rurrenabaque',
        'GYA' => 'Guayaramerín',
        'RIB' => 'Riberalta',
        'TJA' => 'Tarija',
        'SRE' => 'Sucre',
        'TDD' => 'Trinidad',
    ];

    public function __construct(
        protected FlightMicroservice $flightService
    ) {}

    /**
     * Procesa los mensajes o botones enviados por el visitante desde la página web.
     */
    public function processIncomingMessage(
        Conversation $conversation,
        string $text,
        ?string $mediaUrl = null,
        ?string $mediaType = null,
        ?string $buttonId = null,
        ?string $buttonTitle = null
    ): void {
        $trimmed = trim($text);
        $norm = Str::lower($trimmed);

        // Si no se pasó explícitamente $buttonId pero el texto coincide con uno conocido
        if (empty($buttonId) && preg_match('/^(menu_\w+|btn_\w+|ver_itinerario|ruta_\w+|orig_\w+|dest_\w+|date_\w+|select_flight_\d+|flight_\d+|consultar_otra_fecha)$/i', $trimmed)) {
            $buttonId = $trimmed;
        }

        $savedMessage = ! empty($buttonTitle) ? $buttonTitle : ($trimmed ?: ($buttonId ?? ''));

        // 1. Guardar mensaje del usuario en la base de datos
        $chatMsg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $savedMessage,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        $conversation->update(['last_interaction_at' => now()]);

        // 2. Si la conversación ya está en manos de un asesor humano o en espera:
        if (in_array($conversation->status, ['human_live', 'waiting_agent'])) {
            // Si el cliente pide explícitamente volver al bot
            if (in_array($norm, ['menu', 'inicio', 'volver', 'bot', 'reiniciar', 'salir']) || $buttonId === 'menu_volver') {
                $conversation->update([
                    'status' => 'bot_active',
                    'current_state' => 'MENU_PRINCIPAL',
                    'last_interaction_at' => now(),
                ]);
                BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'returned_to_bot'));
                BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));
                $this->replyMenuPrincipal($conversation);

                return;
            }

            // Notificar al asesor en el CRM
            BroadcastHelper::safeBroadcast(new ChatMessageSent($chatMsg, $conversation->id, $conversation));

            try {
                app(WebPushService::class)->notifyNewMessage($conversation, $chatMsg);
            } catch (\Throwable $e) {
                Log::warning('[WebChatBotService] Error al enviar WebPush: '.$e->getMessage());
            }

            return;
        }

        // 3. Si la conversación está en bot_active:
        // Solicitud de hablar con un asesor
        if ($this->isAgentRequest($norm) || $buttonId === 'btn_asesor') {
            $this->handoffToAgent($conversation, 'Solicitud de hablar con asesor de ventas desde la web');

            return;
        }

        // Consulta de Itinerario
        if ($buttonId === 'ver_itinerario' || str_contains($norm, 'itinerario')) {
            $this->handleVerItinerario($conversation);

            return;
        }

        // Consulta de Rutas y Vuelos -> Paso 1: Seleccionar Origen
        if ($buttonId === 'menu_rutas' || str_contains($norm, 'vuelo') || str_contains($norm, 'ruta') || str_contains($norm, 'horario') || $trimmed === '1') {
            $this->handleSeleccionarOrigen($conversation);

            return;
        }

        // Paso 2: Selección de Origen (botón o texto)
        if (str_starts_with((string) $buttonId, 'orig_')) {
            $originCode = strtoupper(substr((string) $buttonId, 5));
            $this->handleSeleccionarDestino($conversation, $originCode);

            return;
        }

        if ($conversation->current_state === 'SELECCION_ORIGEN') {
            $matchedCode = $this->matchCityCode($norm);
            if ($matchedCode) {
                $this->handleSeleccionarDestino($conversation, $matchedCode);

                return;
            }
        }

        // Paso 3: Selección de Destino (botón o texto) -> Pide la fecha de viaje
        if (str_starts_with((string) $buttonId, 'dest_')) {
            $destCode = strtoupper(substr((string) $buttonId, 5));
            $this->handleSolicitarFecha($conversation, $destCode);

            return;
        }

        if ($conversation->current_state === 'SELECCION_DESTINO') {
            $matchedCode = $this->matchCityCode($norm);
            if ($matchedCode) {
                $this->handleSolicitarFecha($conversation, $matchedCode);

                return;
            }
        }

        // Volver a pedir fecha
        if ($buttonId === 'consultar_otra_fecha') {
            $session = $conversation->session_data ?? [];
            $destCode = $session['destination'] ?? 'LPB';
            $this->handleSolicitarFecha($conversation, $destCode);

            return;
        }

        // Paso 4: Selección de Fecha (botón o texto escrito) -> Consulta la API de Transclick
        if (str_starts_with((string) $buttonId, 'date_')) {
            $dateStr = substr((string) $buttonId, 5);
            $this->handleConsultarVuelos($conversation, $dateStr);

            return;
        }

        if ($conversation->current_state === 'SELECCION_FECHA') {
            $parsedDate = $this->parseNaturalDate($trimmed);
            if ($parsedDate) {
                $this->handleConsultarVuelos($conversation, $parsedDate);

                return;
            }
        }

        // Paso 5: Selección de Vuelo de la lista (select_flight_X o flight_X)
        if (preg_match('/^(select_flight_|flight_)(\d+)$/i', (string) $buttonId, $m)) {
            $flightIndex = (int) $m[2];
            $this->handleSeleccionarVuelo($conversation, $flightIndex);

            return;
        }

        // Información de Carga
        if ($buttonId === 'menu_carga' || str_contains($norm, 'carga') || str_contains($norm, 'encomienda') || $trimmed === '2') {
            $this->handleInfoCarga($conversation);

            return;
        }

        // Información de Oficinas
        if ($buttonId === 'menu_oficinas' || str_contains($norm, 'oficina') || str_contains($norm, 'direccion') || $trimmed === '3') {
            $this->handleOficinas($conversation);

            return;
        }

        // Por defecto / Saludo / Menú principal
        $this->replyMenuPrincipal($conversation);
    }

    /**
     * Menú Principal del Web Chat con botones interactivos limpios y directos.
     */
    public function replyMenuPrincipal(Conversation $conv): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $name = ! empty($conv->contact_name) ? " {$conv->contact_name}" : '';
        $text = "¡Hola{$name}! 👋 Te damos una cordial bienvenida a *TAMep* ✈️\n\n".
                'Estamos aquí para ayudarte a planificar tu viaje o gestionar tus envíos de manera rápida y segura. ¿En qué podemos colaborarte hoy?';

        $buttons = [
            ['id' => 'menu_rutas', 'title' => '✈️ Consultar Rutas y Vuelos'],
            ['id' => 'btn_asesor', 'title' => '👤 Hablar con un Asesor de Ventas'],
            ['id' => 'ver_itinerario', 'title' => '📅 Ver Itinerario Oficial'],
            ['id' => 'menu_carga', 'title' => '📦 Información sobre Carga'],
            ['id' => 'menu_oficinas', 'title' => '🏢 Nuestras Oficinas y Horarios'],
        ];

        $this->replyWithButtons($conv, $text, $buttons);
    }

    /**
     * Paso 1: Seleccionar ciudad de Origen.
     */
    public function handleSeleccionarOrigen(Conversation $conv): void
    {
        $conv->update(['current_state' => 'SELECCION_ORIGEN']);

        $text = "🛫 *CONSULTA DE VUELOS*\n\n".
                '¿Desde qué ciudad deseas partir? Selecciona tu ciudad de *ORIGEN*:';

        $buttons = [
            ['id' => 'orig_VVI', 'title' => '📍 Santa Cruz (Viru Viru)'],
            ['id' => 'orig_LPB', 'title' => '📍 La Paz (El Alto)'],
            ['id' => 'orig_CBB', 'title' => '📍 Cochabamba'],
            ['id' => 'orig_CIJ', 'title' => '📍 Cobija'],
            ['id' => 'orig_RBQ', 'title' => '📍 Rurrenabaque'],
            ['id' => 'orig_GYA', 'title' => '📍 Guayaramerín'],
            ['id' => 'orig_RIB', 'title' => '📍 Riberalta'],
            ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
        ];

        $this->replyWithButtons($conv, $text, $buttons);
    }

    /**
     * Paso 2: Seleccionar ciudad de Destino según el origen elegido.
     */
    public function handleSeleccionarDestino(Conversation $conv, string $originCode): void
    {
        $session = $conv->session_data ?? [];
        $originCity = $this->cities[$originCode] ?? $originCode;
        $session['origin'] = $originCode;
        $session['origin_city'] = $originCity;

        $conv->update([
            'current_state' => 'SELECCION_DESTINO',
            'session_data' => $session,
        ]);

        $text = "🛬 *SELECCIONA TU DESTINO*\n\n".
                "🛫 Origen: *{$originCity} ({$originCode})*\n\n".
                '¿A qué ciudad deseas viajar?';

        $destinations = $this->getAvailableDestinations($originCode);
        $buttons = [];

        foreach ($destinations as $d) {
            $buttons[] = [
                'id' => 'dest_'.$d['code'],
                'title' => "📍 {$d['city']} ({$d['code']})",
            ];
        }

        $buttons[] = ['id' => 'menu_rutas', 'title' => '🔄 Cambiar Origen'];
        $buttons[] = ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'];

        $this->replyWithButtons($conv, $text, $buttons);
    }

    /**
     * Paso 3: Solicitar el Día / Fecha de viaje para consultar la API de Transclick.
     */
    public function handleSolicitarFecha(Conversation $conv, string $destCode): void
    {
        $session = $conv->session_data ?? [];
        $originCode = $session['origin'] ?? 'VVI';
        $originCity = $session['origin_city'] ?? ($this->cities[$originCode] ?? $originCode);
        $destCity = $this->cities[$destCode] ?? $destCode;

        $session['destination'] = $destCode;
        $session['destination_city'] = $destCity;

        $conv->update([
            'current_state' => 'SELECCION_FECHA',
            'session_data' => $session,
        ]);

        $text = "📅 *FECHA DE TU VIAJE*\n\n".
                "Ruta: *{$originCity} ({$originCode}) ➔ {$destCity} ({$destCode})* ✈️\n\n".
                'Selecciona una fecha sugerida para consultar vuelos en tiempo real, o escribe tu fecha (ej: *2026-11-02* o *lunes*):';

        $dateButtons = $this->getSuggestedDateButtons();
        $dateButtons[] = ['id' => 'menu_rutas', 'title' => '🔄 Cambiar Ruta'];
        $dateButtons[] = ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'];

        $this->replyWithButtons($conv, $text, $dateButtons);
    }

    /**
     * Paso 4: Consulta en tiempo real con la API de Transclick y muestra los vuelos.
     */
    public function handleConsultarVuelos(Conversation $conv, string $flightDate): void
    {
        $session = $conv->session_data ?? [];
        $origin = $session['origin'] ?? 'VVI';
        $destination = $session['destination'] ?? 'LPB';
        $originCity = $session['origin_city'] ?? ($this->cities[$origin] ?? $origin);
        $destCity = $session['destination_city'] ?? ($this->cities[$destination] ?? $destination);

        $session['flight_date'] = $flightDate;

        // Llamada a la API de Transclick / Airclick
        $flights = [];
        try {
            $flights = $this->flightService->searchFlights(
                origin: $origin,
                destination: $destination,
                date: $flightDate
            );
        } catch (\Throwable $e) {
            Log::error('[WebChatBotService] Error consultando vuelos en Transclick API: '.$e->getMessage());
        }

        $formattedDate = $this->formatDateSpanish($flightDate);

        if (! empty($flights)) {
            $session['available_flights'] = $flights;
            $conv->update([
                'current_state' => 'VUELOS_MOSTRADOS',
                'session_data' => $session,
            ]);

            $text = "✈️ *VUELOS DISPONIBLES EN TIEMPO REAL*\n\n".
                    "🛫 *{$originCity} ({$origin}) ➔ {$destCity} ({$destination})* 🛬\n".
                    "📅 Fecha: *{$formattedDate}*\n\n".
                    'Encontramos *'.count($flights)."* vuelo(s) programados:\n\n";

            foreach ($flights as $f) {
                $num = $f['flight_number'] ?? 'TM';
                $dep = $f['departure_time'] ?? '08:00';
                $arr = $f['arrival_time'] ?? '09:00';
                $seats = $f['seats'] ?? 0;
                $price = $f['price'] ?? 0;
                $curr = $f['currency'] ?? 'BOB';

                $text .= "• *Vuelo {$num}:* Salida {$dep} | Llegada {$arr}\n".
                         "  💺 Asientos disp.: {$seats} | 💰 *{$price} {$curr}*\n\n";
            }

            $text .= 'Selecciona el vuelo que deseas reservar o cotiza directamente con un asesor:';

            $buttons = [];
            foreach ($flights as $idx => $f) {
                $num = $f['flight_number'] ?? 'TM';
                $price = $f['price'] ?? 0;
                $buttons[] = [
                    'id' => 'select_flight_'.$idx,
                    'title' => "🎫 Elegir {$num} ({$price} BOB)",
                ];
            }

            $buttons[] = ['id' => 'btn_asesor', 'title' => '👤 Hablar con un Asesor'];
            $buttons[] = ['id' => 'consultar_otra_fecha', 'title' => '📅 Probar Otra Fecha'];
            $buttons[] = ['id' => 'menu_rutas', 'title' => '🔄 Cambiar Ruta'];
            $buttons[] = ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'];

            $this->replyWithButtons($conv, $text, $buttons);
        } else {
            // No se encontraron vuelos en esa fecha específica
            $conv->update([
                'current_state' => 'SIN_VUELOS_FECHA',
                'session_data' => $session,
            ]);

            $text = "✈️ *DISPONIBILIDAD DE VUELOS*\n\n".
                    "🛫 *{$originCity} ({$origin}) ➔ {$destCity} ({$destination})*\n".
                    "📅 Fecha consultada: *{$formattedDate}*\n\n".
                    "No encontramos vuelos comerciales en esta fecha exacta.\n\n".
                    'TAMep cuenta con frecuencias en días programados de la semana. ¿Deseas ver el itinerario oficial o consultar fechas cercanas con un asesor?';

            $buttons = [
                ['id' => 'btn_asesor', 'title' => '👤 Consultar Fechas con Asesor'],
                ['id' => 'ver_itinerario', 'title' => '📅 Ver Itinerario Oficial'],
                ['id' => 'consultar_otra_fecha', 'title' => '🔄 Probar Otra Fecha'],
                ['id' => 'menu_rutas', 'title' => '🔄 Cambiar Ruta'],
                ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
            ];

            $this->replyWithButtons($conv, $text, $buttons);
        }
    }

    /**
     * Paso 5: Selección de un vuelo específico por parte del pasajero.
     */
    public function handleSeleccionarVuelo(Conversation $conv, int $index): void
    {
        $session = $conv->session_data ?? [];
        $flights = $session['available_flights'] ?? [];
        $selected = $flights[$index] ?? null;

        if (! $selected && ! empty($flights)) {
            $selected = $flights[0];
        }

        if ($selected) {
            $session['selected_flight'] = $selected;
            $conv->update(['session_data' => $session]);

            $flightNum = $selected['flight_number'] ?? 'TAMep';
            $price = $selected['price'] ?? 0;
            $currency = $selected['currency'] ?? 'BOB';
            $depTime = $selected['departure_time'] ?? '';
            $arrTime = $selected['arrival_time'] ?? '';
            $origin = $selected['origin'] ?? ($session['origin'] ?? 'VVI');
            $dest = $selected['destination'] ?? ($session['destination'] ?? 'LPB');
            $originCity = $selected['origin_city'] ?? ($this->cities[$origin] ?? $origin);
            $destCity = $selected['destination_city'] ?? ($this->cities[$dest] ?? $dest);
            $date = $selected['departure_date'] ?? ($session['flight_date'] ?? now()->format('Y-m-d'));

            $reason = "Reserva Vuelo {$flightNum} ({$originCity} ➔ {$destCity}) el {$date} a las {$depTime}. Tarifa: {$price} {$currency}";

            // Transferir al CRM como lead con vuelo preseleccionado
            $this->handoffToAgent($conv, $reason);

            return;
        }

        $this->handoffToAgent($conv, 'Interés en compra de pasaje');
    }

    /**
     * Genera botones de fechas sugeridas (Hoy, Mañana y próximos días)
     */
    protected function getSuggestedDateButtons(): array
    {
        $days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $months = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $buttons = [];

        // Próximos 4 días consecutivos
        for ($i = 0; $i < 4; $i++) {
            $d = now()->addDays($i);
            $ymd = $d->format('Y-m-d');
            $label = $i === 0 ? 'Hoy' : ($i === 1 ? 'Mañana' : $days[$d->dayOfWeek]);
            $title = "📅 {$label} ({$d->day} {$months[$d->month]})";
            $buttons[] = [
                'id' => 'date_'.$ymd,
                'title' => $title,
            ];
        }

        // Una fecha sugerida a 5 días
        $nextWeek = now()->addDays(5);
        $buttons[] = [
            'id' => 'date_'.$nextWeek->format('Y-m-d'),
            'title' => "📅 {$days[$nextWeek->dayOfWeek]} ({$nextWeek->day} {$months[$nextWeek->month]})",
        ];

        return $buttons;
    }

    /**
     * Parsea fechas en lenguaje natural o formatos numéricos
     */
    protected function parseNaturalDate(string $text): ?string
    {
        $norm = mb_strtolower(trim($text));

        if (str_contains($norm, 'hoy')) {
            return now()->format('Y-m-d');
        }
        if (str_contains($norm, 'mañana') || str_contains($norm, 'manana')) {
            return now()->addDay()->format('Y-m-d');
        }
        if (str_contains($norm, 'pasado mañana') || str_contains($norm, 'pasado manana')) {
            return now()->addDays(2)->format('Y-m-d');
        }

        $dayMap = [
            'lunes' => Carbon::MONDAY,
            'martes' => Carbon::TUESDAY,
            'miercoles' => Carbon::WEDNESDAY,
            'miércoles' => Carbon::WEDNESDAY,
            'jueves' => Carbon::THURSDAY,
            'viernes' => Carbon::FRIDAY,
            'sabado' => Carbon::SATURDAY,
            'sábado' => Carbon::SATURDAY,
            'domingo' => Carbon::SUNDAY,
        ];

        foreach ($dayMap as $name => $dayOfWeek) {
            if (str_contains($norm, $name)) {
                $target = now()->next($dayOfWeek);

                return $target->format('Y-m-d');
            }
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $norm, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $norm, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        try {
            $parsed = Carbon::parse($text);
            if ($parsed && $parsed->year >= 2025) {
                return $parsed->format('Y-m-d');
            }
        } catch (\Throwable) {
            // No parseable
        }

        return null;
    }

    /**
     * Formatea fechas amigables en español
     */
    protected function formatDateSpanish(string $dateStr): string
    {
        try {
            $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $c = Carbon::parse($dateStr);
            $dayName = $days[$c->dayOfWeek];
            $monthName = $months[$c->month];

            return "{$dayName} {$c->day} de {$monthName} {$c->year}";
        } catch (\Throwable) {
            return $dateStr;
        }
    }

    /**
     * Catálogo de destinos disponibles por cada origen oficial
     */
    protected function getAvailableDestinations(string $originCode): array
    {
        $destinationsByOrigin = [
            'VVI' => [
                ['code' => 'LPB', 'city' => 'La Paz'],
                ['code' => 'CBB', 'city' => 'Cochabamba'],
                ['code' => 'CIJ', 'city' => 'Cobija'],
                ['code' => 'RIB', 'city' => 'Riberalta'],
                ['code' => 'GYA', 'city' => 'Guayaramerín'],
            ],
            'LPB' => [
                ['code' => 'VVI', 'city' => 'Santa Cruz'],
                ['code' => 'CBB', 'city' => 'Cochabamba'],
                ['code' => 'RBQ', 'city' => 'Rurrenabaque'],
                ['code' => 'CIJ', 'city' => 'Cobija'],
            ],
            'CBB' => [
                ['code' => 'VVI', 'city' => 'Santa Cruz'],
                ['code' => 'LPB', 'city' => 'La Paz'],
            ],
            'CIJ' => [
                ['code' => 'VVI', 'city' => 'Santa Cruz'],
                ['code' => 'LPB', 'city' => 'La Paz'],
            ],
            'RBQ' => [
                ['code' => 'LPB', 'city' => 'La Paz'],
            ],
            'GYA' => [
                ['code' => 'RIB', 'city' => 'Riberalta'],
                ['code' => 'VVI', 'city' => 'Santa Cruz'],
            ],
            'RIB' => [
                ['code' => 'GYA', 'city' => 'Guayaramerín'],
                ['code' => 'VVI', 'city' => 'Santa Cruz'],
            ],
        ];

        return $destinationsByOrigin[$originCode] ?? [
            ['code' => 'LPB', 'city' => 'La Paz'],
            ['code' => 'VVI', 'city' => 'Santa Cruz'],
            ['code' => 'CBB', 'city' => 'Cochabamba'],
        ];
    }

    /**
     * Reconoce nombres comunes de ciudades bolivianas ingresadas en texto
     */
    protected function matchCityCode(string $norm): ?string
    {
        $cityCodes = [
            'santa cruz' => 'VVI',
            'viru viru' => 'VVI',
            'vvi' => 'VVI',
            'la paz' => 'LPB',
            'el alto' => 'LPB',
            'lpb' => 'LPB',
            'cochabamba' => 'CBB',
            'cocha' => 'CBB',
            'cbb' => 'CBB',
            'cobija' => 'CIJ',
            'cij' => 'CIJ',
            'rurrenabaque' => 'RBQ',
            'rurre' => 'RBQ',
            'rbq' => 'RBQ',
            'guayaramerin' => 'GYA',
            'guayaramerín' => 'GYA',
            'guayara' => 'GYA',
            'gya' => 'GYA',
            'riberalta' => 'RIB',
            'rib' => 'RIB',
            'tarija' => 'TJA',
            'tja' => 'TJA',
            'sucre' => 'SRE',
            'sre' => 'SRE',
            'trinidad' => 'TDD',
            'tdd' => 'TDD',
        ];

        foreach ($cityCodes as $alias => $code) {
            if (str_contains($norm, $alias)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Muestra la imagen e información del itinerario oficial.
     */
    protected function handleVerItinerario(Conversation $conv): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $itinerary = Setting::get('itinerary', []);

        $imageUrl = ! empty($itinerary['image_url'])
            ? (string) $itinerary['image_url']
            : (string) config('services.airline.itinerario_image_url', '');

        if (empty($imageUrl)) {
            $localJpg = public_path('images/itinerario.jpg');
            $localPng = public_path('images/itinerario.png');
            $localWebp = public_path('images/itinerario.webp');

            if (file_exists($localJpg)) {
                $imageUrl = url('images/itinerario.jpg');
            } elseif (file_exists($localPng)) {
                $imageUrl = url('images/itinerario.png');
            } elseif (file_exists($localWebp)) {
                $imageUrl = url('images/itinerario.webp');
            }
        }

        $caption = "📅 *ITINERARIO OFICIAL DE VUELOS TAMEP*\n\n".
                   "Consulta las frecuencias de vuelos programados para este mes.\n\n".
                   'Puedes solicitar tu pasaje de inmediato comunicándote con un asesor de ventas:';

        $buttons = [
            ['id' => 'btn_asesor', 'title' => '👤 Contactar Asesor de Ventas'],
            ['id' => 'menu_rutas', 'title' => '✈️ Consultar Rutas y Vuelos'],
            ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
        ];

        $this->replyWithMedia($conv, $caption, $imageUrl, ! empty($imageUrl) ? 'image' : null, $buttons);
    }

    /**
     * Información oficial sobre carga y encomiendas.
     */
    protected function handleInfoCarga(Conversation $conv): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $text = "📦 *SERVICIOS DE CARGA AÉREA Y ENCOMIENDAS - TAMEP*\n\n".
                "Transportamos tu carga a todos nuestros destinos nacionales de forma rápida y segura.\n\n".
                "• *Servicios:* Carga general, paquetería, encomiendas y mercancías especiales.\n".
                "• *Seguimiento:* Mediante el portal web oficial.\n".
                "• *Línea directa Carga:* 73748234\n\n".
                '¿Deseas cotizar un envío con un ejecutivo de atención?';

        $buttons = [
            ['id' => 'btn_asesor', 'title' => '👤 Hablar con Asesor de Carga'],
            ['id' => 'menu_rutas', 'title' => '✈️ Ver Vuelos de Pasajeros'],
            ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
        ];

        $this->replyWithButtons($conv, $text, $buttons);
    }

    /**
     * Oficinas y horarios de TAMep.
     */
    protected function handleOficinas(Conversation $conv): void
    {
        $conv->update(['current_state' => 'MENU_PRINCIPAL']);

        $text = "🏢 *OFICINAS OFICIALES DE TAMEP*\n\n".
                "📍 *La Paz:* Av. Montes #738 esq. Boquerón\n".
                "📍 *El Alto:* Aeropuerto Internacional de El Alto (Hall Principal)\n".
                "📍 *Santa Cruz:* Aeropuerto El Trompillo & Aeropuerto Viru Viru\n".
                "📍 *Cochabamba:* Aeropuerto Jorge Wilstermann\n".
                "📍 *Cobija:* Aeropuerto Capitán Aníbal Arab\n\n".
                '⏰ *Horario de Atención:* Lunes a Viernes 08:00 - 18:30 | Sábados 08:30 - 13:00';

        $buttons = [
            ['id' => 'btn_asesor', 'title' => '👤 Contactar Asesor de Ventas'],
            ['id' => 'menu_rutas', 'title' => '✈️ Consultar Rutas'],
            ['id' => 'menu_volver', 'title' => '🏠 Menú Principal'],
        ];

        $this->replyWithButtons($conv, $text, $buttons);
    }

    /**
     * Transfiere la conversación al panel de agentes de ventas del CRM.
     */
    public function handoffToAgent(Conversation $conv, string $reason): void
    {
        $session = $conv->session_data ?? [];

        // 1. Cambiar estado a espera de agente
        $conv->update([
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
            'last_interaction_at' => now(),
        ]);

        // Notificar en tiempo real cambio de estado a la bandeja del CRM
        BroadcastHelper::safeBroadcast(new ConversationUpdated($conv, 'waiting_agent'));

        // Alerta Web Push a los agentes de ventas
        try {
            app(WebPushService::class)->notifyWaitingPassenger($conv, "Web Chat: {$reason}");
        } catch (\Throwable $e) {
            Log::warning('[WebChatBotService] Error al enviar push de handoff: '.$e->getMessage());
        }

        // 2. Crear lead en el CRM para la gestión comercial
        $selectedFlight = $session['selected_flight'] ?? null;
        $origin = $session['origin'] ?? ($selectedFlight['origin'] ?? 'VVI');
        $destination = $session['destination'] ?? ($selectedFlight['destination'] ?? 'LPB');
        $flightDate = $session['flight_date'] ?? ($selectedFlight['departure_date'] ?? now()->format('Y-m-d'));
        $flightNumber = $selectedFlight['flight_number'] ?? null;
        $price = $selectedFlight['price'] ?? null;

        $originCity = $session['origin_city'] ?? ($this->cities[$origin] ?? $origin);
        $destCity = $session['destination_city'] ?? ($this->cities[$destination] ?? $destination);
        $routeStr = ! empty($session['destination']) ? "{$originCity} ({$origin}) ➔ {$destCity} ({$destination})" : '';

        $leadData = [
            'conversation_id' => $conv->id,
            'channel' => 'web',
            'phone' => $session['contact_phone'] ?? $conv->phone,
            'type' => 'compra_pasaje',
            'passenger_name' => $conv->contact_name ?: ($session['passenger_name'] ?? 'Visitante Web'),
            'origin' => $origin,
            'destination' => $destination,
            'flight_date' => $flightDate,
            'flight_number' => $flightNumber,
            'total_price' => $price,
            'status' => 'nuevo',
            'details' => ! empty($routeStr)
                ? "{$reason} | Ruta: {$routeStr}"
                : $reason,
            'bot_payload' => $session,
        ];

        try {
            Lead::create($leadData);
        } catch (\Throwable $e) {
            Log::warning('[LeadCreate Web handoff] '.$e->getMessage());
        }

        // 3. Notificar al pasajero en el chat
        $flightDetailsText = '';
        if ($selectedFlight) {
            $flightDetailsText = "\n• Vuelo: *{$flightNumber}*\n• Tarifa: *{$price} BOB*\n• Fecha: *{$flightDate}*\n";
        } elseif (! empty($routeStr)) {
            $flightDetailsText = "\n• Ruta: *{$routeStr}*\n• Fecha deseada: *{$flightDate}*\n";
        }

        $replyText = "👤 *CONECTANDO CON UN ASESOR DE VENTAS*\n\n".
                     "¡Hemos transferido tu solicitud a nuestro equipo comercial!{$flightDetailsText}\n".
                     'Un asesor te atenderá en unos momentos para confirmar tu reserva y emitir tu pasaje.';

        $buttons = [
            ['id' => 'menu_volver', 'title' => '🏠 Volver al Menú Principal'],
        ];

        $this->replyWithButtons($conv, $replyText, $buttons);
    }

    /**
     * Envía mensaje con botones interactivos y transmite en vivo al CRM.
     */
    protected function replyWithButtons(Conversation $conv, string $message, array $buttons): void
    {
        $botMsg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $message,
            'buttons' => $buttons,
        ]);

        BroadcastHelper::safeBroadcast(new ChatMessageSent($botMsg, $conv->id, $conv));
    }

    /**
     * Envía mensaje con multimedia (imágenes) y botones.
     */
    protected function replyWithMedia(Conversation $conv, string $message, ?string $mediaUrl, ?string $mediaType, array $buttons = []): void
    {
        $botMsg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $message,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'buttons' => $buttons,
        ]);

        BroadcastHelper::safeBroadcast(new ChatMessageSent($botMsg, $conv->id, $conv));
    }

    /**
     * Detecta si el texto del visitante solicita un asesor humano.
     */
    protected function isAgentRequest(string $norm): bool
    {
        $keywords = [
            'asesor', 'agente', 'humano', 'operador', 'persona', 'ventas', 'ejecutivo',
            'quiero hablar', 'atencion', 'hablar con alguien', 'comunicarme con',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($norm, $kw)) {
                return true;
            }
        }

        return false;
    }
}
