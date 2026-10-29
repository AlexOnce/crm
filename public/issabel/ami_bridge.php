<?php

/**
 * ============================================================================
 * PUENTE HTTPS (PUERTO 443) PARA ASTERISK AMI EN ISSABEL PBX
 * ============================================================================
 * Propósito:
 * Permitir que un hosting compartido (cPanel) que tiene bloqueado el puerto
 * saliente 5038 TCP pueda comunicarse con Asterisk AMI a través del puerto
 * estándar 443 (HTTPS) sin bloqueos de firewall.
 *
 * Instalación en el servidor Issabel (186.121.251.146 / callcenter.tamep.bo):
 * Copiar este archivo en: /var/www/html/ami_bridge.php
 * Permisos recomendados: chown asterisk:asterisk /var/www/html/ami_bridge.php && chmod 644 /var/www/html/ami_bridge.php
 * ============================================================================
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Bridge-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: '{}', true);
if (! is_array($payload)) {
    $payload = $_POST;
}

$action = $payload['action'] ?? ($_GET['action'] ?? 'ping');

if ($action === 'ping') {
    echo json_encode([
        'success' => true,
        'bridge' => 'Issabel HTTPS AMI Bridge v1.0',
        'php_version' => PHP_VERSION,
        'timestamp' => date('c'),
    ]);
    exit;
}

$amiHost = '127.0.0.1';
$amiPort = (int) ($payload['ami_port'] ?? 5038);
$username = (string) ($payload['username'] ?? '');
$secret = (string) ($payload['secret'] ?? '');
$sipDriver = strtolower((string) ($payload['sip_driver'] ?? 'sip'));
$publicHost = (string) ($payload['public_host'] ?? ($_SERVER['SERVER_ADDR'] ?? '186.121.251.146'));
$timeout = (int) ($payload['timeout'] ?? 10);

if ($username === '' || $secret === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error_type' => 'MISSING_CREDENTIALS',
        'message' => 'Faltan credenciales AMI (username o secret) en la petición al puente HTTPS.',
    ]);
    exit;
}

/**
 * Funciones auxiliares para comunicación AMI local (127.0.0.1:5038)
 */
function amiOpenSocket(string $host, int $port, int $timeout)
{
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (! $socket || ! is_resource($socket)) {
        throw new Exception("No se pudo conectar localmente a Asterisk AMI ({$host}:{$port}): [{$errno}] {$errstr}");
    }

    stream_set_timeout($socket, $timeout);

    return $socket;
}

function amiWrite($socket, array $headers): void
{
    $out = '';
    foreach ($headers as $k => $v) {
        $safeVal = str_replace(["\r", "\n"], ' ', (string) $v);
        $out .= "{$k}: {$safeVal}\r\n";
    }
    $out .= "\r\n";
    fwrite($socket, $out);
    fflush($socket);
}

function amiReadBlock($socket, int $timeout): array
{
    $headers = [];
    $start = time();

    while (! feof($socket) && (time() - $start) < $timeout) {
        $line = fgets($socket, 4096);
        if ($line === false) {
            break;
        }
        $line = trim($line);
        if ($line === '') {
            if (! empty($headers)) {
                break;
            }

            continue;
        }
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headers[trim($k)] = trim($v);
        }
    }

    return $headers;
}

function amiReadResponse($socket, string $actionId, int $timeout): array
{
    $start = time();
    while (! feof($socket) && (time() - $start) < $timeout) {
        $block = amiReadBlock($socket, $timeout);
        if (empty($block)) {
            continue;
        }
        if (isset($block['Response']) && ($block['ActionID'] ?? $actionId) === $actionId) {
            return $block;
        }
    }

    return [];
}

function amiLogin($socket, string $username, string $secret, int $timeout): array
{
    $actionId = 'login_'.uniqid();
    amiWrite($socket, [
        'Action' => 'Login',
        'Username' => $username,
        'Secret' => $secret,
        'Events' => 'off',
        'ActionID' => $actionId,
    ]);

    $resp = amiReadResponse($socket, $actionId, $timeout);
    $ok = strtolower($resp['Response'] ?? '') === 'success';

    return [
        'success' => $ok,
        'message' => $resp['Message'] ?? ($ok ? 'Authentication accepted' : 'Authentication failed'),
    ];
}

