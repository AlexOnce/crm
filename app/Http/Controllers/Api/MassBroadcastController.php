<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMassBroadcastJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Lead;
use App\Services\FlightManifestParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MassBroadcastController extends Controller
{
    /**
     * Previsualiza los destinatarios y métricas de una difusión antes de disparar.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flight_number' => 'nullable|string|max:20',
            'flight_date' => 'nullable|date',
            'origin' => 'nullable|string|max:10',
            'destination' => 'nullable|string|max:10',
            'status' => 'nullable|string', // 'emitido', 'comprado', 'todos'
            'manual_phones' => 'nullable|string', // lista separada por comas o saltos de línea
            'manifest_passengers' => 'nullable|array',
            'message' => 'nullable|string|max:1000',
        ]);

        $query = Lead::query();

        // 1. Filtrar pasajeros en base de datos
        if (! empty($validated['flight_number'])) {
            $query->where('flight_number', 'like', "%{$validated['flight_number']}%");
        }

        if (! empty($validated['flight_date'])) {
            $query->whereDate('flight_date', $validated['flight_date']);
        }

        if (! empty($validated['origin'])) {
            $query->where('origin', strtoupper($validated['origin']));
        }

        if (! empty($validated['destination'])) {
            $query->where('destination', strtoupper($validated['destination']));
        }

        if (! empty($validated['status']) && $validated['status'] !== 'todos') {
            $query->where('status', $validated['status']);
        } else {
            // Por defecto, considerar pasajes emitidos o comprados
            $query->whereIn('status', ['emitido', 'comprado', 'contactado']);
        }

        $leads = $query->select([
            'id', 'passenger_name', 'phone', 'flight_number', 'flight_date', 'origin', 'destination', 'booking_reference', 'status',
        ])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        // 2. Procesar números manuales si existen
        $manualList = [];
        if (! empty($validated['manual_phones'])) {
            $rawPhones = preg_split('/[\r\n,;]+/', $validated['manual_phones']);
            foreach ($rawPhones as $raw) {
                $clean = preg_replace('/\D/', '', (string) $raw);
                if (strlen($clean) >= 8) {
                    $manualList[] = [
                        'id' => null,
                        'passenger_name' => 'Destinatario Manual',
                        'phone' => $clean,
                        'flight_number' => $validated['flight_number'] ?? 'N/A',
                        'flight_date' => $validated['flight_date'] ?? null,
                        'origin' => $validated['origin'] ?? '',
                        'destination' => $validated['destination'] ?? '',
                        'booking_reference' => null,
                        'status' => 'manual',
                    ];
                }
            }
        }

        // 3. Unificar y desduplicar por número de teléfono
        $seenPhones = [];
        $uniquePassengers = [];

        foreach ($leads as $lead) {
            $cleanPhone = preg_replace('/\D/', '', $lead->phone);
            if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                $seenPhones[$cleanPhone] = true;
                $uniquePassengers[] = [
                    'id' => $lead->id,
                    'passenger_name' => $lead->passenger_name ?: 'Pasajero',
                    'phone' => $cleanPhone,
                    'flight_number' => $lead->flight_number,
                    'flight_date' => $lead->flight_date?->format('Y-m-d') ?: (string) $lead->flight_date,
                    'origin' => $lead->origin,
                    'destination' => $lead->destination,
                    'booking_reference' => $lead->booking_reference,
                    'status' => $lead->status,
                ];
            }
        }

        foreach ($manualList as $man) {
            if (! isset($seenPhones[$man['phone']])) {
                $seenPhones[$man['phone']] = true;
                $uniquePassengers[] = $man;
            }
        }

        // 4. Incorporar pasajeros procedentes del Manifiesto PDF si fueron cargados
        if (! empty($validated['manifest_passengers'])) {
            foreach ($validated['manifest_passengers'] as $pax) {
                $cleanPhone = preg_replace('/\D/', '', (string) ($pax['phone'] ?? ''));
                if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                    $seenPhones[$cleanPhone] = true;
                    $uniquePassengers[] = [
                        'id' => null,
                        'passenger_name' => $pax['passenger_name'] ?? 'Pasajero',
                        'phone' => $cleanPhone,
                        'flight_number' => $pax['flight_number'] ?? ($validated['flight_number'] ?? ''),
                        'flight_date' => $validated['flight_date'] ?? null,
                        'origin' => $pax['origin'] ?? ($validated['origin'] ?? ''),
                        'destination' => $pax['destination'] ?? ($validated['destination'] ?? ''),
                        'booking_reference' => $pax['pnr'] ?? ($pax['booking_reference'] ?? ''),
                        'status' => 'manifest_pdf',
                    ];
                }
            }
        }

        $totalRecipients = count($uniquePassengers);

        // 4. Calcular costo estimado de SMS según longitud de la plantilla
        $messageText = $validated['message'] ?? '';
        $samplePersonalized = $totalRecipients > 0
            ? $this->personalizeMessage($messageText, $uniquePassengers[0])
            : $messageText;

        $charsCount = mb_strlen($samplePersonalized);
        $smsSegments = $this->calculateSmsSegments($charsCount);

        return response()->json([
            'success' => true,
            'total_recipients' => $totalRecipients,
            'sample_recipients' => array_slice($uniquePassengers, 0, 10),
            'sms_metrics' => [
                'chars_count' => $charsCount,
                'sms_per_recipient' => $smsSegments,
                'total_sms_estimated' => $totalRecipients * $smsSegments,
            ],
            'sample_message' => $samplePersonalized,
        ]);
    }

    /**
     * Inicia una nueva campaña masiva y la envía a segundo plano.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'channel' => 'nullable|in:sms',
            'template_type' => 'nullable|in:flight_delay,flight_cancellation,flight_reminder,custom',
            'message' => 'required|string|max:1000',
            'flight_number' => 'nullable|string|max:20',
            'flight_date' => 'nullable|date',
            'origin' => 'nullable|string|max:10',
            'destination' => 'nullable|string|max:10',
            'status' => 'nullable|string',
            'manual_phones' => 'nullable|string',
            'manifest_passengers' => 'nullable|array',
        ]);

        $validated['channel'] = 'sms';
        $validated['template_type'] = $validated['template_type'] ?? 'custom';

        return DB::transaction(function () use ($validated, $request) {
            // 1. Obtener lista de destinatarios únicos
            $query = Lead::query();

            if (! empty($validated['flight_number'])) {
                $query->where('flight_number', 'like', "%{$validated['flight_number']}%");
            }

            if (! empty($validated['flight_date'])) {
                $query->whereDate('flight_date', $validated['flight_date']);
            }

            if (! empty($validated['origin'])) {
                $query->where('origin', strtoupper($validated['origin']));
            }

            if (! empty($validated['destination'])) {
                $query->where('destination', strtoupper($validated['destination']));
            }

            if (! empty($validated['status']) && $validated['status'] !== 'todos') {
                $query->where('status', $validated['status']);
            } else {
                $query->whereIn('status', ['emitido', 'comprado', 'contactado']);
            }

            $leads = $query->select([
                'id', 'passenger_name', 'phone', 'flight_number', 'flight_date', 'origin', 'destination', 'booking_reference',
            ])
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get();

            $seenPhones = [];
            $recipientsData = [];

            foreach ($leads as $lead) {
                $cleanPhone = preg_replace('/\D/', '', $lead->phone);
                if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                    $seenPhones[$cleanPhone] = true;
                    $leadInfo = [
                        'passenger_name' => $lead->passenger_name ?: 'Pasajero',
                        'phone' => $cleanPhone,
                        'flight_number' => $lead->flight_number ?: '',
                        'flight_date' => $lead->flight_date?->format('d/m/Y') ?: '',
                        'origin' => $lead->origin ?: '',
                        'destination' => $lead->destination ?: '',
                        'booking_reference' => $lead->booking_reference ?: '',
                    ];

                    $personalized = $this->personalizeMessage($validated['message'], $leadInfo);

                    $recipientsData[] = [
                        'lead_id' => $lead->id,
                        'phone' => $cleanPhone,
                        'passenger_name' => $lead->passenger_name,
                        'flight_number' => $lead->flight_number,
                        'message' => $personalized,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Añadir números manuales si existen
            if (! empty($validated['manual_phones'])) {
                $rawPhones = preg_split('/[\r\n,;]+/', $validated['manual_phones']);
                foreach ($rawPhones as $raw) {
                    $cleanPhone = preg_replace('/\D/', '', (string) $raw);
                    if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                        $seenPhones[$cleanPhone] = true;
                        $manualInfo = [
                            'passenger_name' => 'Pasajero',
                            'phone' => $cleanPhone,
                            'flight_number' => $validated['flight_number'] ?? '',
                            'flight_date' => $validated['flight_date'] ?? '',
                            'origin' => $validated['origin'] ?? '',
                            'destination' => $validated['destination'] ?? '',
                            'booking_reference' => '',
                        ];

                        $personalized = $this->personalizeMessage($validated['message'], $manualInfo);

                        $recipientsData[] = [
                            'lead_id' => null,
                            'phone' => $cleanPhone,
                            'passenger_name' => null,
                            'flight_number' => $validated['flight_number'] ?? null,
                            'message' => $personalized,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // 3. Destinatarios del Manifiesto PDF
            if (! empty($validated['manifest_passengers'])) {
                foreach ($validated['manifest_passengers'] as $pax) {
                    $cleanPhone = preg_replace('/\D/', '', (string) ($pax['phone'] ?? ''));
                    if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                        $seenPhones[$cleanPhone] = true;
                        $paxInfo = [
                            'passenger_name' => $pax['passenger_name'] ?? 'Pasajero',
                            'phone' => $cleanPhone,
                            'flight_number' => $pax['flight_number'] ?? ($validated['flight_number'] ?? ''),
                            'flight_date' => $validated['flight_date'] ?? '',
                            'origin' => $pax['origin'] ?? ($validated['origin'] ?? ''),
                            'destination' => $pax['destination'] ?? ($validated['destination'] ?? ''),
                            'booking_reference' => $pax['pnr'] ?? ($pax['booking_reference'] ?? ''),
                        ];

                        $personalized = $this->personalizeMessage($validated['message'], $paxInfo);

                        $recipientsData[] = [
                            'lead_id' => null,
                            'phone' => $cleanPhone,
                            'passenger_name' => $paxInfo['passenger_name'],
                            'flight_number' => $paxInfo['flight_number'],
                            'message' => $personalized,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            if (empty($recipientsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron pasajeros válidos con los filtros seleccionados.',
                ], 422);
            }

            $total = count($recipientsData);
            $sampleLen = mb_strlen($recipientsData[0]['message']);
            $smsSegments = $this->calculateSmsSegments($sampleLen);

            // 2. Registrar Campaña
            $campaign = BroadcastCampaign::create([
                'user_id' => $request->user()?->id,
                'title' => $validated['title'],
                'channel' => $validated['channel'],
                'template_type' => $validated['template_type'],
                'message' => $validated['message'],
                'filters' => [
                    'flight_number' => $validated['flight_number'] ?? null,
                    'flight_date' => $validated['flight_date'] ?? null,
                    'origin' => $validated['origin'] ?? null,
                    'destination' => $validated['destination'] ?? null,
                    'status' => $validated['status'] ?? null,
                ],
                'total_recipients' => $total,
                'sent_count' => 0,
                'failed_count' => 0,
                'status' => 'pending',
                'estimated_sms_per_recipient' => $smsSegments,
            ]);

            // 3. Insertar destinatarios en lote
            foreach ($recipientsData as &$item) {
                $item['campaign_id'] = $campaign->id;
            }
            unset($item);

            BroadcastRecipient::insert($recipientsData);

            // 4. Despachar el Job Orquestador en segundo plano
            ProcessMassBroadcastJob::dispatch($campaign);

            return response()->json([
                'success' => true,
                'message' => "Campaña '{$campaign->title}' iniciada correctamente con {$total} destinatarios.",
                'campaign' => $campaign->fresh()->load('user:id,name'),
            ], 201);
        });
    }

    /**
     * Listado paginado de campañas de difusión masiva.
     */
    public function index(Request $request): JsonResponse
    {
        $campaigns = BroadcastCampaign::with('user:id,name')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Detalle de una campaña con sus destinatarios y estados.
     */
    public function show(BroadcastCampaign $campaign): JsonResponse
    {
        $recipients = $campaign->recipients()
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'campaign' => $campaign->load('user:id,name'),
            'recipients' => $recipients,
        ]);
    }

    /**
     * Reintenta el envío de los destinatarios fallidos de una campaña.
     */
    public function retryFailed(BroadcastCampaign $campaign): JsonResponse
    {
        $failedRecipients = $campaign->recipients()->where('status', 'failed')->get();

        if ($failedRecipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No existen destinatarios fallidos para reintentar en esta campaña.',
            ], 422);
        }

        $failedCount = $failedRecipients->count();

        // Resetear a pending
        $campaign->recipients()->where('status', 'failed')->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        $campaign->update([
            'status' => 'processing',
            'failed_count' => max(0, $campaign->failed_count - $failedCount),
        ]);

        ProcessMassBroadcastJob::dispatch($campaign);

        return response()->json([
            'success' => true,
            'message' => "Se han reencolado {$failedCount} mensajes fallidos para su reintento.",
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Personaliza las etiquetas dinámicas dentro de una plantilla.
     */
    protected function personalizeMessage(string $template, array $data): string
    {
        $replacements = [
            '{pasajero}' => $data['passenger_name'] ?? 'Pasajero',
            '{nombre}' => $data['passenger_name'] ?? 'Pasajero',
            '{vuelo}' => $data['flight_number'] ?? '',
            '{fecha}' => $data['flight_date'] ?? '',
            '{origen}' => $data['origin'] ?? '',
            '{destino}' => $data['destination'] ?? '',
            '{pnr}' => $data['booking_reference'] ?? '',
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Calcula la cantidad de SMS según longitud de caracteres GSM-7.
     */
    protected function calculateSmsSegments(int $charsCount): int
    {
        if ($charsCount <= 0) {
            return 1;
        }

        // SMS único GSM-7 estándar
        if ($charsCount <= 160) {
            return 1;
        }

        // SMS concatenado (153 chars por segmento)
        return (int) ceil($charsCount / 153);
    }

    /**
     * Parsea un manifiesto en formato PDF (desde /documentos/vuelo.pdf o archivo subido).
     */
    public function importPdfManifest(Request $request, FlightManifestParserService $parser): JsonResponse
    {
        $request->validate([
            'pdf_file' => 'nullable|file|mimes:pdf|max:20480',
            'use_default_doc' => 'nullable|boolean',
        ]);

        if ($request->hasFile('pdf_file')) {
            $uploaded = $request->file('pdf_file');
            $tempPath = $uploaded->getRealPath();
        } else {
            // Documento por defecto en /documentos/vuelo.pdf
            $tempPath = base_path('documentos/vuelo.pdf');
        }

        if (! file_exists($tempPath)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el archivo de manifiesto en: '.$tempPath,
            ], 404);
        }

        try {
            $data = $parser->parsePdf($tempPath);

            return response()->json([
                'success' => true,
                'manifest' => $data,
                'message' => "Manifiesto procesado: {$data['valid_phones_count']} de {$data['total_passengers']} pasajeros con celular boliviano detectados.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo PDF: '.$e->getMessage(),
            ], 422);
        }
    }
}
