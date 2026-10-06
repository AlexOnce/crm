<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MetaWhatsAppService
{
    protected string $token;

    protected string $phoneNumberId;

    protected string $version;

    protected string $apiUrl;

    protected string $wabaId;

    public function __construct()
    {
        $this->token = (string) config('services.meta.token', '');
        $this->phoneNumberId = (string) config('services.meta.phone_number_id', '');
        $this->wabaId = (string) config('services.meta.waba_id', '');
        $this->version = (string) config('services.meta.version', 'v20.0');
        $this->apiUrl = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages";
    }

    public function setPhoneNumberId(string $id): self
    {
        $this->phoneNumberId = $id;
        $this->apiUrl = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages";

        return $this;
    }

    public function getPhoneNumberId(): string
    {
        return $this->phoneNumberId;
    }

    /**
     * Verifica el estado y detalles del número en Meta Cloud API
     */
    public function getPhoneNumberDetails(): array
    {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            return ['error' => 'Credenciales de Meta no configuradas'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(8)
                ->get("https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}");

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Verifica los detalles de la cuenta de WhatsApp Business (WABA)
     */
    public function getWabaDetails(): array
    {
        if (empty($this->token) || empty($this->wabaId)) {
            return ['error' => 'Credenciales WABA no configuradas'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(8)
                ->get("https://graph.facebook.com/{$this->version}/{$this->wabaId}");

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Envía un mensaje de texto oficial a través de Meta Cloud API
     */
    public function sendMessage(string $phone, string $text): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            Log::warning("MetaWhatsAppService: Número de teléfono inválido: {$phone}");

            return false;
        }

        // Si no se han configurado credenciales reales aún, loguear para pruebas locales
        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Mensaje a {$cleanNumber}: {$text}");

            return true;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(8)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $text,
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendMessage falló', [
                'status' => $response->status(),
                'body' => $response->json(),
                'phone' => $cleanNumber,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendMessage excepción: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return false;
        }
    }

    /**
     * Envía botones interactivos oficiales a través de Meta Cloud API (WhatsApp Interactive Quick Reply Buttons)
     *
     * @param  string  $phone  Número telefónico del destinatario
     * @param  string  $bodyText  Texto principal del mensaje
     * @param  array  $buttons  Lista de botones [['id' => '...', 'title' => '...']] (Máx. 3 botones, máx. 20 caracteres por título)
     * @param  string|null  $headerText  Encabezado opcional (máx. 60 caracteres)
     * @param  string|null  $footerText  Pie de página opcional (máx. 60 caracteres)
     */
    public function sendInteractiveButtons(
        string $phone,
        string $bodyText,
        array $buttons,
        ?string $headerText = null,
        ?string $footerText = null
    ): bool {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            Log::warning("MetaWhatsAppService: Número de teléfono inválido: {$phone}");

            return false;
        }

        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Botones interactivos a {$cleanNumber}: {$bodyText}", [
                'buttons' => $buttons,
            ]);

            return true;
        }

        try {
            // Formatear botones según la especificación de Meta (máx 3 botones, título <= 20 caracteres)
            $formattedButtons = [];
            foreach (array_slice($buttons, 0, 3) as $index => $btn) {
                $id = (string) ($btn['id'] ?? 'btn_'.($index + 1));
                $title = mb_substr((string) ($btn['title'] ?? 'Opción '.($index + 1)), 0, 20);

                $formattedButtons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $id,
                        'title' => $title,
                    ],
                ];
            }

            $interactive = [
                'type' => 'button',
                'body' => [
                    'text' => $bodyText,
                ],
                'action' => [
                    'buttons' => $formattedButtons,
                ],
            ];

            if (! empty($headerText)) {
                $interactive['header'] = [
                    'type' => 'text',
                    'text' => mb_substr($headerText, 0, 60),
                ];
            }

            if (! empty($footerText)) {
                $interactive['footer'] = [
                    'text' => mb_substr($footerText, 0, 60),
                ];
            }

            $response = Http::withToken($this->token)
                ->timeout(8)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'interactive',
                    'interactive' => $interactive,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendInteractiveButtons falló', [
                'status' => $response->status(),
                'body' => $response->json(),
                'phone' => $cleanNumber,
            ]);

            return $this->sendMessage($phone, $bodyText);
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendInteractiveButtons excepción: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return $this->sendMessage($phone, $bodyText);
        }
    }

    /**
     * Envía un menú interactivo tipo lista a través de Meta Cloud API (WhatsApp Interactive List)
     */
    public function sendInteractiveList(
        string $phone,
        string $bodyText,
        string $buttonText,
        array $sections,
        ?string $headerText = null,
        ?string $footerText = null
    ): bool {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            return false;
        }

        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Lista interactiva a {$cleanNumber}: {$bodyText}");

            return true;
        }

        try {
            $interactive = [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => mb_substr($buttonText, 0, 20),
                    'sections' => $sections,
                ],
            ];

            if (! empty($headerText)) {
                $interactive['header'] = [
                    'type' => 'text',
                    'text' => mb_substr($headerText, 0, 60),
                ];
            }

            if (! empty($footerText)) {
                $interactive['footer'] = [
                    'text' => mb_substr($footerText, 0, 60),
                ];
            }

            $response = Http::withToken($this->token)
                ->timeout(8)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'interactive',
                    'interactive' => $interactive,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendInteractiveList falló', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $this->sendMessage($phone, $bodyText);
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendInteractiveList excepción: '.$e->getMessage());

            return $this->sendMessage($phone, $bodyText);
        }
    }

    /**
     * Envía un mensaje interactivo de tipo WhatsApp Flow a través de Meta Cloud API
     *
     * @param  string  $phone  Número telefónico del destinatario
     * @param  string  $bodyText  Texto explicativo del cuerpo del mensaje
     * @param  string  $flowId  ID del Flow registrado y publicado en Meta Business Suite
     * @param  string  $flowCta  Texto del botón que abre el Flow (máx. 20 caracteres)
     * @param  string  $flowToken  Token único de seguimiento de sesión
     * @param  string|null  $headerText  Encabezado opcional (máx. 60 caracteres)
     * @param  string|null  $footerText  Pie de página opcional (máx. 60 caracteres)
     * @param  string  $mode  Modo del Flow: 'published' o 'draft'
     * @param  string|null  $screenId  ID de la pantalla inicial opcional
     * @param  array  $flowActionPayload  Datos adicionales opcionales para inicializar la pantalla
     */
    public function sendFlowMessage(
        string $phone,
        string $bodyText,
        string $flowId,
        string $flowCta = '📝 Llenar Datos',
        string $flowToken = '',
        ?string $headerText = null,
        ?string $footerText = null,
        string $mode = 'published',
        ?string $screenId = 'PASSENGER_INFO',
        array $flowActionPayload = []
    ): bool {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            Log::warning("MetaWhatsAppService: Número de teléfono inválido: {$phone}");

            return false;
        }

        if (empty($flowId)) {
            Log::warning('MetaWhatsAppService::sendFlowMessage: flowId vacío, usando fallback de texto.');

            return $this->sendMessage($phone, $bodyText);
        }

        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Flow Interactivo a {$cleanNumber}: {$bodyText} [Flow ID: {$flowId}]");

            return true;
        }

        try {
            $parameters = [
                'flow_message_version' => '3',
                'flow_token' => ! empty($flowToken) ? (string) $flowToken : 'flow_'.uniqid(),
                'flow_id' => (string) $flowId,
                'flow_cta' => mb_substr($flowCta, 0, 20),
                'mode' => in_array($mode, ['draft', 'published']) ? $mode : 'published',
            ];

            if (! empty($screenId)) {
                $parameters['flow_action'] = 'navigate';
                $parameters['flow_action_payload'] = array_merge([
                    'screen' => $screenId,
                ], ! empty($flowActionPayload) ? ['data' => $flowActionPayload] : []);
            }

            $interactive = [
                'type' => 'flow',
                'body' => [
                    'text' => $bodyText,
                ],
                'action' => [
                    'name' => 'flow',
                    'parameters' => $parameters,
                ],
            ];

            if (! empty($headerText)) {
                $interactive['header'] = [
                    'type' => 'text',
                    'text' => mb_substr($headerText, 0, 60),
                ];
            }

            if (! empty($footerText)) {
                $interactive['footer'] = [
                    'text' => mb_substr($footerText, 0, 60),
                ];
            }

            $response = Http::withToken($this->token)
                ->timeout(8)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'interactive',
                    'interactive' => $interactive,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendFlowMessage falló', [
                'status' => $response->status(),
                'body' => $response->json(),
                'phone' => $cleanNumber,
                'flowId' => $flowId,
            ]);

            return $this->sendMessage($phone, $bodyText);
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendFlowMessage excepción: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return $this->sendMessage($phone, $bodyText);
        }
    }

    /**
     * Envía un mensaje basado en plantilla oficial de WhatsApp (requerido para iniciar conversación fuera de la ventana de 24h)
     */
    public function sendTemplate(string $phone, string $templateName = 'hello_world', string $languageCode = 'en_US', array $components = []): array
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            return ['success' => false, 'error' => 'Número de teléfono inválido'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(8)
                ->post($this->apiUrl, $payload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Envía un documento/PDF oficial (ej: boleto aéreo emitido) a través de Meta Cloud API
     */
    public function sendDocument(string $phone, string $documentUrl, string $fileName, string $caption = ''): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            return false;
        }

        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Documento a {$cleanNumber} ({$fileName}): {$documentUrl}");

            return true;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'document',
                    'document' => [
                        'link' => $documentUrl,
                        'filename' => $fileName,
                        'caption' => $caption,
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendDocument falló', [
                'status' => $response->status(),
                'body' => $response->json(),
                'phone' => $cleanNumber,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendDocument excepción: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return false;
        }
    }

    /**
     * Envía una imagen oficial (ej: comprobante o foto de boleto) a través de Meta Cloud API
     */
    public function sendImage(string $phone, string $imageUrl, string $caption = '', ?string $localFilePath = null): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            return false;
        }

        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Imagen a {$cleanNumber}: {$imageUrl} (Caption: {$caption})");

            return true;
        }

        try {
            $imagePayload = [];

            // Si se cuenta con archivo local, intentar subirlo directamente a Meta Media API
            if ($localFilePath && file_exists($localFilePath)) {
                $mediaId = $this->uploadMedia($localFilePath);
                if ($mediaId) {
                    $imagePayload['id'] = $mediaId;
                }
            }

            // Fallback a enlace público directo
            if (empty($imagePayload)) {
                $imagePayload['link'] = $imageUrl;
            }

            if (! empty($caption)) {
                $imagePayload['caption'] = $caption;
            }

            $response = Http::withToken($this->token)
                ->timeout(12)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'image',
                    'image' => $imagePayload,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendImage falló', [
                'status' => $response->status(),
                'body' => $response->json(),
                'phone' => $cleanNumber,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendImage excepción: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return false;
        }
    }

    /**
     * Envía una nota de voz o audio a través de Meta Cloud API
     */
    public function sendAudio(string $phone, string $audioUrl, ?string $localFilePath = null): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phone);
        if (empty($cleanNumber)) {
            return false;
        }

        if (empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            Log::info("[META CLOUD API SIMULACIÓN] Audio a {$cleanNumber}: {$audioUrl}");

            return true;
        }

        try {
            $audioPayload = [];

            if ($localFilePath && file_exists($localFilePath)) {
                $mime = function_exists('mime_content_type') ? (mime_content_type($localFilePath) ?: 'audio/ogg') : 'audio/ogg';
                $mediaId = $this->uploadMedia($localFilePath, $mime);
                if ($mediaId) {
                    $audioPayload['id'] = $mediaId;
                }
            }

            if (empty($audioPayload)) {
                $audioPayload['link'] = $audioUrl;
            }

            $response = Http::withToken($this->token)
                ->timeout(12)
                ->post($this->apiUrl, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanNumber,
                    'type' => 'audio',
                    'audio' => $audioPayload,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MetaWhatsAppService::sendAudio falló', [
                'status' => $response->status(),
                'body' => $response->json(),
                'phone' => $cleanNumber,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::sendAudio excepción: '.$e->getMessage(), [
                'phone' => $cleanNumber,
            ]);

            return false;
        }
    }

    /**
     * Descarga un archivo multimedia desde Meta Cloud API usando el media ID
     * y lo almacena de forma persistente en el disco público de Laravel.
     */
    public function downloadMedia(string $mediaId, string $folder = 'audio'): ?string
    {
        if (empty($mediaId) || empty($this->token) || $this->token === 'tu_token_de_meta_aqui') {
            return null;
        }

        try {
            // 1. Obtener la URL temporal del archivo desde Graph API
            $metaMediaUrl = "https://graph.facebook.com/{$this->version}/{$mediaId}";
            $infoResponse = Http::withToken($this->token)
                ->timeout(12)
                ->get($metaMediaUrl);

            if (! $infoResponse->successful()) {
                Log::warning("MetaWhatsAppService::downloadMedia falló al obtener metadatos de {$mediaId}", [
                    'status' => $infoResponse->status(),
                    'body' => $infoResponse->json(),
                ]);

                return null;
            }

            $downloadUrl = $infoResponse->json('url');
            $mimeType = $infoResponse->json('mime_type') ?? '';

            if (empty($downloadUrl)) {
                return null;
            }

            // 2. Determinar la extensión según el MIME
            $extension = $this->guessExtensionFromMime($mimeType, $folder);

            // 3. Descargar el binario con el token Bearer requerido por Meta
            $fileResponse = Http::withToken($this->token)
                ->timeout(25)
                ->get($downloadUrl);

            if (! $fileResponse->successful()) {
                Log::warning("MetaWhatsAppService::downloadMedia falló al descargar binario desde {$downloadUrl}", [
                    'status' => $fileResponse->status(),
                ]);

                return null;
            }

            // 4. Guardar en storage/app/public/{folder}/
            $filename = "{$folder}_".time().'_'.Str::random(8).".{$extension}";
            $storagePath = "{$folder}/{$filename}";

            Storage::disk('public')->put($storagePath, $fileResponse->body());

            return asset("storage/{$storagePath}");
        } catch (\Throwable $e) {
            Log::error('MetaWhatsAppService::downloadMedia excepción: '.$e->getMessage(), [
                'media_id' => $mediaId,
            ]);

            return null;
        }
    }

    /**
     * Deduce la extensión de archivo adecuada según el tipo MIME
     */
    protected function guessExtensionFromMime(string $mime, string $defaultFolder = 'audio'): string
    {
        $mime = strtolower(trim($mime));

        if (str_contains($mime, 'ogg')) {
            return 'ogg';
        }
        if (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
            return 'mp3';
        }
        if (str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac')) {
            return 'm4a';
        }
        if (str_contains($mime, 'wav')) {
            return 'wav';
        }
        if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
            return 'jpg';
        }
        if (str_contains($mime, 'png')) {
            return 'png';
        }
        if (str_contains($mime, 'webp')) {
            return 'webp';
        }
        if (str_contains($mime, 'pdf')) {
            return 'pdf';
        }

        return $defaultFolder === 'audio' ? 'ogg' : 'bin';
    }

    /**
     * Sube un archivo multimedia binario a Meta Graph API Media Endpoint
     */
    public function uploadMedia(string $filePath, string $type = 'image/jpeg'): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        try {
            $mediaUrl = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/media";
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($mediaUrl, [
                    'messaging_product' => 'whatsapp',
                    'type' => $type,
                ]);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('MetaWhatsAppService::uploadMedia falló', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('MetaWhatsAppService::uploadMedia excepción: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Obtiene la lista de WhatsApp Flows asociados a la cuenta de WhatsApp Business (WABA)
     */
    public function listFlows(): array
    {
        if (empty($this->token) || empty($this->wabaId)) {
            return ['error' => 'Credenciales de Meta o WABA no configuradas'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->get("https://graph.facebook.com/{$this->version}/{$this->wabaId}/flows");

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Crea un nuevo WhatsApp Flow en Meta Cloud API
     */
    public function createFlow(string $name, array $categories = ['CUSTOMER_SUPPORT']): array
    {
        if (empty($this->token) || empty($this->wabaId)) {
            return ['error' => 'Credenciales de Meta o WABA no configuradas'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$this->version}/{$this->wabaId}/flows", [
                    'name' => $name,
                    'categories' => $categories,
                ]);

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Sube el JSON de estructura de pantallas a un WhatsApp Flow existente en Meta
     */
    public function uploadFlowJson(string $flowId, string $jsonContent): array
    {
        if (empty($this->token)) {
            return ['error' => 'Token de Meta no configurado'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->attach('file', $jsonContent, 'flow.json')
                ->post("https://graph.facebook.com/{$this->version}/{$flowId}/assets", [
                    'name' => 'flow.json',
                    'asset_type' => 'FLOW_JSON',
                ]);

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Publica un WhatsApp Flow para que pueda ser enviado a clientes en producción
     */
    public function publishFlow(string $flowId): array
    {
        if (empty($this->token)) {
            return ['error' => 'Token de Meta no configurado'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$this->version}/{$flowId}/publish");

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Obtiene los detalles de un Flow específico en Meta
     */
    public function getFlowDetails(string $flowId): array
    {
        if (empty($this->token)) {
            return ['error' => 'Token de Meta no configurado'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->get("https://graph.facebook.com/{$this->version}/{$flowId}?fields=id,name,status,categories,validation_errors");

            return $response->json() ?? ['error' => 'Respuesta vacía de Meta'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Sanitiza el número telefónico a formato internacional puro (E.164 numérico sin +)
     */
    public function cleanPhoneNumber(string $phone): string
    {
        return preg_replace('/[^\d]/', '', $phone);
    }
}
