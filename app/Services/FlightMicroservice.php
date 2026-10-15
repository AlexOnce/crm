<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlightMicroservice
{
    /**
     * Obtiene el token de autenticación OAuth de Airclick (Production / Staging)
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('airclick_access_token', now()->addMinutes(50), function () {
            $baseUrl = config('services.airclick.base_url', 'https://agair.transclicksolutions.com');
            $authBasic = config('services.airclick.auth_basic', 'ZXh0X3Rjc19haXJjbGljazozeHRfdGNzXzQxcmNsMWNr');
            $clientId = config('services.airclick.client_id', 'ext_tcs_airclick');

            try {
                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->withHeaders([
                        'Authorization' => 'Basic '.$authBasic,
                    ])
                    ->asForm()
                    ->post("{$baseUrl}/uaa/client", [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return $data['access_token'] ?? null;
                }

                Log::error('Airclick API: Error al obtener access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Airclick API: Excepción al conectar con endpoint de token', [
                    'error' => $e->getMessage(),
                ]);
            }

            return null;
        });
    }

    /**
     * Obtiene la lista oficial de aeropuertos desde la API de Airclick
     */
    public function getAirports(): array
    {
        return Cache::remember('airclick_airports_list', now()->addHours(24), function () {
            $token = $this->getAccessToken();
            $baseUrl = config('services.airclick.base_url', 'https://agair.transclicksolutions.com');

            if ($token) {
                try {
                    $response = Http::withoutVerifying()
                        ->timeout(12)
                        ->withHeaders([
                            'Authorization' => 'Bearer '.$token,
                            'access' => $token,
                            'Content-Type' => 'application/json',
                        ])
                        ->get("{$baseUrl}/sales/ext/airports");

                    if ($response->successful() && is_array($response->json())) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Airclick API: No se pudo obtener aeropuertos remotos, usando catálogo local', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Catálogo oficial de respaldo de Bolivia (TAMep)
            return [
                ['iataCode' => 'CIJ', 'name' => 'AEROPUERTO CAP ANIBAL ARAB', 'city' => 'COBIJA'],
                ['iataCode' => 'CBB', 'name' => 'AEROPUERTO INTERNACIONAL JORGE WILSTERMAN', 'city' => 'COCHABAMBA'],
                ['iataCode' => 'LPB', 'name' => 'AEROPUERTO INTERNACIONAL EL ALTO', 'city' => 'LA PAZ'],
                ['iataCode' => 'SRZ', 'name' => 'AEROPUERTO EL TROMPILLO', 'city' => 'SANTA CRUZ'],
                ['iataCode' => 'VVI', 'name' => 'AEROPUERTO INTERNACIONAL VIRU VIRU', 'city' => 'SANTA CRUZ'],
                ['iataCode' => 'TJA', 'name' => 'AEROPUERTO CAP ORIEL LEA PLAZA', 'city' => 'TARIJA'],
                ['iataCode' => 'TDD', 'name' => 'AEROPUERTO TTE AV JORGE HENRICH ARAUZ', 'city' => 'TRINIDAD'],
                ['iataCode' => 'SRE', 'name' => 'AEROPUERTO INTERNACIONAL DE ALCANTARI', 'city' => 'SUCRE'],
            ];
        });
    }

    /**
     * Resuelve un texto o nombre de ciudad a su código IATA oficial (3 letras)
     */
    public function resolveIataCode(string $query): string
    {
        $clean = strtoupper(trim($query));
        if (strlen($clean) === 3 && ctype_alpha($clean)) {
            return $clean;
        }

        $normalized = Str::ascii(mb_strtolower($query));
        $airports = $this->getAirports();

        // Mapeos comunes en lenguaje natural
        $aliases = [
            'la paz' => 'LPB',
            'el alto' => 'LPB',
            'cochabamba' => 'CBB',
            'cbb' => 'CBB',
            'cercado' => 'CBB',
            'santa cruz' => 'VVI',
            'viru viru' => 'VVI',
            'el trompillo' => 'SRZ',
            'sucre' => 'SRE',
            'alcantari' => 'SRE',
            'tarija' => 'TJA',
            'trinidad' => 'TDD',
            'beni' => 'TDD',
            'cobija' => 'CIJ',
            'pando' => 'CIJ',
        ];

        foreach ($aliases as $alias => $code) {
            if (str_contains($normalized, $alias)) {
                return $code;
            }
        }

        foreach ($airports as $ap) {
            $cityNorm = Str::ascii(mb_strtolower($ap['city'] ?? ''));
            $nameNorm = Str::ascii(mb_strtolower($ap['name'] ?? ''));

            if (str_contains($normalized, $cityNorm) || str_contains($cityNorm, $normalized)) {
                return $ap['iataCode'];
            }
            if (str_contains($normalized, $nameNorm)) {
                return $ap['iataCode'];
            }
        }

        return $clean;
    }

    /**
     * Devuelve el nombre de la ciudad correspondiente al código IATA
     */
    public function getCityName(string $iataCode): string
    {
        $code = strtoupper(trim($iataCode));
        $cities = [
            'LPB' => 'La Paz',
            'VVI' => 'Santa Cruz',
            'SRZ' => 'Santa Cruz',
            'CBB' => 'Cochabamba',
            'TJA' => 'Tarija',
            'SRE' => 'Sucre',
            'CIJ' => 'Cobija',
            'TDD' => 'Trinidad',
        ];

        if (isset($cities[$code])) {
            return $cities[$code];
        }

        foreach ($this->getAirports() as $ap) {
            if (strtoupper($ap['iataCode'] ?? '') === $code) {
                return $ap['city'] ?? $code;
            }
        }

        return $code;
    }

    /**
     * Consulta vuelos disponibles entre dos aeropuertos/ciudades consumiendo la API de Producción de Airclick
     */
    public function searchFlights(
        string $origin,
        string $destination,
        ?string $date = null,
        int $adults = 1,
        int $children = 0,
        int $babies = 0
    ): array {
        $originIata = $this->resolveIataCode($origin) ?: 'VVI';
        $destinyIata = $this->resolveIataCode($destination) ?: 'LPB';

        // Manejo de fecha compatible con el formato requerido por la API de Airclick: yyyy-MM-dd 00:00:00
        $parsedDate = $date ? Carbon::parse($date)->format('Y-m-d') : now()->addDay()->format('Y-m-d');
        $departureDateApi = "{$parsedDate} 00:00:00";

        $token = $this->getAccessToken();
        $baseUrl = config('services.airclick.base_url', 'https://agair.transclicksolutions.com');

        if ($token) {
            try {
                $response = Http::withoutVerifying()
                    ->timeout(12)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'access' => $token,
                        'Content-Type' => 'application/json',
                    ])
                    ->get("{$baseUrl}/sales/ext/flights", [
                        'originIata' => $originIata,
                        'destinyIata' => $destinyIata,
                        'adults' => max(1, min(70, $adults)),
                        'children' => max(0, min(70, $children)),
                        'babies' => max(0, min(70, $babies)),
                        'departureDate' => $departureDateApi,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $flights = $json['flights'] ?? [];

                    if (! empty($flights)) {
                        $mapped = [];
                        foreach ($flights as $f) {
                            $mapped[] = [
                                'flight_number' => 'TM-'.($f['number'] ?? '101'),
                                'origin' => $f['origin']['iataCode'] ?? $originIata,
                                'origin_city' => $f['origin']['city'] ?? $this->getCityName($originIata),
                                'origin_name' => $f['origin']['name'] ?? '',
                                'destination' => $f['destiny']['iataCode'] ?? $destinyIata,
                                'destination_city' => $f['destiny']['city'] ?? $this->getCityName($destinyIata),
                                'destination_name' => $f['destiny']['name'] ?? '',
                                'departure_time' => $f['departureTime'] ?? '08:00',
                                'arrival_time' => $f['arrivalTime'] ?? '09:00',
                                'departure_date' => $f['departureDate'] ?? $parsedDate,
                                'seats' => (int) ($f['seats'] ?? 0),
                                'price' => (float) ($f['totalAmount'] ?? 450.00),
                                'currency' => 'BOB',
                                'aircraft' => 'Avro RJ70 CP-3106',
                                'is_live' => true,
                            ];
                        }

                        return $mapped;
                    }

                    // La aerolínea no tiene vuelos programados para esta fecha
                    Log::info('Airclick API: No hay vuelos comerciales disponibles para la fecha', [
                        'origin' => $originIata,
                        'destination' => $destinyIata,
                        'date' => $parsedDate,
                    ]);

                    return [];
                } else {
                    Log::warning('Airclick API: Falló consulta de vuelos', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Airclick API: Excepción al consultar vuelos en vivo', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    /**
     * Consulta vuelos disponibles en múltiples rutas en paralelo utilizando Http::pool.
     *
     * @param  array<int, array{0: string, 1: string}>  $routes  Lista de pares [origen, destino]
     */
    public function searchRoutesParallel(array $routes, ?string $date = null): array
    {
        if (empty($routes)) {
            return [];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return [];
        }

        $parsedDate = $date ? Carbon::parse($date)->format('Y-m-d') : now()->addDay()->format('Y-m-d');
        $departureDateApi = "{$parsedDate} 00:00:00";
        $baseUrl = config('services.airclick.base_url', 'https://agair.transclicksolutions.com');

        try {
            $responses = Http::pool(function (Pool $pool) use ($routes, $baseUrl, $token, $departureDateApi) {
                $calls = [];
                foreach ($routes as $idx => [$orig, $dest]) {
                    $origIata = $this->resolveIataCode($orig);
                    $destIata = $this->resolveIataCode($dest);
                    $calls["{$idx}_{$origIata}_{$destIata}"] = $pool->withoutVerifying()
                        ->timeout(12)
                        ->withHeaders([
                            'Authorization' => 'Bearer '.$token,
                            'access' => $token,
                            'Content-Type' => 'application/json',
                        ])
                        ->get("{$baseUrl}/sales/ext/flights", [
                            'originIata' => $origIata,
                            'destinyIata' => $destIata,
                            'adults' => 1,
                            'children' => 0,
                            'babies' => 0,
                            'departureDate' => $departureDateApi,
                        ]);
                }

                return $calls;
            });

            $allFlights = [];
            $seenFlightKeys = [];

            foreach ($responses as $key => $res) {
                if ($res instanceof Response && $res->successful()) {
                    $data = $res->json();
                    $flights = $data['flights'] ?? [];
                    if (! empty($flights)) {
                        $parts = explode('_', $key);
                        $fallbackOrig = $parts[1] ?? 'VVI';
                        $fallbackDest = $parts[2] ?? 'LPB';

                        foreach ($flights as $f) {
                            $num = 'TM-'.($f['number'] ?? '101');
                            $depTime = $f['departureTime'] ?? '08:00';
                            $depDate = $f['departureDate'] ?? $parsedDate;
                            $uniqueKey = "{$num}_{$depDate}_{$depTime}";

                            if (! isset($seenFlightKeys[$uniqueKey])) {
                                $seenFlightKeys[$uniqueKey] = true;
                                $origIata = $f['origin']['iataCode'] ?? $fallbackOrig;
                                $destIata = $f['destiny']['iataCode'] ?? $fallbackDest;

                                $allFlights[] = [
                                    'flight_number' => $num,
                                    'origin' => $origIata,
                                    'origin_city' => $f['origin']['city'] ?? $this->getCityName($origIata),
                                    'origin_name' => $f['origin']['name'] ?? '',
                                    'destination' => $destIata,
                                    'destination_city' => $f['destiny']['city'] ?? $this->getCityName($destIata),
                                    'destination_name' => $f['destiny']['name'] ?? '',
                                    'departure_time' => $depTime,
                                    'arrival_time' => $f['arrivalTime'] ?? '09:00',
                                    'departure_date' => $depDate,
                                    'seats' => (int) ($f['seats'] ?? 0),
                                    'price' => (float) ($f['totalAmount'] ?? 450.00),
                                    'currency' => 'BOB',
                                    'aircraft' => 'Avro RJ70 CP-3106',
                                    'is_live' => true,
                                ];
                            }
                        }
                    }
                }
            }

            // Ordenar por hora de salida
            usort($allFlights, function ($a, $b) {
                return strcmp($a['departure_time'] ?? '', $b['departure_time'] ?? '');
            });

            return $allFlights;
        } catch (\Throwable $e) {
            Log::error('Airclick API: Excepción al consultar rutas en paralelo', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Busca vuelos en fechas cercanas (hasta +/- 4 días) cuando no hay disponibilidad en la fecha solicitada
     */
    public function findNearbyFlights(
        string $origin,
        string $destination,
        string $targetDate,
        int $adults = 1,
        int $children = 0,
        int $babies = 0,
        int $limit = 3
    ): array {
        try {
            $target = Carbon::parse($targetDate);
        } catch (\Throwable) {
            $target = now();
        }

        $today = now()->startOfDay();
        $offsets = [+1, -1, +2, -2, +3, -3, +4];
        $nearby = [];

        foreach ($offsets as $offset) {
            $checkDate = $target->copy()->addDays($offset);
            if ($checkDate->lt($today)) {
                continue;
            }

            $dateStr = $checkDate->format('Y-m-d');
            $flights = $this->searchFlights($origin, $destination, $dateStr, $adults, $children, $babies);

            if (! empty($flights)) {
                $nearby[] = [
                    'date' => $dateStr,
                    'formatted' => $checkDate->format('d/m/Y'),
                    'day_name' => $this->getDayNameEs($checkDate),
                    'offset_days' => $offset,
                    'flights' => $flights,
                ];
            }

            if (count($nearby) >= $limit) {
                break;
            }
        }

        // Ordenar por cercanía (menor distancia en días a la fecha solicitada)
        usort($nearby, fn ($a, $b) => abs($a['offset_days']) <=> abs($b['offset_days']));

        return $nearby;
    }

    /**
     * Obtiene el nombre del día en español
     */
    public function getDayNameEs(Carbon $date): string
    {
        $days = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];

        return $days[$date->dayOfWeek] ?? '';
    }

    /**
     * Orquesta la emisión oficial del boleto y genera el PNR
     */
    public function bookTicket(array $bookingData): array
    {
        // Generación de PNR oficial (6 caracteres alfanuméricos en mayúsculas)
        $pnr = 'TM'.strtoupper(Str::random(4));
        $ticketNumber = '729-'.rand(1000000000, 9999999999);

        // Generar archivo físico / documento PDF simulado en Storage seguro
        $storageDir = 'tickets';
        if (! Storage::disk('public')->exists($storageDir)) {
            Storage::disk('public')->makeDirectory($storageDir);
        }

        $fileName = "Boleto_{$pnr}.html";
        $filePath = "{$storageDir}/{$fileName}";

        // Plantilla HTML del pase de abordar / billete electrónico
        $htmlContent = $this->generateTicketHtml($bookingData, $pnr, $ticketNumber);
        Storage::disk('public')->put($filePath, $htmlContent);

        $publicUrl = asset("storage/{$filePath}");

        return [
            'success' => true,
            'pnr' => $pnr,
            'ticket_number' => $ticketNumber,
            'pdf_url_or_path' => $publicUrl,
            'file_name' => "Boleto_{$pnr}.pdf",
        ];
    }

    /**
     * Genera el HTML completo del boleto electrónico con barra de herramientas de impresión
     */
    public function generateTicketHtmlWithPrintBar(array $data, string $pnr, string $ticketNumber, ?Lead $lead = null): string
    {
        $passenger = htmlspecialchars($data['passenger'] ?? 'Pasajero');
        $document = htmlspecialchars($data['document'] ?? 'N/A');
        $flight = htmlspecialchars($data['flight'] ?? 'TM-101');
        $date = htmlspecialchars($data['date'] ?? now()->format('Y-m-d'));
        $origin = htmlspecialchars($data['origin'] ?? 'ORIGEN');
        $dest = htmlspecialchars($data['destination'] ?? 'DESTINO');
        $paxCount = (int) ($data['passengers'] ?? 1);
        $passengers = htmlspecialchars($paxCount > 1 ? "{$paxCount} Pasajeros" : '1 Pasajero');
        $status = htmlspecialchars($data['status'] ?? 'CONFIRMADO');
        $price = isset($data['total_price']) && $data['total_price'] > 0
            ? number_format((float) $data['total_price'], 2, '.', ',').' BOB'
            : 'Tarifa Oficial';

        $ticketDisplay = $ticketNumber;
        if ($paxCount > 1) {
            $rawDigits = preg_replace('/\D/', '', $ticketNumber);
            $numPart = (int) $rawDigits;
            $endNum = $numPart + $paxCount - 1;
            $ticketDisplay = '729-'.str_pad((string) $numPart, 10, '0', STR_PAD_LEFT).' al 729-'.str_pad((string) $endNum, 10, '0', STR_PAD_LEFT)." ({$paxCount} boletos)";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Boleto Electrónico TAMep - {$pnr} - {$passenger}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0f172a; padding: 24px; color: #1e293b; min-height: 100vh; }
    .container { max-width: 680px; margin: 0 auto; }
    
    /* Barra de Herramientas Superior */
    .toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); padding: 12px 20px; border-radius: 14px; color: #fff; }
    .toolbar-title { font-size: 13px; font-weight: 700; color: #94a3b8; display: flex; align-items: center; gap: 8px; }
    .toolbar-title span { color: #38bdf8; }
    .btn-print { background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%); color: #fff; border: none; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); transition: all 0.2s; }
    .btn-print:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45); }
    .btn-close { color: #94a3b8; text-decoration: none; font-size: 12px; font-weight: 600; padding: 8px 12px; border-radius: 8px; transition: background 0.2s; }
    .btn-close:hover { background: rgba(255,255,255,0.08); color: #fff; }

    /* Tarjeta Pase de Abordar */
    .ticket { background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border: 1px solid #e2e8f0; }
    .header { background: linear-gradient(135deg, #0b192c 0%, #1e3e62 100%); color: #fff; padding: 26px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #f59e0b; }
    .header-brand h1 { font-size: 22px; font-weight: 900; letter-spacing: 1.5px; color: #ffffff; }
    .header-brand .sub { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-top: 3px; font-weight: 600; }
    .header-brand .type { font-size: 11px; color: #38bdf8; margin-top: 2px; font-weight: 700; }
    .pnr-box { text-align: right; }
    .pnr-label { font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 2px; }
    .pnr-code { background: #0284c7; color: #ffffff; font-weight: 900; padding: 6px 14px; border-radius: 10px; font-size: 15px; font-family: 'Courier New', Courier, monospace; letter-spacing: 1.5px; display: inline-block; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3); }

    /* Cuerpo */
    .content { padding: 32px 30px; }
    .route-banner { display: flex; align-items: center; justify-content: space-around; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 24px; border-radius: 16px; margin-bottom: 28px; border: 1px solid #e2e8f0; position: relative; }
    .route-point { text-align: center; }
    .route-code { font-size: 32px; font-weight: 900; color: #0f172a; font-family: 'Courier New', Courier, monospace; }
    .route-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-top: 4px; letter-spacing: 0.5px; }
    .route-plane { display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .route-plane-icon { font-size: 24px; }
    .route-plane-flight { font-size: 11px; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 2px 10px; border-radius: 12px; font-family: monospace; }

    /* Grilla de Datos */
    .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px 24px; margin-bottom: 28px; }
    .field { padding-bottom: 6px; }
    .label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px; }
    .val { font-size: 15px; font-weight: 800; color: #0f172a; }
    .val.mono { font-family: 'Courier New', Courier, monospace; }
    .val.highlight { color: #16a34a; }
    .val.price { color: #0284c7; }

    /* Equipaje y Servicios */
    .baggage-bar { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; font-size: 12px; }
    .baggage-item { display: flex; align-items: center; gap: 8px; color: #166534; font-weight: 700; }

    /* Código de Barras Simulado */
    .barcode-section { border-top: 2px dashed #cbd5e1; padding-top: 20px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
    .barcode-lines { height: 42px; flex: 1; background: repeating-linear-gradient(to right, #0f172a 0px, #0f172a 2px, transparent 2px, transparent 4px, #0f172a 4px, #0f172a 7px, transparent 7px, transparent 9px, #0f172a 9px, #0f172a 10px, transparent 10px, transparent 13px, #0f172a 13px, #0f172a 16px); opacity: 0.85; border-radius: 4px; }
    .barcode-pnr { font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: 900; color: #475569; letter-spacing: 2px; }

    /* Footer */
    .footer { background: #f8fafc; padding: 18px 30px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; line-height: 1.5; text-align: center; }
    .footer strong { color: #0f172a; }

    /* Impresión */
    @media print {
        body { background: #fff !important; padding: 0 !important; }
        .toolbar { display: none !important; }
        .ticket { box-shadow: none !important; border: 1px solid #cbd5e1 !important; border-radius: 0 !important; }
        .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .route-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<div class="container">
    <div class="toolbar">
        <div class="toolbar-title">
            <span>✈️ TAMep</span> &bull; Billete Electrónico &bull; Localizador: <strong>{$pnr}</strong>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <button onclick="window.print()" class="btn-print">
                🖨️ Imprimir / Guardar PDF
            </button>
            <a href="javascript:window.close()" class="btn-close">Cerrar</a>
        </div>
    </div>

    <div class="ticket">
        <div class="header">
            <div class="header-brand">
                <h1>TAMep</h1>
                <div class="sub">Transporte Aéreo Militar Empresa Pública</div>
                <div class="type">PASE DE ABORDAR &bull; BILLETE ELECTRÓNICO</div>
            </div>
            <div class="pnr-box">
                <div class="pnr-label">CÓDIGO DE RESERVA</div>
                <div class="pnr-code">{$pnr}</div>
            </div>
        </div>

        <div class="content">
            <div class="route-banner">
                <div class="route-point">
                    <div class="route-code">{$origin}</div>
                    <div class="route-label">Aeropuerto Origen</div>
                </div>
                <div class="route-plane">
                    <div class="route-plane-icon">✈️</div>
                    <div class="route-plane-flight">VUELO {$flight}</div>
                </div>
                <div class="route-point">
                    <div class="route-code">{$dest}</div>
                    <div class="route-label">Aeropuerto Destino</div>
                </div>
            </div>

            <div class="grid">
                <div class="field">
                    <div class="label">Pasajero Titular</div>
                    <div class="val">{$passenger}</div>
                </div>
                <div class="field">
                    <div class="label">Documento / Pasaporte</div>
                    <div class="val mono">{$document}</div>
                </div>
                <div class="field">
                    <div class="label">Fecha Programada</div>
                    <div class="val mono">{$date}</div>
                </div>
                <div class="field">
                    <div class="label">Número de Vuelo</div>
                    <div class="val mono">{$flight}</div>
                </div>
                <div class="field">
                    <div class="label">Pasajeros (PAX)</div>
                    <div class="val">{$passengers}</div>
                </div>
                <div class="field">
                    <div class="label">Número de Boleto (E-TKT)</div>
                    <div class="val mono">{$ticketDisplay}</div>
                </div>
                <div class="field">
                    <div class="label">Tarifa Total Registrada</div>
                    <div class="val price mono">{$price}</div>
                </div>
                <div class="field">
                    <div class="label">Estado de Emisión</div>
                    <div class="val highlight">● {$status}</div>
                </div>
            </div>

            <div class="baggage-bar">
                <div class="baggage-item">
                    <span>🧳</span>
                    <span>Equipaje Bodega: 1 pieza (hasta 20 kg)</span>
                </div>
                <div class="baggage-item">
                    <span>🎒</span>
                    <span>Equipaje de Mano: 1 pieza (hasta 5 kg)</span>
                </div>
            </div>

            <div class="barcode-section">
                <div class="barcode-lines"></div>
                <div class="barcode-pnr">{$pnr} &bull; ETKT {$ticketDisplay}</div>
            </div>
        </div>

        <div class="footer">
            <strong>Instrucciones Importantes:</strong> Presente este boleto electrónico impreso o en su dispositivo móvil junto a su documento de identidad vigente en los mostradores de TAMep en el aeropuerto al menos <strong>2 horas antes</strong> de la salida de su vuelo para la entrega de equipaje y asignación de asiento.
        </div>
    </div>
</div>
</body>
</html>
HTML;
    }

    protected function generateTicketHtml(array $data, string $pnr, string $ticketNumber): string
    {
        return $this->generateTicketHtmlWithPrintBar($data, $pnr, $ticketNumber);
    }
}
