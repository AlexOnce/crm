<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Services\BroadcastHelper;
use App\Services\FlightMicroservice;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlightBookingController extends Controller
{
    public function issueTicket(
        Request $request,
        Lead $lead,
        FlightMicroservice $flightService,
        MetaWhatsAppService $whatsapp
    ): JsonResponse {
        $validated = $request->validate([
            'passenger_name' => 'required|string|max:150',
            'passport_number' => 'required|string|max:50',
            'origin' => 'required|string|max:10',
            'destination' => 'required|string|max:10',
            'flight_date' => 'required|date',
            'flight_number' => 'required|string|max:20',
            'total_price' => 'required|numeric|min:0',
            'passengers_count' => 'nullable|integer|min:1|max:70',
            'details' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $lead, $flightService, $request) {
            $paxCount = (int) ($validated['passengers_count'] ?? $lead->passengers_count ?? 1);
            if ($paxCount < 1) {
                $paxCount = 1;
            }

            // 1. Orquestar la emisión del pasaje en el microservicio central de la aerolínea
            $booking = $flightService->bookTicket([
                'passenger' => $validated['passenger_name'],
                'document' => $validated['passport_number'],
                'flight' => $validated['flight_number'],
                'date' => $validated['flight_date'],
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'passengers' => $paxCount,
            ]);

            if (! $booking['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo emitir el boleto en el sistema central de vuelos.',
                ], 422);
            }

            // 2. Actualizar el Lead en la base de datos local marcándolo como emitido
            $lead->update([
                'passenger_name' => $validated['passenger_name'],
                'passport_number' => $validated['passport_number'],
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'flight_date' => $validated['flight_date'],
                'flight_number' => $validated['flight_number'],
                'passengers_count' => $paxCount,
                'total_price' => $validated['total_price'],
                'booking_reference' => $booking['pnr'],
                'details' => $validated['details'] ?? $lead->details,
                'status' => 'emitido',
            ]);

            // 3. Finalizar la atención del asesor y reactivar el bot en la conversación
            $updatedConversation = null;
            if ($lead->conversation) {
                $lead->conversation->update([
                    'assigned_user_id' => $request->user()?->id ?? $lead->conversation->assigned_user_id,
                    'status' => 'bot_active',
                    'current_state' => 'MENU_PRINCIPAL',
                    'session_data' => [],
                    'last_interaction_at' => now(),
                ]);
                $updatedConversation = $lead->conversation->fresh();
                BroadcastHelper::safeBroadcast(new ConversationUpdated($updatedConversation, 'released'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Boleto registrado como emitido en sistema y atención finalizada con éxito.',
                'lead' => $lead->fresh(),
                'conversation' => $updatedConversation,
                'chat_message' => null,
                'pnr' => $booking['pnr'],
            ]);
        });
    }

    /**
     * Crear o registrar un lead manualmente para una conversación
     */
    public function storeManualLead(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'passenger_name' => 'required|string|max:150',
            'passport_number' => 'nullable|string|max:50',
            'origin' => 'required|string|max:10',
            'destination' => 'required|string|max:10',
            'flight_date' => 'required|date',
            'flight_number' => 'nullable|string|max:20',
            'passengers_count' => 'nullable|integer|min:1|max:70',
            'total_price' => 'nullable|numeric|min:0',
            'invoice_razon_social' => 'nullable|string|max:150',
            'invoice_nit' => 'nullable|string|max:50',
            'details' => 'nullable|string',
        ]);

        $paxCount = (int) ($validated['passengers_count'] ?? 1);
        if ($paxCount < 1) {
            $paxCount = 1;
        }

        $botPayload = [
            'manual_crm' => true,
            'passenger_flow' => [
                'full_name' => $validated['passenger_name'],
                'doc_number' => $validated['passport_number'] ?? null,
                'passengers' => $paxCount,
                'invoice_razon_social' => $validated['invoice_razon_social'] ?? null,
                'invoice_nit' => $validated['invoice_nit'] ?? null,
            ],
            'created_by' => $request->user()?->name ?? 'Asesor CRM',
        ];

        $lead = Lead::create([
            'conversation_id' => $conversation->id,
            'phone' => $conversation->phone,
            'type' => 'compra_pasaje',
            'passenger_name' => $validated['passenger_name'],
            'passport_number' => $validated['passport_number'] ?: 'Pendiente',
            'origin' => strtoupper($validated['origin']),
            'destination' => strtoupper($validated['destination']),
            'flight_date' => $validated['flight_date'],
            'flight_number' => $validated['flight_number'] ?: 'TM-204',
            'passengers_count' => $paxCount,
            'total_price' => (float) ($validated['total_price'] ?? 0),
            'status' => 'contactado',
            'details' => $validated['details'] ?? 'Registro manual por asesor en CRM',
            'bot_payload' => $botPayload,
        ]);

        if (empty($conversation->contact_name)) {
            $conversation->update(['contact_name' => $validated['passenger_name']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reserva registrada exitosamente.',
            'lead' => $lead->fresh(),
        ]);
    }

    /**
     * Actualizar los datos de un Lead existente
     */
    public function updateLead(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'passenger_name' => 'sometimes|required|string|max:150',
            'passport_number' => 'nullable|string|max:50',
            'origin' => 'sometimes|required|string|max:10',
            'destination' => 'sometimes|required|string|max:10',
            'flight_date' => 'sometimes|required|date',
            'flight_number' => 'nullable|string|max:20',
            'passengers_count' => 'nullable|integer|min:1|max:70',
            'total_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'invoice_razon_social' => 'nullable|string|max:150',
            'invoice_nit' => 'nullable|string|max:50',
            'details' => 'nullable|string',
        ]);

        $paxCount = isset($validated['passengers_count']) ? (int) $validated['passengers_count'] : $lead->passengers_count;
        if ($paxCount < 1) {
            $paxCount = 1;
        }

        $botPayload = $lead->bot_payload ?: [];
        if (! isset($botPayload['passenger_flow']) || ! is_array($botPayload['passenger_flow'])) {
            $botPayload['passenger_flow'] = [];
        }

        if (isset($validated['passenger_name'])) {
            $botPayload['passenger_flow']['full_name'] = $validated['passenger_name'];
        }
        if (array_key_exists('passport_number', $validated)) {
            $botPayload['passenger_flow']['doc_number'] = $validated['passport_number'];
        }
        if (! empty($validated['invoice_razon_social'])) {
            $botPayload['passenger_flow']['invoice_razon_social'] = $validated['invoice_razon_social'];
        }
        if (! empty($validated['invoice_nit'])) {
            $botPayload['passenger_flow']['invoice_nit'] = $validated['invoice_nit'];
        }
        $botPayload['passenger_flow']['passengers'] = $paxCount;

        $updateData = [
            'passenger_name' => $validated['passenger_name'] ?? $lead->passenger_name,
            'passport_number' => array_key_exists('passport_number', $validated) ? ($validated['passport_number'] ?: 'Pendiente') : $lead->passport_number,
            'origin' => isset($validated['origin']) ? strtoupper($validated['origin']) : $lead->origin,
            'destination' => isset($validated['destination']) ? strtoupper($validated['destination']) : $lead->destination,
            'flight_date' => $validated['flight_date'] ?? $lead->flight_date,
            'flight_number' => $validated['flight_number'] ?? $lead->flight_number,
            'passengers_count' => $paxCount,
            'total_price' => isset($validated['total_price']) ? (float) $validated['total_price'] : $lead->total_price,
            'details' => $validated['details'] ?? $lead->details,
            'bot_payload' => $botPayload,
        ];

        if (! empty($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        $lead->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Datos de reserva actualizados.',
            'lead' => $lead->fresh(),
        ]);
    }

    /**
     * Vista pública / web para imprimir o descargar el billete electrónico oficial de TAMep
     */
    public function viewTicketHtml(Lead $lead, FlightMicroservice $flightService)
    {
        if (! in_array($lead->status, ['emitido', 'comprado'])) {
            abort(404, 'Este pasajero aún no cuenta con un boleto emitido (estado actual: '.$lead->status.').');
        }

        $pnr = $lead->booking_reference ?: ('TM'.strtoupper(substr(md5((string) $lead->id.(string) $lead->phone), 0, 4)));
        $ticketNumber = '729-'.str_pad((string) (($lead->id * 142857) % 10000000000), 10, '0', STR_PAD_LEFT);

        $rawDate = $lead->flight_date ? (string) $lead->flight_date : now()->format('Y-m-d');
        $cleanDate = explode(' ', $rawDate)[0];

        $bookingData = [
            'passenger' => $lead->passenger_name ?: 'Pasajero TAMep',
            'document' => $lead->passport_number ?: 'Sin Documento',
            'flight' => $lead->flight_number ?: 'TM-101',
            'date' => $cleanDate,
            'origin' => strtoupper($lead->origin ?: 'VVI'),
            'destination' => strtoupper($lead->destination ?: 'LPB'),
            'passengers' => (int) ($lead->passengers_count ?: 1),
            'total_price' => $lead->total_price ?: 0,
            'status' => in_array($lead->status, ['emitido', 'comprado']) ? 'CONFIRMADO' : strtoupper($lead->status ?: 'CONFIRMADO'),
        ];

        $html = $flightService->generateTicketHtmlWithPrintBar($bookingData, $pnr, $ticketNumber, $lead);

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
