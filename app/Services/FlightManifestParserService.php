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

        // 1. Motor primario: Extracción nativa Pure PHP (100% portable, sin dependencias de Python ni pdftotext)
        try {
            $data = $this->parseWithPurePhp($pdfPath);
            if (! empty($data['success']) && ! empty($data['passengers'])) {
                return $data;
            }
        } catch (\Throwable $e) {
            Log::warning('Fallo motor Pure PHP para PDF, intentando motor Python', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Motor secundario: Extracción mediante Python (pypdf) con soporte Linux/Windows
        try {
            $data = $this->parseWithPython($pdfPath);
            if (! empty($data['success']) && ! empty($data['passengers'])) {
                return $data;
            }
        } catch (\Throwable $e) {
            Log::warning('Fallo motor Python para PDF, intentando motor pdftotext', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Fallback automático: Extracción mediante pdftotext nativo
        return $this->parseWithPdfToText($pdfPath);
    }

    /**
     * Motor nativo Pure PHP: Extrae y decodifica streams y CMaps de fuentes del PDF
     * sin depender de Python, pypdf ni pdftotext. 100% compatible con cPanel y Windows.
     *
     * @param  string  $pdfPath  Ruta al archivo PDF.
     * @return array Datos del vuelo y pasajeros.
     *
     * @throws Exception Si no se puede leer o parsear el PDF.
     */
    protected function parseWithPurePhp(string $pdfPath): array
    {
        $content = file_get_contents($pdfPath);
        if ($content === false) {
            throw new Exception("No se pudo leer el archivo: {$pdfPath}");
        }

        // 1. Extraer todos los objetos PDF
        preg_match_all('/(\d+)\s+(\d+)\s+obj(.*?)endobj/s', $content, $objs, PREG_SET_ORDER);
        $pdfObjects = [];
        foreach ($objs as $o) {
            $pdfObjects[$o[1]] = $o[3];
        }

        // 2. Encontrar páginas
        $pages = [];
        foreach ($pdfObjects as $id => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) && ! preg_match('/\/Type\s*\/Pages\b/', $body)) {
                $pages[$id] = $body;
            }
        }

        // 3. Mapear fuentes a ToUnicode
        $fontToUnicodeMap = [];
        foreach ($pdfObjects as $id => $body) {
            if (preg_match('/\/Type\s*\/Font\b/', $body)) {
                if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $body, $tum)) {
                    $fontToUnicodeMap[$id] = (int) $tum[1];
                }
            }
        }

        // 4. Parsear CMaps ToUnicode
        $cmapsByUnicodeObj = [];
        foreach ($fontToUnicodeMap as $fontId => $uniId) {
            if (! isset($cmapsByUnicodeObj[$uniId]) && isset($pdfObjects[$uniId])) {
                $uniBody = $pdfObjects[$uniId];
                if (preg_match('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $uniBody, $sm)) {
                    $decomp = @gzuncompress($sm[1]);
                    if ($decomp === false) {
                        $decomp = @gzinflate(substr($sm[1], 2));
                    }
                    if ($decomp !== false) {
                        $map = [];
                        // bfchar
                        if (preg_match_all('/<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>/', $decomp, $chars)) {
                            for ($i = 0; $i < count($chars[1]); $i++) {
                                $src = hexdec($chars[1][$i]);
                                $dst = hexdec($chars[2][$i]);
                                $map[$src] = mb_chr($dst, 'UTF-8');
                            }
                        }
                        // bfrange
                        if (preg_match_all('/<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>/', $decomp, $ranges)) {
                            for ($i = 0; $i < count($ranges[1]); $i++) {
                                $start = hexdec($ranges[1][$i]);
                                $end = hexdec($ranges[2][$i]);
                                $dst = hexdec($ranges[3][$i]);
                                for ($c = $start; $c <= $end; $c++) {
                                    $map[$c] = mb_chr($dst + ($c - $start), 'UTF-8');
                                }
                            }
                        }
                        $cmapsByUnicodeObj[$uniId] = $map;
                    }
                }
            }
        }

        // 5. Decodificar texto de cada página
        $fullText = '';
        foreach ($pages as $pageId => $pageBody) {
            $pageFonts = [];
            if (preg_match('/\/Font\s*<<([^>]*)>>/s', $pageBody, $fm)) {
                if (preg_match_all('/\/([A-Za-z0-9]+)\s+(\d+)\s+\d+\s+R/', $fm[1], $fontRefs)) {
                    for ($i = 0; $i < count($fontRefs[1]); $i++) {
                        $fName = '/'.$fontRefs[1][$i];
                        $fObjId = (int) $fontRefs[2][$i];
                        $uniObjId = $fontToUnicodeMap[$fObjId] ?? null;
                        if ($uniObjId && isset($cmapsByUnicodeObj[$uniObjId])) {
                            $pageFonts[$fName] = $cmapsByUnicodeObj[$uniObjId];
                        }
                    }
                }
            }

            $contentIds = [];
            if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $pageBody, $cm)) {
                $contentIds[] = (int) $cm[1];
            } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $pageBody, $cm)) {
                preg_match_all('/(\d+)\s+\d+\s+R/', $cm[1], $cRefs);
                foreach ($cRefs[1] as $cRef) {
                    $contentIds[] = (int) $cRef;
                }
            }

            foreach ($contentIds as $cId) {
                if (! isset($pdfObjects[$cId])) {
                    continue;
                }
                $cBody = $pdfObjects[$cId];
                if (! preg_match('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $cBody, $sm)) {
                    continue;
                }
                $decomp = @gzuncompress($sm[1]);
                if ($decomp === false) {
                    $decomp = @gzinflate(substr($sm[1], 2));
                }
                if ($decomp === false) {
                    continue;
                }

                if (preg_match_all('/BT(.*?)ET/s', $decomp, $bts)) {
                    foreach ($bts[1] as $block) {
                        $lines = explode("\n", trim($block));
                        $currentFont = null;
                        $currentCmap = [];
                        $blockStr = '';

                        foreach ($lines as $line) {
                            if (preg_match('/(\/F[0-9A-Za-z]+)\s+[\d.]+\s+Tf/', $line, $tf)) {
                                $currentFont = $tf[1];
                                $currentCmap = $pageFonts[$currentFont] ?? [];
                            }

                            if (preg_match_all('/<([0-9a-fA-F]+)>\s*(?:Tj|\')/', $line, $hexMatches)) {
                                foreach ($hexMatches[1] as $hex) {
                                    $len = strlen($hex);
                                    for ($k = 0; $k < $len; $k += 4) {
                                        $code = hexdec(substr($hex, $k, 4));
                                        $blockStr .= $currentCmap[$code] ?? '';
                                    }
                                }
                            } elseif (preg_match('/\[(.*?)\]\s*TJ/s', $line, $arrMatch)) {
                                if (preg_match_all('/<([0-9a-fA-F]+)>/', $arrMatch[1], $hexInArr)) {
                                    foreach ($hexInArr[1] as $hex) {
                                        $len = strlen($hex);
                                        for ($k = 0; $k < $len; $k += 4) {
                                            $code = hexdec(substr($hex, $k, 4));
                                            $blockStr .= $currentCmap[$code] ?? '';
                                        }
                                    }
                                }
                            }
                        }

                        if (trim($blockStr) !== '') {
                            $fullText .= $blockStr."\n";
                        }
                    }
                }
            }
        }

        if (empty(trim($fullText))) {
            throw new Exception('No se pudo extraer texto del PDF mediante motor nativo.');
        }

        // Limpiar caracteres de control no imprimibles
        $fullText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $fullText);

        // Número de vuelo
        $flightNum = '708';
        if (preg_match('/REPORTE\s+GENERAL\s+DEL\s+VUELO\s+([A-Z0-9-]+)/i', $fullText, $fm)) {
            $flightNum = trim($fm[1]);
        }

        // Fecha y hora de salida
        $departureDate = null;
        $departureDateRaw = null;
        $departureTime = null;
        if (preg_match('/Fecha\s+de\s+Salida[^\d]*(\d{2}\/\d{2}\/\d{4})\s*(\d{2}[:*]\d{2})?/i', $fullText, $dm)) {
            $departureDateRaw = $dm[1];
            $departureTime = isset($dm[2]) ? str_replace('*', ':', $dm[2]) : null;
            $parts = explode('/', $departureDateRaw);
            if (count($parts) === 3) {
                $departureDate = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        // Extraer filas tabulares
        preg_match_all('/(?m)^\s*(\d{1,3})\s*\n\s*([A-Z]{3})\s*\n\s*([A-Z]{3})\s*\n\s*([A-Z0-9]{5,8})\s*$/u', $fullText, $rowMatches, PREG_OFFSET_CAPTURE);

        $passengers = [];
        $defaultOrigin = 'VVI';
        $defaultDest = 'LPB';

        $count = count($rowMatches[0]);
        for ($i = 0; $i < $count; $i++) {
            $num = (int) $rowMatches[1][$i][0];
            $origin = trim($rowMatches[2][$i][0]);
            $dest = trim($rowMatches[3][$i][0]);
            $pnr = trim($rowMatches[4][$i][0]);
            $defaultOrigin = $origin;
            $defaultDest = $dest;

            $startPos = $rowMatches[0][$i][1] + strlen($rowMatches[0][$i][0]);
            $endPos = ($i + 1 < $count) ? $rowMatches[0][$i + 1][1] : strlen($fullText);
            $block = trim(substr($fullText, $startPos, $endPos - $startPos));

            // Normalizar líneas y guiones cortados
            $blockClean = preg_replace('/-\s*\n\s*/', '-', $block);
            $cleanLines = array_values(array_filter(array_map('trim', explode("\n", $blockClean))));

            $tarifa = 'TAMEP-ADT';
            $tarifaIdx = -1;
            foreach ($cleanLines as $idx => $line) {
                if (preg_match('/\b([A-Z0-9]*(?:TAMEP|SENIOR|SERV|WAY|BCHD)[A-Z0-9-]*)\b/u', $line, $tm)) {
                    $tarifa = $tm[1];
                    $tarifaIdx = $idx;
                    break;
                }
            }

            $nameParts = [];
            $restLines = [];
            if ($tarifaIdx !== -1) {
                for ($k = 0; $k < $tarifaIdx; $k++) {
                    $nameParts[] = $cleanLines[$k];
                }
                for ($k = $tarifaIdx + 1; $k < count($cleanLines); $k++) {
                    $restLines[] = $cleanLines[$k];
                }
            } else {
                $nameParts = $cleanLines;
            }

            $passengerName = trim(preg_replace('/\s+/u', ' ', implode(' ', $nameParts)));
            $restText = implode(' ', $restLines);

            // DNI (5-9 dígitos)
            $dni = null;
            if (preg_match('/\b(\d{5,9}[A-Z]?)\b/u', $restText, $dnm)) {
                $dni = $dnm[1];
            }

            // Teléfono boliviano
            $phone = null;
            if (preg_match('/(?:\+?591[\s-]*)?([67]\d{7})\b/u', $restText, $pm)) {
                $phone = $pm[1];
            }

            // Email
            $email = null;
            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u', $restText, $em)) {
                $email = strtolower(trim($em[0]));
            }

            // Apellido paterno
            $words = explode(' ', $passengerName);
            if (empty($words) || empty($words[0])) {
                $paternalSurname = 'Pasajero';
            } elseif (strtoupper($words[0]) === 'DE' && isset($words[1]) && strtoupper($words[1]) === 'LA' && isset($words[2])) {
                $paternalSurname = "{$words[0]} {$words[1]} {$words[2]}";
            } elseif (in_array(strtoupper($words[0]), ['DE', 'DEL']) && isset($words[1])) {
                $paternalSurname = "{$words[0]} {$words[1]}";
            } else {
                $paternalSurname = $words[0];
            }

            $passengers[] = [
                'num' => $num,
                'pnr' => $pnr,
                'origin' => $origin,
                'destination' => $dest,
                'passenger_name' => $passengerName,
                'paternal_surname' => $paternalSurname,
                'tarifa' => $tarifa,
                'dni' => $dni,
                'phone' => $phone,
                'email' => $email,
            ];
        }

        $validPhones = array_filter($passengers, fn ($p) => ! empty($p['phone']));

        $airportCities = [
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

        return [
            'success' => true,
            'flight_number' => $flightNum,
            'departure_date' => $departureDate,
            'departure_date_raw' => $departureDateRaw,
            'departure_time' => $departureTime,
            'origin' => $defaultOrigin,
            'destination' => $defaultDest,
            'origin_city' => $airportCities[strtoupper($defaultOrigin)] ?? $defaultOrigin,
            'destination_city' => $airportCities[strtoupper($defaultDest)] ?? $defaultDest,
            'total_passengers' => count($passengers),
            'valid_phones_count' => count($validPhones),
            'passengers' => $passengers,
        ];
    }

    /**
     * Motor de extracción secundario mediante Python 3 y pypdf.
     */
    protected function parseWithPython(string $pdfPath): array
    {
        $scriptPath = app_path('Scripts/parse_flight_pdf.py');
        if (! file_exists($scriptPath)) {
            throw new Exception("El script de extracción no se encuentra en: {$scriptPath}");
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $defaultPath = $isWindows
            ? 'C:\\Windows\\system32;C:\\Windows'
            : '/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin';

        $env = array_merge($_SERVER, [
            'PATH' => getenv('PATH') ?: $defaultPath,
            'PYTHONHASHSEED' => '0',
            'PYTHONIOENCODING' => 'utf-8',
        ]);

        if ($isWindows) {
            $env['SYSTEMROOT'] = getenv('SYSTEMROOT') ?: 'C:\\Windows';
            $env['WINDIR'] = getenv('WINDIR') ?: 'C:\\Windows';
            $env['TEMP'] = getenv('TEMP') ?: 'C:\\Windows\\Temp';
            $env['TMP'] = getenv('TMP') ?: 'C:\\Windows\\Temp';
        }

        $pythonBin = $isWindows ? 'python' : ($this->findLinuxPython() ?? 'python3');

        $process = new Process([$pythonBin, $scriptPath, $pdfPath], null, $env);
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
     * Motor de extracción alternativo mediante pdftotext (Xpdf/Poppler).
     */
    protected function parseWithPdfToText(string $pdfPath): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $defaultPath = $isWindows
            ? 'C:\\Windows\\system32;C:\\Windows'
            : '/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin';

        $env = array_merge($_SERVER, [
            'PATH' => getenv('PATH') ?: $defaultPath,
        ]);

        if ($isWindows) {
            $env['SYSTEMROOT'] = getenv('SYSTEMROOT') ?: 'C:\\Windows';
            $env['WINDIR'] = getenv('WINDIR') ?: 'C:\\Windows';
        }

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

                // Apellido paterno
                $nameParts = preg_split('/\s+/u', $name);
                if (empty($nameParts) || empty($nameParts[0])) {
                    $paternalSurname = 'Pasajero';
                } elseif (strtoupper($nameParts[0]) === 'DE' && isset($nameParts[1]) && strtoupper($nameParts[1]) === 'LA' && isset($nameParts[2])) {
                    $paternalSurname = "{$nameParts[0]} {$nameParts[1]} {$nameParts[2]}";
                } elseif (in_array(strtoupper($nameParts[0]), ['DE', 'DEL']) && isset($nameParts[1])) {
                    $paternalSurname = "{$nameParts[0]} {$nameParts[1]}";
                } else {
                    $paternalSurname = $nameParts[0];
                }

                $passengers[] = [
                    'num' => $num,
                    'pnr' => $pnr,
                    'origin' => $origin,
                    'destination' => $dest,
                    'passenger_name' => $name,
                    'paternal_surname' => $paternalSurname,
                    'tarifa' => $tarifa,
                    'dni' => $dni,
                    'phone' => $phone,
                    'email' => $email,
                ];
            }
        }

        $validPhones = array_filter($passengers, fn ($p) => ! empty($p['phone']));

        $airportCities = [
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

        return [
            'success' => true,
            'flight_number' => $flightNum,
            'departure_date' => $departureDate,
            'departure_date_raw' => $departureDateRaw,
            'departure_time' => $departureTime,
            'origin' => $defaultOrigin,
            'destination' => $defaultDest,
            'origin_city' => $airportCities[strtoupper($defaultOrigin)] ?? $defaultOrigin,
            'destination_city' => $airportCities[strtoupper($defaultDest)] ?? $defaultDest,
            'total_passengers' => count($passengers),
            'valid_phones_count' => count($validPhones),
            'passengers' => $passengers,
        ];
    }

    /**
     * Localiza el binario de Python 3 en entornos Linux/cPanel.
     */
    protected function findLinuxPython(): ?string
    {
        $candidates = [
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            '/bin/python3',
            '/usr/bin/python',
        ];

        foreach ($candidates as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        return 'python3';
    }
}
