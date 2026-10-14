<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class FlightManifestParserService
{
    /**
     * Parsea un archivo PDF de manifiesto/reporte de vuelo de TAMep.
     *
     * @param  string  $pdfPath  Ruta absoluta o relativa al archivo PDF.
     * @return array Resumen del vuelo y lista de pasajeros con teléfonos bolivianos.
     *
     * @throws Exception Si el archivo no existe o no se puede procesar.
     */
    public function parsePdf(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new Exception("El archivo PDF no fue encontrado en: {$pdfPath}");
        }

        $scriptPath = app_path('Scripts/parse_flight_pdf.py');
        if (! file_exists($scriptPath)) {
            throw new Exception("El script de extracción no se encuentra en: {$scriptPath}");
        }

        // Ejecutar Python 3 para extracción de texto con pypdf
        $process = new Process(['python', $scriptPath, $pdfPath]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('Error en parse_flight_pdf.py', [
                'path' => $pdfPath,
                'error' => $errorOutput,
            ]);

            throw new Exception("Fallo al procesar el archivo PDF: {$errorOutput}");
        }

        $rawOutput = trim($process->getOutput());
        $data = json_decode($rawOutput, true);

        if (! is_array($data)) {
            Log::error('Respuesta no JSON de parse_flight_pdf.py', [
                'output' => substr($rawOutput, 0, 500),
            ]);

            throw new Exception('El procesador de PDF devolvió un formato no reconocible.');
        }

        if (! empty($data['error'])) {
            throw new Exception($data['error']);
        }

        return $data;
    }
}
