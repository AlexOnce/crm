<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Métricas y KPIs consolidados para el panel ejecutivo
     */
    public function metrics(): JsonResponse
    {
        $totalLeads = Lead::count();
        $totalTickets = (int) Lead::whereIn('status', ['emitido', 'comprado'])
            ->sum(DB::raw('COALESCE(passengers_count, 1)'));
        $totalRevenue = (float) Lead::whereIn('status', ['emitido', 'comprado'])->sum('total_price');

        $startOfToday = now('America/La_Paz')->startOfDay()->setTimezone('UTC');
        $endOfToday = now('America/La_Paz')->endOfDay()->setTimezone('UTC');

        $ticketsToday = (int) Lead::whereIn('status', ['emitido', 'comprado'])
            ->whereBetween('updated_at', [$startOfToday, $endOfToday])
            ->sum(DB::raw('COALESCE(passengers_count, 1)'));
        $revenueToday = (float) Lead::whereIn('status', ['emitido', 'comprado'])
            ->whereBetween('updated_at', [$startOfToday, $endOfToday])
            ->sum('total_price');

        $conversionRate = $totalLeads > 0
            ? round(($totalTickets / $totalLeads) * 100, 1)
            : 0;

        $conversations = [
            'waiting_agent' => Conversation::where('status', 'waiting_agent')->count(),
            'human_live' => Conversation::where('status', 'human_live')->count(),
            'bot_active' => Conversation::where('status', 'bot_active')->count(),
            'total' => Conversation::count(),
        ];

        // Rendimiento por asesor: Suma real de boletos (passengers_count) vendidos por cada asesor
        $agents = User::withCount('assignedConversations')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at'])
            ->map(function ($agent) {
                $count = (int) Lead::whereIn('status', ['emitido', 'comprado'])
                    ->whereHas('conversation', function ($q) use ($agent) {
                        $q->where('assigned_user_id', $agent->id);
                    })
                    ->sum(DB::raw('COALESCE(passengers_count, 1)'));

                $agent->setAttribute('tickets_count', $count);

                return $agent;
            })
            ->sortByDesc('tickets_count')
            ->values();

        // Últimos 6 boletos emitidos recientemente
        $recentTickets = Lead::whereIn('status', ['emitido', 'comprado'])
            ->with(['conversation.assignedUser:id,name,email'])
            ->latest('updated_at')
            ->take(6)
            ->get([
                'id', 'conversation_id', 'passenger_name', 'passport_number',
                'flight_number', 'origin', 'destination', 'flight_date',
                'passengers_count', 'total_price', 'booking_reference', 'ticket_pdf_path', 'updated_at',
            ]);

        $counts = $this->buildTicketCounts();

        return response()->json([
            'success' => true,
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'revenue_today' => $revenueToday,
                'total_tickets' => $totalTickets,
                'tickets_today' => $ticketsToday,
                'total_leads' => $totalLeads,
                'conversion_rate' => $conversionRate,
            ],
            'conversations' => $conversations,
            'agents' => $agents,
            'recent_tickets' => $recentTickets,
            'counts' => $counts,
        ]);
    }

    /**
     * Listado completo y filtrable de todos los pasajeros y boletos registrados
     */
    public function tickets(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');

        $query = Lead::with(['conversation.assignedUser:id,name,email'])
            ->latest('id');

        if (! empty($status) && $status !== 'all') {
            if ($status === 'contactado' || $status === 'en_proceso') {
                $query->where(function ($q) {
                    $q->whereIn('status', ['contactado', 'en_emision', 'en_proceso'])
                        ->orWhere(function ($sub) {
                            $sub->where('status', 'nuevo')
                                ->whereHas('conversation', function ($cq) {
                                    $cq->where('status', 'human_live')
                                       ->orWhereNotNull('assigned_user_id');
                                });
                        });
                });
            } elseif ($status === 'nuevo') {
                $query->where('status', 'nuevo')
                    ->whereDoesntHave('conversation', function ($cq) {
                        $cq->where('status', 'human_live')
                           ->orWhereNotNull('assigned_user_id');
                    });
            } elseif ($status === 'emitido') {
                $query->whereIn('status', ['emitido', 'comprado']);
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('passenger_name', 'like', "%{$search}%")
                    ->orWhere('passport_number', 'like', "%{$search}%")
                    ->orWhere('flight_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(25);

        $tickets->getCollection()->transform(function ($lead) {
            $lead->ticket_view_url = in_array($lead->status, ['emitido', 'comprado']) ? url("/tickets/view/{$lead->id}") : null;

            return $lead;
        });

        $counts = $this->buildTicketCounts();

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'counts' => $counts,
        ]);
    }

    /**
     * Conteo unificado de tickets por estado para tabs y badges
     */
    private function buildTicketCounts(): array
    {
        $inProcessCondition = function ($q) {
            $q->whereIn('status', ['contactado', 'en_emision', 'en_proceso'])
                ->orWhere(function ($sub) {
                    $sub->where('status', 'nuevo')
                        ->whereHas('conversation', function ($cq) {
                            $cq->where('status', 'human_live')
                               ->orWhereNotNull('assigned_user_id');
                        });
                });
        };

        return [
            'total' => Lead::count(),
            'nuevo' => Lead::where('status', 'nuevo')
                ->whereDoesntHave('conversation', function ($cq) {
                    $cq->where('status', 'human_live')
                       ->orWhereNotNull('assigned_user_id');
                })->count(),
            'contactado' => Lead::where($inProcessCondition)->count(),
            'emitido' => Lead::whereIn('status', ['emitido', 'comprado'])->count(),
            'emitido_pax' => (int) Lead::whereIn('status', ['emitido', 'comprado'])->sum(DB::raw('COALESCE(passengers_count, 1)')),
            'emitido_reservas' => Lead::whereIn('status', ['emitido', 'comprado'])->count(),
        ];
    }

    /**
     * Registra manualmente la información de un pasajero / boleto desde el panel de administración
     */
    public function storePassenger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'passenger_name' => 'required|string|max:150',
            'passport_number' => 'required|string|max:50',
            'phone' => 'required|string|max:30',
            'origin' => 'required|string|max:10',
            'destination' => 'required|string|max:10',
            'flight_date' => 'required|date',
            'flight_number' => 'nullable|string|max:20',
            'passengers_count' => 'nullable|integer|min:1|max:20',
            'total_price' => 'nullable|numeric|min:0',
            'booking_reference' => 'nullable|string|max:30',
            'status' => 'nullable|in:nuevo,contactado,en_emision,emitido',
            'details' => 'nullable|string|max:1000',
        ]);

        $cleanPhone = preg_replace('/[^\d]/', '', $validated['phone']);

        // Buscar o crear conversación para este teléfono
        $conversation = Conversation::firstOrCreate(
            ['phone' => $cleanPhone],
            [
                'contact_name' => $validated['passenger_name'],
                'status' => 'waiting_agent',
                'current_state' => 'MENU_PRINCIPAL',
                'last_interaction_at' => now(),
            ]
        );

        $status = $validated['status'] ?? 'nuevo';

        $lead = Lead::create([
            'conversation_id' => $conversation->id,
            'phone' => $cleanPhone,
            'type' => 'compra_pasaje',
            'passenger_name' => $validated['passenger_name'],
            'passport_number' => $validated['passport_number'],
            'origin' => strtoupper($validated['origin']),
            'destination' => strtoupper($validated['destination']),
            'flight_date' => $validated['flight_date'],
            'flight_number' => $validated['flight_number'] ?: 'Por definir',
            'passengers_count' => (int) ($validated['passengers_count'] ?? 1),
            'total_price' => (float) ($validated['total_price'] ?? 0),
            'booking_reference' => $validated['booking_reference'] ?: null,
            'status' => $status,
            'details' => $validated['details'] ?? 'Registro manual desde panel de administración',
            'bot_payload' => [
                'manual_admin' => true,
                'created_by' => auth()->user()?->name ?? 'Admin',
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pasajero registrado exitosamente.',
            'lead' => $lead->load('conversation.assignedUser'),
        ]);
    }

    /**
     * Actualiza el estado o PNR de un boleto / pasajero
     */
    public function updatePassengerStatus(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:nuevo,contactado,en_emision,emitido,cancelado,en_proceso',
            'booking_reference' => 'nullable|string|max:30',
        ]);

        $lead->status = $validated['status'];
        if (array_key_exists('booking_reference', $validated)) {
            $lead->booking_reference = $validated['booking_reference'];
        }
        $lead->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del pasajero actualizado correctamente.',
            'lead' => $lead->load('conversation.assignedUser'),
        ]);
    }

    /**
     * Monitoreo en vivo de todas las conversaciones activas para supervisión
     */
    public function conversationsMonitor(Request $request): JsonResponse
    {
        $status = $request->query('status'); // 'waiting_agent', 'human_live', 'all'

        $query = Conversation::with([
            'assignedUser:id,name,email',
            'latestMessage',
            'latestLead',
        ])
            ->orderByDesc('last_interaction_at');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['waiting_agent', 'human_live']);
        }

        $conversations = $query->paginate(30);

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Diagnóstico y salud de Meta Cloud API
     */
    public function metaStatus(MetaWhatsAppService $service): JsonResponse
    {
        $phoneDetails = $service->getPhoneNumberDetails();
        $wabaDetails = $service->getWabaDetails();

        return response()->json([
            'success' => true,
            'phone' => $phoneDetails,
            'waba' => $wabaDetails,
            'phone_id' => config('services.meta.phone_number_id'),
            'waba_id' => config('services.meta.waba_id'),
            'verify_token' => config('services.meta.verify_token'),
        ]);
    }

    /**
     * Obtiene la configuración actual del itinerario de vuelos
     */
    public function getItinerary(): JsonResponse
    {
        $itinerary = Setting::get('itinerary', []);

        $defaultWebUrl = config('services.airline.itinerario_web_url', 'https://tamep.bo/itinerarios');
        $defaultImageUrl = config('services.airline.itinerario_image_url', '');

        $localJpg = public_path('images/itinerario.jpg');
        $localPng = public_path('images/itinerario.png');
        $localWebp = public_path('images/itinerario.webp');

        $effectiveImageUrl = $itinerary['image_url'] ?? null;
        $hasLocalImage = false;

        if (! empty($itinerary['local_path']) && file_exists($itinerary['local_path'])) {
            $hasLocalImage = true;
        } elseif (file_exists($localJpg)) {
            $effectiveImageUrl = $effectiveImageUrl ?: url('images/itinerario.jpg');
            $hasLocalImage = true;
        } elseif (file_exists($localPng)) {
            $effectiveImageUrl = $effectiveImageUrl ?: url('images/itinerario.png');
            $hasLocalImage = true;
        } elseif (file_exists($localWebp)) {
            $effectiveImageUrl = $effectiveImageUrl ?: url('images/itinerario.webp');
            $hasLocalImage = true;
        } elseif (! empty($defaultImageUrl)) {
            $effectiveImageUrl = $effectiveImageUrl ?: $defaultImageUrl;
        }

        return response()->json([
            'success' => true,
            'itinerary' => [
                'image_url' => $effectiveImageUrl,
                'web_url' => $itinerary['web_url'] ?? $defaultWebUrl,
                'caption' => $itinerary['caption'] ?? '',
                'has_image' => $hasLocalImage || ! empty($effectiveImageUrl),
                'updated_at' => $itinerary['updated_at'] ?? null,
                'updated_by' => $itinerary['updated_by'] ?? null,
            ],
        ]);
    }

    /**
     * Actualiza la imagen y datos del itinerario oficial desde el panel admin
     */
    public function updateItinerary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'web_url' => 'nullable|url|max:500',
            'caption' => 'nullable|string|max:1000',
        ]);

        $current = Setting::get('itinerary', []);
        $imageUrl = $current['image_url'] ?? null;
        $localPath = $current['local_path'] ?? null;

        // Si se subió un nuevo archivo de imagen
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'itinerario_'.time().'.'.$ext;

            // Guardar en storage/app/public/itineraries/
            $savedPath = $file->storeAs('itineraries', $filename, 'public');
            $fullStoragePath = storage_path('app/public/'.$savedPath);
            $imageUrl = asset('storage/'.$savedPath);
            $localPath = $fullStoragePath;

            // También copiar a public/images/itinerario.{ext} para compatibilidad inmediata
            try {
                if (! file_exists(public_path('images'))) {
                    mkdir(public_path('images'), 0755, true);
                }
                copy($fullStoragePath, public_path('images/itinerario.'.$ext));
            } catch (\Throwable) {
                // Silencioso si falla la copia redundante
            }
        }

        $newData = [
            'image_url' => $imageUrl,
            'local_path' => $localPath,
            'web_url' => $validated['web_url'] ?? ($current['web_url'] ?? config('services.airline.itinerario_web_url', 'https://tamep.bo/itinerarios')),
            'caption' => $validated['caption'] ?? ($current['caption'] ?? ''),
            'updated_at' => now()->format('Y-m-d H:i:s'),
            'updated_by' => auth()->user()?->name ?? 'Administrador',
        ];

        Setting::set('itinerary', $newData);

        return response()->json([
            'success' => true,
            'message' => 'Itinerario de vuelos actualizado correctamente.',
            'itinerary' => $newData,
        ]);
    }

    /**
     * Obtiene el listado completo de mensajes y plantillas del Bot de WhatsApp
     */
    public function getBotMessages(): JsonResponse
    {
        $messages = BotMessage::orderBy('category')->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Modifica el texto de un mensaje del bot
     */
    public function updateBotMessage(Request $request, BotMessage $botMessage): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $botMessage->update([
            'message' => $validated['message'],
            'updated_by' => auth()->user()?->name ?? 'Administrador Comercial',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mensaje del bot guardado correctamente.',
            'bot_message' => $botMessage->fresh(),
        ]);
    }

    /**
     * Restablece el texto de un mensaje a su versión predeterminada de fábrica
     */
    public function restoreBotMessage(BotMessage $botMessage): JsonResponse
    {
        $botMessage->update([
            'message' => $botMessage->default_message,
            'updated_by' => (auth()->user()?->name ?? 'Administrador').' (Predeterminado)',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'El mensaje ha sido restablecido a su versión predeterminada.',
            'bot_message' => $botMessage->fresh(),
        ]);
    }
}
