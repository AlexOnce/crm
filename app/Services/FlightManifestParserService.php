<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class FlightManifestParserService
{
    /**
     * Parsea un archivo PDF de manifiesto/reporte de vuelo de TAMep.
     * Soporta extracción híbrida (Python pypdf con fallback automático a pdftotext).
     *
     * @param  string  $pdfPath  Ruta absoluta o relativa al archivo PDF.
     * @return array Resumen del vuelo y lista de pasajeros con teléfonos bolivianos.
     *
     * @throws Exception Si el archivo no existe o no se puede procesar con ningún motor.
     */
    public function parsePdf(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new Exception("El archivo PDF no fue encontrado en: {$pdfPath}");
        }

        // 1. Intentar extracción mediante Python (pypdf) con variables de entorno de Windows explícitas
        try {
            $data = $this->parseWithPython($pdfPath);
            if (! empty($data['success']) && ! empty($data['passengers'])) {
                return $data;
            }
        } catch (Exception $e) {
            Log::warning('Fallo motor Python para PDF, intentando motor pdftotext', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Fallback automático: Extracción mediante pdftotext nativo
        return $this->parseWithPdfToText($pdfPath);
    }

    /**
     * Motor de extracción primario mediante Python 3 y pypdf.
     */
    protected function parseWithPython(string $pdfPath): array
    {
        $scriptPath = app_path('Scripts/parse_flight_pdf.py');
        if (! file_exists($scriptPath)) {
            throw new Exception("El script de extracción no se encuentra en: {$scriptPath}");
        }

        $env = array_merge($_SERVER, [
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: 'C:\\Windows\\system32;C:\\Windows',
            'TEMP' => getenv('TEMP') ?: 'C:\\Windows\\Temp',
            'TMP' => getenv('TMP') ?: 'C:\\Windows\\Temp',
            'PYTHONHASHSEED' => '0',
            'PYTHONIOENCODING' => 'utf-8',
        ]);

        $process = new Process(['python', $scriptPath, $pdfPath], null, $env);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();
            throw new Exception("Fallo en ejecución de Python: {$errorOutput}");
        }

        $rawOutput = trim($process->getOutput());
        $data = json_decode($rawOutput, true);

        if (! is_array($data)) {
            throw new Exception('El procesador de Python devolvió un formato no reconocible.');
        }

        if (! empty($data['error'])) {
            throw new Exception($data['error']);
        }

        return $data;
    }

    /**
     * Motor de extracción alternativo mediante pdftotext (Xpdf/Poppler en Windows).
     */
    protected function parseWithPdfToText(string $pdfPath): array
    {
        $env = array_merge($_SERVER, [
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: 'C:\\Windows\\system32;C:\\Windows',
        ]);

        $process = new Process(['pdftotext', '-layout', $pdfPath, '-'], null, $env);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = $process->getErrorOutput() ?: $process->getOutput();
            throw new Exception("No se pudo leer el archivo PDF con pdftotext: {$error}");
        }

        $text = $process->getOutput();

        // 1. Extraer número de vuelo
        $flightNum = '708';
        if (preg_match('/REPORTE\s+GENERAL\s+DEL\s+VUELO\s+([A-Z0-9-]+)/i', $text, $fm)) {
            $flightNum = trim($fm[1]);
        }

        // 2. Extraer fecha y hora de salida
        $departureDate = null;
        $departureDateRaw = null;
        $departureTime = null;
        if (preg_match('/Fecha\s+de\s+Salida:\s*(\d{2}\/\d{2}\/\d{4})\s*(\d{2}:\d{2})?/i', $text, $dm)) {
            $departureDateRaw = $dm[1];
            $departureTime = $dm[2] ?? null;
            $parts = explode('/', $departureDateRaw);
            if (count($parts) === 3) {
                $departureDate = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        // 3. Extraer pasajeros dividiendo por inicio de fila tabular
        preg_match_all('/(?m)^\s*(\d{1,3})\s+([A-Z]{3})\s+([A-Z]{3})\s+([A-Z0-9]{5,8})\s+/u', $text, $allMatches, PREG_OFFSET_CAPTURE);

        $passengers = [];
        $defaultOrigin = 'VVI';
        $defaultDest = 'LPB';

        if (! empty($allMatches[0])) {
            $count = count($allMatches[0]);
            for ($i = 0; $i < $count; $i++) {
                $num = (int) $allMatches[1][$i][0];
                $origin = trim($allMatches[2][$i][0]);
                $dest = trim($allMatches[3][$i][0]);
                $pnr = trim($allMatches[4][$i][0]);
                $defaultOrigin = $origin;
                $defaultDest = $dest;

                $start = $allMatches[0][$i][1] + strlen($allMatches[0][$i][0]);
                $end = ($i + 1 < $count) ? $allMatches[0][$i + 1][1] : strlen($text);
                $block = trim(substr($text, $start, $end - $start));

                // Tarifa
                $tarifa = 'TAMEP-ADT';
                if (preg_match('/\b([A-Z0-9]*(?:TAMEP|SENIOR|SERV|WAY|BCHD)[A-Z0-9-]*)\b/u', $block, $tm)) {
                    $tarifa = $tm[1];
                    $pos = strpos($block, $tarifa);
                    $nameRaw = substr($block, 0, $pos);
                    $rest = substr($block, $pos + strlen($tarifa));
                } else {
                    $nameRaw = $block;
                    $rest = $block;
                }

                $name = trim(preg_replace('/\s+/u', ' ', $nameRaw));

                // Celular boliviano
                $phone = null;
                if (preg_match('/(?:\+?591[\s-]*)?([67]\d{7})\b/u', $rest, $pm)) {
                    $phone = $pm[1];
                }

                // Email
                $email = null;
                if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u', $rest, $em)) {
                    $email = strtolower(trim($em[0]));
                }

                // DNI
                $dni = null;
                if (preg_match('/\b(\d{5,9}[A-Z]?)\b/u', $rest, $dnm)) {
                    $dni = $dnm[1];
                    if ($phone && ($dni === $phone || $dni === '591'.$phone)) {
                        preg_match_all('/\b(\d{5,9}[A-Z]?)\b/u', $rest, $allNums);
                        $dni = $allNums[1][1] ?? null;
                    }
                }

                $passengers[] = [
                    'num' => $num,
                    'pnr' => $pnr,
                    'origin' => $origin,
                    'destination' => $dest,
                    'passenger_name' => $name,
                    'tarifa' => $tarifa,
                    'dni' => $dni,
                    'phone' => $phone,
                    'email' => $email,
                ];
            }
        }

        $validPhones = array_filter($passengers, fn ($p) => ! empty($p['phone']));

        return [
            'success' => true,
            'flight_number' => $flightNum,
            'departure_date' => $departureDate,
            'departure_date_raw' => $departureDateRaw,
            'departure_time' => $departureTime,
            'origin' => $defaultOrigin,
            'destination' => $defaultDest,
            'total_passengers' => count($passengers),
            'valid_phones_count' => count($validPhones),
            'passengers' => $passengers,
        ];
    }
}