function amiLogoff($socket, int $timeout): void
{
    try {
        amiWrite($socket, ['Action' => 'Logoff']);
        amiReadBlock($socket, 2);
    } catch (Throwable $e) {
        // Ignorar al cerrar
    }
}

function amiDispatchMessage(
    $socket,
    string $toExtension,
    string $message,
    ?string $imageUrl,
    string $sourceExtension,
    string $sipDriver,
    string $publicHost,
    array $mediaMeta,
    int $timeout
): array {
    $destination = $sipDriver === 'pjsip' ? "pjsip:{$toExtension}" : "sip:{$toExtension}";
    $fromHeader = "\"Call Center TAMep\" <sip:{$sourceExtension}@{$publicHost}>";
    $textMessage = trim($message);
    $textSuccess = true;
    $imageSuccess = true;
    $lastError = null;

    if ($textMessage !== '') {
        $actionId = 'msg_txt_'.uniqid();
        amiWrite($socket, [
            'Action' => 'MessageSend',
            'To' => $destination,
            'From' => $fromHeader,
            'Base64Body' => base64_encode($textMessage),
            'ActionID' => $actionId,
        ]);

        $response = amiReadResponse($socket, $actionId, $timeout);
        if (strtolower($response['Response'] ?? '') !== 'success') {
            $textSuccess = false;
            $lastError = $response['Message'] ?? "No se pudo entregar el texto a la extensión {$toExtension}.";
        }
    }

    if (! empty($imageUrl)) {
        if ($textMessage !== '') {
            usleep(800000);
        }

        if (! empty($mediaMeta['rcs_xml'])) {
            $rcsXml = $mediaMeta['rcs_xml'];
        } else {
            $fileName = $mediaMeta['name'] ?? basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'imagen.png');
            $fileSize = (int) ($mediaMeta['size'] ?? 102400);
            $mimeType = $mediaMeta['mime'] ?? 'image/png';
            $until = gmdate('Y-m-d\TH:i:s\Z', time() + (30 * 86400));

            $rcsXml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
                '<file xmlns="urn:gsma:params:xml:ns:rcs:rcs:fthttp">'."\n".
                '  <file-info type="file">'."\n".
                '    <file-size>'.$fileSize.'</file-size>'."\n".
                '    <file-name>'.htmlspecialchars($fileName, ENT_XML1, 'UTF-8').'</file-name>'."\n".
                '    <content-type>'.htmlspecialchars($mimeType, ENT_XML1, 'UTF-8').'</content-type>'."\n".
                '    <data url="'.htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8').'" until="'.$until.'"/>'."\n".
                '  </file-info>'."\n".
                '</file>';
        }

        $actionIdImg = 'msg_img_'.uniqid();
        amiWrite($socket, [
            'Action' => 'MessageSend',
            'To' => $destination,
            'From' => $fromHeader,
            'Base64Body' => base64_encode($rcsXml),
            'Variable' => 'content-type=application/vnd.gsma.rcs-ft-http+xml',
            'ActionID' => $actionIdImg,
        ]);

        $responseImg = amiReadResponse($socket, $actionIdImg, $timeout);
        if (strtolower($responseImg['Response'] ?? '') !== 'success') {
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

$socket = null;
try {
    $socket = amiOpenSocket($amiHost, $amiPort, $timeout);
    $banner = trim((string) fgets($socket, 1024));

    $login = amiLogin($socket, $username, $secret, $timeout);
    if (! $login['success']) {
        amiLogoff($socket, $timeout);
        echo json_encode([
            'success' => false,
            'error_type' => 'AUTH_FAILED',
            'message' => 'Fallo de autenticación: '.$login['message'],
            'diagnostic' => "Asterisk local (127.0.0.1:{$amiPort}) rechazó las credenciales del usuario '{$username}'. Verifica en /etc/asterisk/manager_custom.conf que el usuario '{$username}', su secret y permit=127.0.0.1/255.255.255.0 estén configurados.",
            'banner' => $banner,
        ]);
        exit;
    }

    if ($action === 'test') {
        amiLogoff($socket, $timeout);
        echo json_encode([
            'success' => true,
            'message' => 'Conexión y autenticación exitosas con Issabel AMI (vía Puente HTTPS Puerto 443).',
            'error_type' => null,
            'diagnostic' => 'Conexión HTTPS (Puerto 443) -> Asterisk AMI (127.0.0.1:5038) funcionando al 100%.',
            'banner' => $banner ?: 'Asterisk Call Manager (HTTPS Bridge)',
        ]);
        exit;
    }

    if ($action === 'extensions') {
        $actionId = 'peers_'.uniqid();
        amiWrite($socket, [
            'Action' => 'SIPpeers',
            'ActionID' => $actionId,
        ]);

        $extensions = [];
        $startTime = time();
        while (! feof($socket) && (time() - $startTime) < $timeout) {
            $block = amiReadBlock($socket, $timeout);
            if (empty($block)) {
                continue;
            }

            $event = $block['Event'] ?? '';
            if ($event === 'PeerlistComplete') {
                break;
            }

            if ($event === 'PeerEntry') {
                $rawName = $block['ObjectName'] ?? '';
                if ($rawName !== '') {
                    $status = $block['Status'] ?? 'UNKNOWN';
                    $ip = $block['IPaddress'] ?? '-';
                    $isOnline = strpos(strtoupper($status), 'OK') !== false;

                    $extensions[] = [
                        'extension' => $rawName,
                        'ip' => $ip === '-none-' ? '-' : $ip,
                        'status' => $status,
                        'online' => $isOnline,
                    ];
                }
            }
        }

        amiLogoff($socket, $timeout);
        usort($extensions, fn ($a, $b) => strnatcmp($a['extension'], $b['extension']));

        echo json_encode([
            'success' => true,
            'extensions' => $extensions,
            'banner' => $banner,
        ]);
        exit;
    }

    if ($action === 'send_message') {
        $toExtension = trim((string) ($payload['to_extension'] ?? ''));
        $message = (string) ($payload['message'] ?? '');
        $imageUrl = $payload['image_url'] ?? null;
        $fromExtension = (string) ($payload['from_extension'] ?? '1');
        $mediaMeta = is_array($payload['media_meta'] ?? null) ? $payload['media_meta'] : [];

        $res = amiDispatchMessage(
            $socket,
            $toExtension,
            $message,
            $imageUrl,
            $fromExtension,
            $sipDriver,
            $publicHost,
            $mediaMeta,
            $timeout
        );

        amiLogoff($socket, $timeout);
        echo json_encode($res);
        exit;
    }

    if ($action === 'send_bulk') {
        $toExtensions = is_array($payload['extensions'] ?? null) ? $payload['extensions'] : [];
        $message = (string) ($payload['message'] ?? '');
        $imageUrl = $payload['image_url'] ?? null;
        $fromExtension = (string) ($payload['from_extension'] ?? '1');
        $mediaMeta = is_array($payload['media_meta'] ?? null) ? $payload['media_meta'] : [];

        $total = count($toExtensions);
        $sent = 0;
        $failed = 0;
        $details = [];

        foreach ($toExtensions as $ext) {
            $ext = trim((string) $ext);
            if ($ext === '') {
                continue;
            }

            $res = amiDispatchMessage(
                $socket,
                $ext,
                $message,
                $imageUrl,
                $fromExtension,
                $sipDriver,
                $publicHost,
                $mediaMeta,
                $timeout
            );
            $details[$ext] = $res;

            if ($res['success']) {
                $sent++;
            } else {
                $failed++;
            }

            usleep(50000);
        }

        amiLogoff($socket, $timeout);
        echo json_encode([
            'success' => true,
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'details' => $details,
        ]);
        exit;
    }

    amiLogoff($socket, $timeout);
    echo json_encode([
        'success' => false,
        'message' => "Acción no reconocida: {$action}",
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error_type' => 'LOCAL_AMI_ERROR',
        'message' => $e->getMessage(),
        'diagnostic' => 'El puente HTTPS en Issabel no pudo conectarse a Asterisk local (127.0.0.1:5038). Verifica que el servicio asterisk esté corriendo en Issabel.',
    ]);
} finally {
    if (is_resource($socket)) {
        @fclose($socket);
    }
}
