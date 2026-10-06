<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $instance;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.evolution.url', 'http://127.0.0.1:8080'), '/');
        $this->apiKey = (string) config('services.evolution.api_key', '');
        $this->instance = (string) config('services.evolution.instance', 'airline_prod');
    }

    /**
     * Envía un mensaje de texto simple o formateado por WhatsApp
     */
    public function sendMessage(string $phone, string $text): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            Log::warning("EvolutionApiService: Número de teléfono inválido: {$phone}");

            return false;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(4)
                ->post("{$this->baseUrl}/message/sendText/{$this->instance}", [
                    'number' => $cleanNumber,
                    'text' => $text,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("EvolutionApiService::sendMessage falló con status {$response->status()}", [
                'body' => $response->body(),
                'phone' => $cleanNumber,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('EvolutionApiService::sendMessage error de conexión: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return false;
        }
    }

    /**
     * Envía un documento o PDF (por ejemplo, el boleto aéreo emitido)
     */
    public function sendDocument(string $phone, string $mediaUrlOrBase64, string $fileName, string $caption = ''): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(8)
                ->post("{$this->baseUrl}/message/sendMedia/{$this->instance}", [
                    'number' => $cleanNumber,
                    'media' => $mediaUrlOrBase64,
                    'mediatype' => 'document',
                    'mimetype' => 'application/pdf',
                    'fileName' => $fileName,
                    'caption' => $caption,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('EvolutionApiService::sendDocument error: '.$e->getMessage(), [
                'phone' => $cleanNumber,
                'fileName' => $fileName,
            ]);

            return false;
        }
    }

    /**
     * Sanitiza el número a formato internacional numérico puro
     */
    protected function cleanPhoneNumber(string $phone): string
    {
        return preg_replace('/[^\d]/', '', $phone);
    }
}
