<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class IssabelAmiService
{
    /**
     * IP o Dominio del servidor Issabel PBX.
     */
    protected string $host;

    /**
     * Puerto TCP de Asterisk Manager Interface (AMI).
     */
    protected int $port;

    /**
     * Usuario de autenticación AMI.
     */
    protected string $username;

    /**
     * Contraseña del usuario AMI.
     */
    protected string $secret;

    /**
     * Extensión remitente por defecto.
     */
    protected string $sourceExtension;

    /**
     * Controlador SIP ('sip' o 'pjsip').
     */
    protected string $sipDriver;

    /**
     * Contexto de marcación interna.
     */
    protected string $context;

    /**
     * Tiempo límite de espera de conexión (segundos).
     */
    protected int $timeout;

    /**
     * URL del puente HTTPS (Puerto 443) alojado en Issabel para hostings con puerto 5038 bloqueado.
     */
    protected string $bridgeUrl;

    public function __construct()
    {
        $this->host = (string) config('services.issabel.host', env('ISSABEL_HOST', '186.121.251.146'));
        $this->port = (int) config('services.issabel.port', env('ISSABEL_AMI_PORT', 443));
        $this->username = (string) config('services.issabel.user', env('ISSABEL_AMI_USER', 'crm_admin'));
        $this->secret = (string) config('services.issabel.secret', env('ISSABEL_AMI_SECRET', ''));
        $this->sourceExtension = (string) config('services.issabel.source_extension', env('ISSABEL_SOURCE_EXTENSION', '1'));
        $this->sipDriver = strtolower((string) config('services.issabel.sip_driver', env('ISSABEL_SIP_DRIVER', 'sip')));
        $this->context = (string) config('services.issabel.context', env('ISSABEL_CONTEXT', 'from-internal'));
        $this->timeout = (int) config('services.issabel.timeout', env('ISSABEL_TIMEOUT_SECONDS', 10));
        $this->bridgeUrl = (string) config('services.issabel.bridge_url', env('ISSABEL_BRIDGE_URL', 'https://callcenter.tamep.bo/ami_bridge.php'));
    }

    /**
     * Determina si la conexión debe realizarse mediante el puente HTTPS (puerto 443)
     * en lugar de un socket TCP plano (puerto 5038).
     */
    protected function shouldUseHttpsBridge(): bool
    {
        return in_array($this->port, [443, 80, 8443], true);
    }

    /**
     * Ejecuta una petición HTTPS (Puerto 443) hacia el script puente ami_bridge.php en Issabel.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function callBridge(string $action, array $params = []): array
    {
        if (! function_exists('curl_init')) {
            return [
                'success' => false,
                'error_type' => 'CURL_DISABLED',
                'message' => 'La extensión cURL de PHP no está habilitada en el hosting.',
                'diagnostic' => 'Habilita la extensión php-curl en el panel cPanel de tu hosting para usar la conexión por puerto 443.',
                'http_code' => 0,
            ];
        }

        $payload = array_merge([
            'action' => $action,
            'username' => $this->username,
            'secret' => $this->secret,
            'sip_driver' => $this->sipDriver,
            'public_host' => $this->host,
            'timeout' => $this->timeout,
            'ami_port' => 5038,
        ], $params);

        $candidateUrls = array_values(array_unique(array_filter([
            trim($this->bridgeUrl),
            "https://{$this->host}/ami_bridge.php",
            'https://callcenter.tamep.bo/ami_bridge.php',
        ])));

        $lastCurlError = '';
        $lastHttpCode = 0;
        $lastUrl = $candidateUrls[0] ?? '';

        foreach ($candidateUrls as $url) {
            $lastUrl = $url;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_TIMEOUT => max($this->timeout + 5, 15),
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $rawResponse = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $lastHttpCode = $httpCode;
            $lastCurlError = $curlErr;

            if ($rawResponse !== false && $httpCode === 200) {
                $decoded = json_decode((string) $rawResponse, true);
                if (is_array($decoded)) {
                    $decoded['http_code'] = 200;
                    $decoded['bridge_url'] = $url;

                    return $decoded;
                }
            }

            // Si el servidor contestó 404 en esta URL, probar la siguiente o reportar que falta subir ami_bridge.php
            if ($httpCode === 404) {
                continue;
            }
        }

        if ($lastHttpCode === 404) {
            return [
                'success' => false,
                'error_type' => 'BRIDGE_NOT_UPLOADED',
                'message' => 'Falta subir el archivo puente ami_bridge.php a tu servidor Issabel (HTTP 404).',
                'diagnostic' => "El puerto 443 en {$this->host} pertenece al servidor web Apache (HTTPS) de Issabel, no al socket directo de Asterisk. Para conectar por el puerto 443 desde tu hosting, sube el archivo 'public/issabel/ami_bridge.php' de este proyecto a '/var/www/html/ami_bridge.php' en tu servidor Issabel ({$this->host}), junto donde está hft.php.",
                'http_code' => 404,
            ];
        }

        return [
            'success' => false,
            'error_type' => 'BRIDGE_CONNECTION_ERROR',
            'message' => 'No se pudo conectar al puente HTTPS en el puerto 443: '.($lastCurlError ?: "HTTP {$lastHttpCode}"),
            'diagnostic' => "No hubo respuesta válida desde {$lastUrl} (HTTP {$lastHttpCode}). Verifica que el servidor web HTTPS de Issabel ({$this->host}:443) esté activo y que el archivo '/var/www/html/ami_bridge.php' esté subido en Issabel.",
            'http_code' => $lastHttpCode,
        ];
    }

    /**
     * Prueba la conexión TCP o Puente HTTPS (Puerto 443) y la autenticación del usuario contra Asterisk AMI.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     error_type: ?string,
     *     diagnostic: ?string,
     *     banner: ?string,
     *     host: string,
     *     port: int
     * }
     */
    public function testConnection(): array
    {
        // 1. Verificación de credenciales básicas en .env
        if (empty($this->host)) {
            return [
                'success' => false,
                'message' => 'ISSABEL_HOST no configurado',
                'error_type' => 'MISSING_HOST',
                'diagnostic' => 'La variable ISSABEL_HOST está vacía en el archivo .env de tu hosting. Debes colocar la IP pública de Issabel (ej. 186.121.251.146).',
                'banner' => null,
                'host' => $this->host,
                'port' => $this->port,
            ];
        }

        if (empty($this->secret)) {
            return [
                'success' => false,
                'message' => 'ISSABEL_AMI_SECRET no configurado',
                'error_type' => 'MISSING_SECRET',
                'diagnostic' => 'La variable ISSABEL_AMI_SECRET está vacía en el archivo .env de tu hosting. Configura la contraseña de conexión AMI (ej. issabel2026*).',
                'banner' => null,
                'host' => $this->host,
                'port' => $this->port,
            ];
        }

        // 2. Si el puerto configurado es 443 (o puerto web para saltar bloqueo de hosting), usar Puente HTTPS
        if ($this->shouldUseHttpsBridge()) {
            $bridgeRes = $this->callBridge('test');

            return [
                'success' => (bool) ($bridgeRes['success'] ?? false),
                'message' => (string) ($bridgeRes['message'] ?? 'Error al verificar puente HTTPS en puerto 443'),
                'error_type' => $bridgeRes['error_type'] ?? (($bridgeRes['success'] ?? false) ? null : 'BRIDGE_ERROR'),
                'diagnostic' => $bridgeRes['diagnostic'] ?? null,
                'banner' => $bridgeRes['banner'] ?? null,
                'host' => $this->host,
                'port' => $this->port,
            ];
        }

        // 3. Verificación preliminar de funciones PHP requeridas para socket directo (puerto 5038)
        if (! function_exists('stream_socket_client')) {
            return [
                'success' => false,
                'message' => 'Función stream_socket_client() no disponible',
                'error_type' => 'PHP_FUNCTION_DISABLED',
                'diagnostic' => 'La función PHP stream_socket_client() está deshabilitada en tu hosting. Cambia ISSABEL_AMI_PORT=443 en tu .env y sube public/issabel/ami_bridge.php a /var/www/html/ami_bridge.php en Issabel.',
                'banner' => null,
                'host' => $this->host,
                'port' => $this->port,
            ];
        }

        $socket = null;
        try {
            $socket = $this->openSocket();
            $banner = $this->readBanner($socket);

            // Validar que el puerto realmente haya respondido con el protocolo Asterisk Call Manager
            if ($banner === '' || ! str_contains(strtolower($banner), 'asterisk')) {
                return [
                    'success' => false,
                    'message' => "El puerto {$this->port} no devolvió el saludo de Asterisk AMI.",
                    'error_type' => 'INVALID_AMI_BANNER',
                    'diagnostic' => "El servidor {$this->host}:{$this->port} abrió el socket pero no es Asterisk AMI (respuesta recibida: '".($banner ?: 'vacía / protocolo SSL')."'). Si estás usando el puerto 443 porque tu hosting bloquea el 5038, asegúrate de subir 'public/issabel/ami_bridge.php' a '/var/www/html/ami_bridge.php' en tu servidor Issabel.",
                    'banner' => $banner ?: null,
                    'host' => $this->host,
                    'port' => $this->port,
                ];
            }

            $loginResponse = $this->login($socket);
            if (! ($loginResponse['success'] ?? false)) {
                $errMsg = $loginResponse['message'] ?? 'Credenciales rechazadas por Asterisk';

                return [
                    'success' => false,
                    'message' => 'Fallo de autenticación: '.$errMsg,
                    'error_type' => 'AUTH_FAILED',
                    'diagnostic' => "Asterisk rechazó la autenticación del usuario AMI '{$this->username}'. Verifica: 1) Que el usuario y secreto coincidan con /etc/asterisk/manager_custom.conf. 2) Que en manager_custom.conf tengas permit=127.0.0.1/255.255.255.0 y permit=216.246.47.120/255.255.255.255 (IP de tu hosting).",
                    'banner' => $banner,
                    'host' => $this->host,
                    'port' => $this->port,
                ];
            }

            $this->logoff($socket);

            return [
                'success' => true,
                'message' => 'Conexión y autenticación exitosas con Issabel AMI.',
                'error_type' => null,
                'diagnostic' => 'Conexión TCP y autenticación Asterisk AMI funcionando al 100%.',
                'banner' => $banner,
                'host' => $this->host,
                'port' => $this->port,
            ];
        } catch (Exception $e) {
            // Fallback automático al Puente HTTPS (puerto 443) si el puerto 5038 está bloqueado por el hosting
            $bridgeFallback = $this->callBridge('test');
            if ($bridgeFallback['success'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Conectado automáticamente vía Puente HTTPS (Puerto 443).',
                    'error_type' => null,
                    'diagnostic' => 'El puerto 5038 está bloqueado en el hosting, pero la conexión funcionó al 100% mediante el puente HTTPS en el puerto 443.',
                    'banner' => $bridgeFallback['banner'] ?? 'Asterisk Call Manager (HTTPS 443)',
                    'host' => $this->host,
                    'port' => 443,
                ];
            }

            $msg = $e->getMessage();
            $lower = strtolower($msg);
            $errorType = 'SOCKET_ERROR';
            $diagnostic = "No se pudo conectar a {$this->host}:{$this->port}. Detalle: {$msg}";

            if (str_contains($lower, 'timed out') || str_contains($lower, '110') || str_contains($lower, '10060')) {
                $errorType = 'SOCKET_TIMEOUT';
                $diagnostic = "Tiempo de espera agotado (Timeout) al conectar con {$this->host}:{$this->port}. Tu hosting bloquea el puerto {$this->port} saliente. SOLUCIÓN: Usa ISSABEL_AMI_PORT=443 en tu .env y sube el archivo 'public/issabel/ami_bridge.php' a '/var/www/html/ami_bridge.php' en tu servidor Issabel.";
            } elseif (str_contains($lower, 'refused') || str_contains($lower, '111') || str_contains($lower, '10061')) {
                $errorType = 'CONNECTION_REFUSED';
                $diagnostic = "Conexión rechazada (Connection Refused) por {$this->host}:{$this->port}. Asterisk no está corriendo o el puerto AMI {$this->port} no está escuchando en Asterisk.";
            } elseif (str_contains($lower, 'no route') || str_contains($lower, '113') || str_contains($lower, '10065')) {
                $errorType = 'NO_ROUTE_TO_HOST';
                $diagnostic = "Sin ruta hacia el host {$this->host}. Verifica que la IP pública de Issabel sea correcta y accesible desde internet.";
            }

            Log::error('IssabelAmi: Error probando conexión', [
                'host' => $this->host,
                'port' => $this->port,
                'error_type' => $errorType,
                'error' => $msg,
                'diagnostic' => $diagnostic,
            ]);

            return [
                'success' => false,
                'message' => 'Error al conectar con Issabel: '.$msg,
                'error_type' => $errorType,
                'diagnostic' => $diagnostic,
                'banner' => null,
                'host' => $this->host,
                'port' => $this->port,
            ];
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /**
     * Consulta la lista de extensiones SIP y su estado de registro en Issabel.
     *
     * @return array<int, array{extension: string, ip: string, status: string, online: bool}>
     */
    public function getExtensions(): array
    {
        if ($this->shouldUseHttpsBridge()) {
            $bridgeRes = $this->callBridge('extensions');
            if (($bridgeRes['success'] ?? false) && isset($bridgeRes['extensions']) && is_array($bridgeRes['extensions'])) {
                return $bridgeRes['extensions'];
            }

            Log::warning('IssabelAmi (Bridge 443): Error obteniendo extensiones', [
                'message' => $bridgeRes['message'] ?? 'Sin respuesta del puente HTTPS',
            ]);

            return [];
        }

        $socket = null;
        $extensions = [];

        try {
            $socket = $this->openSocket();
            $this->readBanner($socket);

            $login = $this->login($socket);
            if (! ($login['success'] ?? false)) {
                throw new Exception('No se pudo autenticar en Asterisk: '.($login['message'] ?? 'Error desconocido'));
            }

            // Ejecutamos la acción SIPpeers
            $actionId = 'peers_'.uniqid();
            $this->write($socket, [
                'Action' => 'SIPpeers',
                'ActionID' => $actionId,
            ]);

            // Leer eventos hasta PeerlistComplete
            $startTime = time();
            while (! feof($socket) && (time() - $startTime) < $this->timeout) {
                $block = $this->readBlock($socket);
                if (empty($block)) {
                    continue;
                }

                $event = $block['Event'] ?? '';
                if ($event === 'PeerlistComplete') {
                    break;
                }

                if ($event === 'PeerEntry') {
                    $rawName = $block['ObjectName'] ?? '';
                    // Filtrar sólo nombres numéricos o extensiones válidas
                    if (! empty($rawName)) {
                        $status = $block['Status'] ?? 'UNKNOWN';
                        $ip = $block['IPaddress'] ?? '-';
                        $isOnline = str_contains(strtoupper($status), 'OK');

                        $extensions[] = [
                            'extension' => $rawName,
                            'ip' => $ip === '-none-' ? '-' : $ip,
                            'status' => $status,
                            'online' => $isOnline,
                        ];
                    }
                }
            }

            $this->logoff($socket);

            // Ordenar por número de extensión
            usort($extensions, fn ($a, $b) => strnatcmp($a['extension'], $b['extension']));

            return $extensions;
        } catch (Exception $e) {
            $bridgeRes = $this->callBridge('extensions');
            if (($bridgeRes['success'] ?? false) && isset($bridgeRes['extensions']) && is_array($bridgeRes['extensions'])) {
                return $bridgeRes['extensions'];
            }

            Log::warning('IssabelAmi: Error obteniendo extensiones', [
                'error' => $e->getMessage(),
            ]);

            return [];
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /**
     * Envía un mensaje instantáneo de texto e imagen a una extensión específica.
     *
     * @param  string  $toExtension  Extensión destino (ej. "102")
     * @param  string  $message  Texto del mensaje
     * @param  string|null  $imageUrl  URL opcional de imagen adjunta
     * @param  string|null  $fromExtension  Extensión remitente opcional
     * @return array{success: bool, message: string}
     */
    public function sendMessage(
        string $toExtension,
        string $message,
        ?string $imageUrl = null,
        ?string $fromExtension = null
    ): array {
        if ($this->shouldUseHttpsBridge()) {
            $bridgeRes = $this->callBridge('send_message', [
                'to_extension' => $toExtension,
                'message' => $message,
                'image_url' => $imageUrl,
                'from_extension' => $fromExtension ?: $this->sourceExtension,
            ]);

            return [
                'success' => (bool) ($bridgeRes['success'] ?? false),
                'message' => (string) ($bridgeRes['message'] ?? 'Error enviando mensaje por puente HTTPS 443'),
            ];
        }

        $socket = null;
        try {
            $socket = $this->openSocket();
            $this->readBanner($socket);

            $login = $this->login($socket);
            if (! ($login['success'] ?? false)) {
                throw new Exception('Fallo de autenticación: '.($login['message'] ?? 'Credenciales no válidas'));
            }

            $result = $this->dispatchMessage($socket, $toExtension, $message, $imageUrl, $fromExtension);

            $this->logoff($socket);

            return $result;
        } catch (Exception $e) {
            Log::error('IssabelAmi: Error al enviar mensaje', [
                'to' => $toExtension,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error enviando mensaje: '.$e->getMessage(),
            ];
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /**
     * Envía comunicados en masa a múltiples extensiones en una sola sesión AMI.
     *
     * @param  array<string>  $toExtensions  Lista de extensiones destino
     * @param  string  $message  Contenido del comunicado
     * @param  string|null  $imageUrl  Imagen opcional adjunta
     * @param  string|null  $fromExtension  Extensión remitente
     * @param  array{size?: int|null, mime?: string|null, name?: string|null}  $mediaMeta  Metadatos de la imagen
     * @return array{total: int, sent: int, failed: int, details: array<string, array{success: bool, message: string}>}
     */
    public function sendBulk(
        array $toExtensions,
        string $message,
        ?string $imageUrl = null,
        ?string $fromExtension = null,
        array $mediaMeta = []
    ): array {
        if ($this->shouldUseHttpsBridge()) {
            $bridgeRes = $this->callBridge('send_bulk', [
                'extensions' => array_values($toExtensions),
                'message' => $message,
                'image_url' => $imageUrl,
                'from_extension' => $fromExtension ?: $this->sourceExtension,
                'media_meta' => $mediaMeta,
            ]);

            if (($bridgeRes['success'] ?? false) && isset($bridgeRes['details']) && is_array($bridgeRes['details'])) {
                return [
                    'total' => (int) ($bridgeRes['total'] ?? count($toExtensions)),
                    'sent' => (int) ($bridgeRes['sent'] ?? 0),
                    'failed' => (int) ($bridgeRes['failed'] ?? 0),
                    'details' => $bridgeRes['details'],
                ];
            }

            $errMsg = (string) ($bridgeRes['message'] ?? 'Error al despachar mediante puente HTTPS 443');
            $details = [];
            foreach ($toExtensions as $ext) {
                $ext = trim((string) $ext);
                if ($ext !== '') {
                    $details[$ext] = ['success' => false, 'message' => $errMsg];
                }
            }

            return [
                'total' => count($details),
                'sent' => 0,
                'failed' => count($details),
                'details' => $details,
            ];
        }

        $socket = null;
        $total = count($toExtensions);
        $sent = 0;
        $failed = 0;
        $details = [];

        try {
            $socket = $this->openSocket();
            $this->readBanner($socket);

            $login = $this->login($socket);
            if (! ($login['success'] ?? false)) {
                throw new Exception('Autenticación fallida con Issabel PBX: '.($login['message'] ?? ''));
            }

            foreach ($toExtensions as $ext) {
                $ext = trim((string) $ext);
                if (empty($ext)) {
                    continue;
                }

                $res = $this->dispatchMessage($socket, $ext, $message, $imageUrl, $fromExtension, $mediaMeta);
                $details[$ext] = $res;

                if ($res['success']) {
                    $sent++;
                } else {
                    $failed++;
                }

                // Breve pausa para no saturar el bus de eventos de Asterisk
                usleep(50000); // 50ms
            }

            $this->logoff($socket);
        } catch (Exception $e) {
            Log::error('IssabelAmi: Error en envío masivo', ['error' => $e->getMessage()]);
            // Completar destinatarios no procesados como fallidos
            foreach ($toExtensions as $ext) {
                if (! isset($details[$ext])) {
                    $details[$ext] = ['success' => false, 'message' => $e->getMessage()];
                    $failed++;
                }
            }
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'details' => $details,
        ];
    }

    /**
     * Despacha la acción MessageSend a través de un socket abierto.
     * Si incluye imagen, genera el paquete estándar GSMA RCS File Transfer XML para Linphone.
     *
     * @param  resource  $socket
     * @param  array{size?: int|null, mime?: string|null, name?: string|null}  $mediaMeta
     * @return array{success: bool, message: string}
     */
    protected function dispatchMessage(
        $socket,
        string $toExtension,
        string $message,
        ?string $imageUrl = null,
        ?string $fromExtension = null,
        array $mediaMeta = []
    ): array {
        $source = $fromExtension ?: $this->sourceExtension;

        // Formatear destino según el driver configurado
        $destination = $this->sipDriver === 'pjsip'
            ? "pjsip:{$toExtension}"
            : "sip:{$toExtension}";

        $fromHeader = "\"Call Center TAMep\" <sip:{$source}@{$this->host}>";
        $textMessage = trim($message);
        $textSuccess = true;
        $imageSuccess = true;
        $lastError = null;

        // 1. Enviar el mensaje de texto principal (si contiene texto)
        if (! empty($textMessage)) {
            $actionId = 'msg_txt_'.uniqid();
            $this->write($socket, [
                'Action' => 'MessageSend',
                'To' => $destination,
                'From' => $fromHeader,
                'Base64Body' => base64_encode($textMessage),
                'ActionID' => $actionId,
            ]);

            $response = $this->readResponse($socket, $actionId);
            $status = strtolower($response['Response'] ?? '');
            if ($status !== 'success') {
                $textSuccess = false;
                $lastError = $response['Message'] ?? "No se pudo entregar el texto a la extensión {$toExtension}.";
            }
        }

        // 2. Enviar la imagen como mensaje nativo GSMA RCS XML compatible con Linphone
        if (! empty($imageUrl)) {
            if (! empty($textMessage)) {
                usleep(800000); // 800ms para permitir que el softphone complete la transacción SIP
            }

            // Usar el XML oficial devuelto por hft.php o generarlo según norma GSMA RCS
            if (! empty($mediaMeta['rcs_xml'])) {
                $rcsXml = $mediaMeta['rcs_xml'];
            } else {
                $fileName = $mediaMeta['name'] ?? basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'imagen.png');
                $fileSize = $mediaMeta['size'] ?? 102400;
                $mimeType = $mediaMeta['mime'] ?? 'image/png';
                $until = date('Y-m-d\TH:i:s\Z', time() + (30 * 86400));

                $rcsXml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
                    '<file xmlns="urn:gsma:params:xml:ns:rcs:rcs:fthttp">'."\n".
                    '  <file-info type="file">'."\n".
                    '    <file-size>'.(int) $fileSize.'</file-size>'."\n".
                    '    <file-name>'.htmlspecialchars($fileName, ENT_XML1, 'UTF-8').'</file-name>'."\n".
                    '    <content-type>'.htmlspecialchars($mimeType, ENT_XML1, 'UTF-8').'</content-type>'."\n".
                    '    <data url="'.htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8').'" until="'.$until.'"/>'."\n".
                    '  </file-info>'."\n".
                    '</file>';
            }

            $actionIdImg = 'msg_img_'.uniqid();
            $this->write($socket, [
                'Action' => 'MessageSend',
                'To' => $destination,
                'From' => $fromHeader,
                'Base64Body' => base64_encode($rcsXml),
                'Variable' => 'content-type=application/vnd.gsma.rcs-ft-http+xml',
                'ActionID' => $actionIdImg,
            ]);

            $responseImg = $this->readResponse($socket, $actionIdImg);
            $statusImg = strtolower($responseImg['Response'] ?? '');
            if ($statusImg !== 'success') {
                $imageSuccess = false;
                $lastError = $responseImg['Message'] ?? "No se pudo entregar la imagen a la extensión {$toExtension}.";
            }
        }

        if ($textSuccess && $imageSuccess) {
            return [
                'success' => true,
                'message' => "Comunicado entregado exitosamente a la extensión {$toExtension}.",
            ];
        }

        return [
            'success' => false,
            'message' => $lastError ?: "Error al despachar a la extensión {$toExtension}.",
        ];
    }

    /**
     * Abre el socket TCP con el servidor Issabel.
     *
     * @return resource
     *
     * @throws Exception
     */
    protected function openSocket()
    {
        $connectionString = "tcp://{$this->host}:{$this->port}";
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $connectionString,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $socket || ! is_resource($socket)) {
            throw new Exception("No fue posible abrir el socket con Issabel ({$connectionString}): [{$errno}] {$errstr}");
        }

        stream_set_timeout($socket, $this->timeout);

        return $socket;
    }

    /**
     * Lee el banner inicial de bienvenida que emite Asterisk (ej. "Asterisk Call Manager/13.x").
     *
     * @param  resource  $socket
     */
    protected function readBanner($socket): string
    {
        $banner = fgets($socket, 1024);

        return trim((string) $banner);
    }

    /**
     * Realiza el handshake de inicio de sesión en AMI.
     *
     * @param  resource  $socket
     * @return array{success: bool, message: string}
     */
    protected function login($socket): array
    {
        $actionId = 'login_'.uniqid();
        $this->write($socket, [
            'Action' => 'Login',
            'Username' => $this->username,
            'Secret' => $this->secret,
            'Events' => 'off',
            'ActionID' => $actionId,
        ]);

        $response = $this->readResponse($socket, $actionId);
        $isSuccess = strtolower($response['Response'] ?? '') === 'success';

        return [
            'success' => $isSuccess,
            'message' => $response['Message'] ?? ($isSuccess ? 'Autenticado' : 'Error de autenticación'),
        ];
    }

    /**
     * Cierra la sesión AMI de forma ordenada.
     *
     * @param  resource  $socket
     */
    protected function logoff($socket): void
    {
        try {
            $this->write($socket, [
                'Action' => 'Logoff',
            ]);
            $this->readBlock($socket);
        } catch (Exception) {
            // Ignorar errores al cerrar
        }
    }

    /**
     * Escribe un paquete de encabezados formateados para AMI.
     *
     * @param  resource  $socket
     * @param  array<string, string>  $headers
     */
    protected function write($socket, array $headers): void
    {
        $payload = '';
        foreach ($headers as $key => $value) {
            // Un salto de línea dentro de un valor rompería el paquete AMI
            $safeValue = str_replace(["\r", "\n"], ' ', (string) $value);
            $payload .= "{$key}: {$safeValue}\r\n";
        }
        $payload .= "\r\n";

        fwrite($socket, $payload);
        fflush($socket);
    }

    /**
     * Lee bloques hasta encontrar la respuesta que corresponde al ActionID indicado,
     * descartando eventos asíncronos que Asterisk pueda intercalar.
     *
     * @param  resource  $socket
     * @return array<string, string>
     */
    protected function readResponse($socket, string $actionId): array
    {
        $startTime = time();

        while (! feof($socket) && (time() - $startTime) < $this->timeout) {
            $block = $this->readBlock($socket);
            if (empty($block)) {
                continue;
            }

            if (isset($block['Response']) && ($block['ActionID'] ?? $actionId) === $actionId) {
                return $block;
            }
        }

        return [];
    }

    /**
     * Lee un bloque de respuesta delimitado por doble salto de línea (\r\n\r\n).
     *
     * @param  resource  $socket
     * @return array<string, string>
     */
    protected function readBlock($socket): array
    {
        $headers = [];
        $startTime = time();

        while (! feof($socket) && (time() - $startTime) < $this->timeout) {
            $line = fgets($socket, 4096);
            if ($line === false) {
                break;
            }

            $line = trim($line);
            if ($line === '') {
                // Doble salto de línea indica fin de bloque de respuesta
                if (! empty($headers)) {
                    break;
                }

                continue;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }
}
