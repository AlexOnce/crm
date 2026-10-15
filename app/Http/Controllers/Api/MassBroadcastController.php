<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMassBroadcastJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Lead;
use App\Services\FlightManifestParserService;
use App\Services\FlightMicroservice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
            'flight_time' => 'nullable|string|max:20',
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
                        'passenger_name' => 'Pasajero',
                        'paternal_surname' => 'Pasajero',
                        'phone' => $clean,
                        'flight_number' => $validated['flight_number'] ?? 'N/A',
                        'flight_date' => $validated['flight_date'] ?? null,
                        'flight_time' => $validated['flight_time'] ?? null,
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

        // Prioridad 1: Pasajeros del Manifiesto PDF (nombres y apellidos reales)
        if (! empty($validated['manifest_passengers'])) {
            foreach ($validated['manifest_passengers'] as $pax) {
                $cleanPhone = preg_replace('/\D/', '', (string) ($pax['phone'] ?? ''));
                if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                    $seenPhones[$cleanPhone] = true;
                    $uniquePassengers[] = [
                        'id' => null,
                        'passenger_name' => $pax['passenger_name'] ?? 'Pasajero',
                        'paternal_surname' => $pax['paternal_surname'] ?? null,
                        'phone' => $cleanPhone,
                        'flight_number' => $pax['flight_number'] ?? ($validated['flight_number'] ?? ''),
                        'flight_date' => $validated['flight_date'] ?? null,
                        'flight_time' => $validated['flight_time'] ?? ($pax['departure_time'] ?? null),
                        'origin' => $pax['origin'] ?? ($validated['origin'] ?? ''),
                        'destination' => $pax['destination'] ?? ($validated['destination'] ?? ''),
                        'booking_reference' => $pax['pnr'] ?? ($pax['booking_reference'] ?? ''),
                        'status' => 'manifest_pdf',
                    ];
                }
            }
        }

        // Prioridad 2: Pasajeros de la base de datos CRM
        foreach ($leads as $lead) {
            $cleanPhone = preg_replace('/\D/', '', $lead->phone);
            if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                $seenPhones[$cleanPhone] = true;
                $uniquePassengers[] = [
                    'id' => $lead->id,
                    'passenger_name' => $lead->passenger_name ?: 'Pasajero',
                    'paternal_surname' => $this->extractPaternalSurname($lead->passenger_name),
                    'phone' => $cleanPhone,
                    'flight_number' => $lead->flight_number,
                    'flight_date' => $lead->flight_date?->format('Y-m-d') ?: (string) $lead->flight_date,
                    'flight_time' => $validated['flight_time'] ?? null,
                    'origin' => $lead->origin,
                    'destination' => $lead->destination,
                    'booking_reference' => $lead->booking_reference,
                    'status' => $lead->status,
                ];
            }
        }

        // Prioridad 3: Números manuales adicionales
        foreach ($manualList as $man) {
            if (! isset($seenPhones[$man['phone']])) {
                $seenPhones[$man['phone']] = true;
                $uniquePassengers[] = $man;
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
            'title' => 'nullable|string|max:150',
            'channel' => 'nullable|in:sms',
            'template_type' => 'nullable|string|max:50',
            'message' => 'required|string|max:1000',
            'flight_number' => 'nullable|string|max:20',
            'flight_date' => 'nullable|date',
            'flight_time' => 'nullable|string|max:20',
            'origin' => 'nullable|string|max:10',
            'destination' => 'nullable|string|max:10',
            'status' => 'nullable|string',
            'manual_phones' => 'nullable|string',
            'manifest_passengers' => 'nullable|array',
        ]);

        $validated['channel'] = 'sms';
        $validated['template_type'] = $validated['template_type'] ?? 'custom';
        $validated['title'] = ! empty($validated['title'])
            ? $validated['title']
            : ('Aviso Vuelo '.($validated['flight_number'] ? 'TM-'.preg_replace('/^TM-?/i', '', $validated['flight_number']) : 'TAMep').' '.($validated['flight_date'] ?? now()->format('d/m/Y')));

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
            }

            $leads = $query->select([
                'id', 'passenger_name', 'phone', 'flight_number', 'flight_date', 'origin', 'destination', 'booking_reference',
            ])
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get();

            $seenPhones = [];
            $recipientsData = [];

            // 1. Destinatarios del Manifiesto PDF (nombres y apellidos reales)
            if (! empty($validated['manifest_passengers'])) {
                foreach ($validated['manifest_passengers'] as $pax) {
                    $cleanPhone = preg_replace('/\D/', '', (string) ($pax['phone'] ?? ''));
                    if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                        $seenPhones[$cleanPhone] = true;
                        $paxInfo = [
                            'passenger_name' => $pax['passenger_name'] ?? 'Pasajero',
                            'paternal_surname' => $pax['paternal_surname'] ?? null,
                            'phone' => $cleanPhone,
                            'flight_number' => $pax['flight_number'] ?? ($validated['flight_number'] ?? ''),
                            'flight_date' => $validated['flight_date'] ?? '',
                            'flight_time' => $validated['flight_time'] ?? ($pax['departure_time'] ?? null),
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

            // 2. Destinatarios de la base de datos CRM
            foreach ($leads as $lead) {
                $cleanPhone = preg_replace('/\D/', '', $lead->phone);
                if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                    $seenPhones[$cleanPhone] = true;
                    $leadInfo = [
                        'passenger_name' => $lead->passenger_name ?: 'Pasajero',
                        'paternal_surname' => $this->extractPaternalSurname($lead->passenger_name),
                        'phone' => $cleanPhone,
                        'flight_number' => $lead->flight_number ?: '',
                        'flight_date' => $lead->flight_date?->format('d/m/Y') ?: '',
                        'flight_time' => $validated['flight_time'] ?? null,
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

            // 3. Destinatarios añadidos por números manuales adicionales
            if (! empty($validated['manual_phones'])) {
                $rawPhones = preg_split('/[\r\n,;]+/', $validated['manual_phones']);
                foreach ($rawPhones as $raw) {
                    $cleanPhone = preg_replace('/\D/', '', (string) $raw);
                    if (strlen($cleanPhone) >= 8 && ! isset($seenPhones[$cleanPhone])) {
                        $seenPhones[$cleanPhone] = true;
                        $manualInfo = [
                            'passenger_name' => 'Pasajero',
                            'paternal_surname' => 'Pasajero',
                            'phone' => $cleanPhone,
                            'flight_number' => $validated['flight_number'] ?? '',
                            'flight_date' => $validated['flight_date'] ?? '',
                            'flight_time' => $validated['flight_time'] ?? null,
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
     * Listado paginado de campañas de difusión masiva con filtros opcionales de vuelo y fecha.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BroadcastCampaign::with('user:id,name');

        if ($request->filled('flight_number')) {
            $flightNum = trim(preg_replace('/^TM-?/i', '', (string) $request->input('flight_number')));
            $query->where(function ($q) use ($flightNum) {
                $q->where('title', 'like', "%{$flightNum}%")
                    ->orWhere('filters->flight_number', 'like', "%{$flightNum}%")
                    ->orWhereHas('recipients', function ($r) use ($flightNum) {
                        $r->where('flight_number', 'like', "%{$flightNum}%");
                    });
            });
        }

        if ($request->filled('date')) {
            $date = (string) $request->input('date');
            $query->where(function ($q) use ($date) {
                $q->whereDate('created_at', $date)
                    ->orWhere('filters->flight_date', $date);
            });
        }

        $campaigns = $query->orderByDesc('id')->paginate(15);

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
     * En mensajería masiva SMS GoIP se envía solo el apellido paterno y el mensaje predefinido.
     */
    protected function personalizeMessage(string $template, array $data): string
    {
        $fullName = $data['passenger_name'] ?? 'Pasajero';
        $paternalSurname = $data['paternal_surname'] ?? $this->extractPaternalSurname($fullName);
        $originCity = $this->resolveCityName($data['origin'] ?? '');
        $destinationCity = $this->resolveCityName($data['destination'] ?? '');
        $flightNumber = $data['flight_number'] ?? '';
        $flightDate = $data['flight_date'] ?? '';
        $flightTime = $data['flight_time'] ?? ($data['departure_time'] ?? '15:30');

        // Si la fecha viene en formato ISO (YYYY-MM-DD), formatear a DD/MM/YYYY
        if (is_string($flightDate) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $flightDate, $m)) {
            $flightDate = "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        // Normalizar fórmulas de saludo o prefijos al estándar "Sr/Sra {apellido}: "
        $trimmed = trim($template);
        if (preg_match('/^(?:Estimad[oa]\s+pasajer[oa]|Sr(?:@|\(a\))?\.?)\s*(\{apellido\})?:\s*/iu', $trimmed)) {
            $template = preg_replace('/^(?:Estimad[oa]\s+pasajer[oa]|Sr(?:@|\(a\))?\.?)\s*(\{apellido\})?:\s*/iu', 'Sr/Sra {apellido}: ', $trimmed);
        } elseif (preg_match('/^\{apellido(?:_paterno)?\}:\s*/iu', $trimmed)) {
            $template = preg_replace('/^\{apellido(?:_paterno)?\}:\s*/iu', 'Sr/Sra {apellido}: ', $trimmed);
        }

        // Si la plantilla no contiene ninguna variable de apellido/pasajero ni prefijo, anteponer "Sr/Sra [Apellido]: "
        if (! preg_match('/\{(?:apellido|apellido_paterno|pasajero|nombre)\}/i', $template)) {
            $template = "Sr/Sra {$paternalSurname}: {$template}";
        }

        $replacements = [
            '{apellido}' => $paternalSurname,
            '{apellido_paterno}' => $paternalSurname,
            '{pasajero}' => $paternalSurname, // Solo enviar apellido paterno, nunca nombre completo
            '{nombre}' => $paternalSurname,
            '{vuelo}' => $flightNumber,
            '{fecha}' => $flightDate,
            '{hora}' => $flightTime,
            '{origen}' => $originCity,
            '{destino}' => $destinationCity,
            '{ruta}' => ($originCity && $destinationCity) ? "{$originCity}-{$destinationCity}" : '',
            '{pnr}' => $data['booking_reference'] ?? ($data['pnr'] ?? ''),
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Extrae el apellido paterno del nombre completo del pasajero.
     */
    protected function extractPaternalSurname(?string $fullName): string
    {
        if (empty($fullName)) {
            return 'Pasajero';
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $fullName));
        $parts = explode(' ', $clean);

        if (empty($parts)) {
            return 'Pasajero';
        }

        $first = strtoupper($parts[0]);
        if ($first === 'DE' && isset($parts[1]) && strtoupper($parts[1]) === 'LA' && isset($parts[2])) {
            return $parts[0].' '.$parts[1].' '.$parts[2];
        }

        if (($first === 'DE' || $first === 'DEL') && isset($parts[1])) {
            return $parts[0].' '.$parts[1];
        }

        return $parts[0];
    }

    /**
     * Resuelve el nombre común de ciudad para aeropuertos de Bolivia.
     */
    protected function resolveCityName(?string $code): string
    {
        if (empty($code)) {
            return '';
        }

        $airports = [
            'VVI' => 'Santa Cruz',
            'SRZ' => 'Santa Cruz',
            'LPB' => 'La Paz',
            'CBB' => 'Cochabamba',
            'TJA' => 'Tarija',
            'SRE' => 'Sucre',
            'CIJ' => 'Cobija',
            'TDD' => 'Trinidad',
            'RIB' => 'Riberalta',
            'GYA' => 'Guayaramerin',
            'RBQ' => 'Rurrenabaque',
            'UYU' => 'Uyuni',
            'BYC' => 'Yacuiba',
            'POI' => 'Potosi',
        ];

        $upper = strtoupper(trim($code));

        return $airports[$upper] ?? $code;
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

    /**
     * Consulta el estado oficial y reprogramaciones de un vuelo en vivo en Transclick / Airclick.
     */
    public function checkFlightStatus(Request $request, FlightMicroservice $flightService): JsonResponse
    {
        $validated = $request->validate([
            'flight_number' => 'nullable|string',
            'flight_date' => 'nullable|string',
            'origin' => 'nullable|string',
            'destination' => 'nullable|string',
        ]);

        $cleanFlightNumber = ! empty($validated['flight_number'])
            ? strtoupper(trim(preg_replace('/^TM-?/i', '', (string) $validated['flight_number'])))
            : null;

        $date = ! empty($validated['flight_date'])
            ? Carbon::parse($validated['flight_date'])->format('Y-m-d')
            : now()->format('Y-m-d');

        $orig = ! empty($validated['origin']) ? strtoupper(trim($validated['origin'])) : null;
        $dest = ! empty($validated['destination']) ? strtoupper(trim($validated['destination'])) : null;

        try {
            $allFlights = [];
            $matchedFlight = null;

            if ($orig && $dest) {
                // Búsqueda directa por Origen y Destino solicitados
                $allFlights = $flightService->searchFlights($orig, $dest, $date);
            } else {
                // Búsqueda flexible cuando solo se selecciona la fecha (o solo origen/destino)
                $allRoutes = [
                    ['VVI', 'LPB'],
                    ['LPB', 'VVI'],
                    ['CBB', 'LPB'],
                    ['LPB', 'CBB'],
                    ['VVI', 'CBB'],
                    ['CBB', 'VVI'],
                    ['CIJ', 'LPB'],
                    ['LPB', 'CIJ'],
                    ['CIJ', 'VVI'],
                    ['VVI', 'CIJ'],
                    ['CIJ', 'CBB'],
                    ['CBB', 'CIJ'],
                ];

                $routesToQuery = array_values(array_filter($allRoutes, function ($pair) use ($orig, $dest) {
                    if ($orig && $pair[0] !== $orig) {
                        return false;
                    }
                    if ($dest && $pair[1] !== $dest) {
                        return false;
                    }

                    return true;
                }));

                $allFlights = $flightService->searchRoutesParallel($routesToQuery, $date);

                // Fallback para entornos de testing con mocks de searchFlights
                if (empty($allFlights)) {
                    foreach ($routesToQuery as [$rOrig, $rDest]) {
                        $resFlights = $flightService->searchFlights($rOrig, $rDest, $date);
                        if (! empty($resFlights)) {
                            foreach ($resFlights as $f) {
                                $allFlights[] = $f;
                            }
                            if (! empty($cleanFlightNumber)) {
                                break;
                            }
                        }
                    }
                }
            }

            if (! empty($cleanFlightNumber) && ! empty($allFlights)) {
                foreach ($allFlights as $f) {
                    $num = strtoupper(trim(preg_replace('/^TM-?/i', '', $f['flight_number'])));
                    if ($num === $cleanFlightNumber || str_contains($num, $cleanFlightNumber)) {
                        $matchedFlight = $f;
                        break;
                    }
                }
            }

            if (! empty($allFlights)) {
                $primaryFlight = $matchedFlight ?? $allFlights[0];

                return response()->json([
                    'success' => true,
                    'found' => true,
                    'flights' => $allFlights,
                    'flight' => $primaryFlight,
                    'departure_time' => $primaryFlight['departure_time'] ?? null,
                    'departure_date' => $primaryFlight['departure_date'] ?? $date,
                    'origin' => $primaryFlight['origin'] ?? $orig,
                    'destination' => $primaryFlight['destination'] ?? $dest,
                    'origin_city' => $primaryFlight['origin_city'] ?? $this->resolveCityName($orig),
                    'destination_city' => $primaryFlight['destination_city'] ?? $this->resolveCityName($dest),
                    'status' => $primaryFlight['status'] ?? 'Confirmado',
                    'message' => 'Se encontraron '.count($allFlights).' vuelo(s) disponible(s) en Transclick.',
                ]);
            }

            return response()->json([
                'success' => true,
                'found' => false,
                'flights' => [],
                'message' => 'No se encontraron vuelos programados en Transclick para la fecha '.Carbon::parse($date)->format('d/m/Y').'.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar Transclick: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la cola de SMS en segundo plano bajo demanda.
     */
    public function processQueueNow(): JsonResponse
    {
        try {
            $pendingCount = DB::table('jobs')->whereIn('queue', ['sms', 'default'])->count();
            if ($pendingCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay mensajes pendientes en la cola de envío.',
                    'processed_count' => 0,
                ]);
            }

            // Ejecuta el worker hasta vaciar la cola o procesar lotes
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--queue' => 'sms,default',
                '--tries' => 3,
                '--max-time' => 50,
            ]);

            $remainingCount = DB::table('jobs')->whereIn('queue', ['sms', 'default'])->count();

            return response()->json([
                'success' => true,
                'message' => "Procesados mensajes de la cola. Pendientes restantes: {$remainingCount}.",
                'remaining_count' => $remainingCount,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la cola: '.$e->getMessage(),
            ], 500);
        }
    }
}
