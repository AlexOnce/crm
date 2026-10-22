<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallCenterBroadcast;
use App\Models\CallCenterRecipient;
use App\Services\IssabelAmiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallCenterBroadcastController extends Controller
{
    /**
     * Consulta el estado de la conexión con el servidor Issabel PBX vía AMI.
     */
    public function status(IssabelAmiService $amiService): JsonResponse
    {
        $test = $amiService->testConnection();

        return response()->json([
            'success' => true,
            'connected' => $test['success'],
            'banner' => $test['banner'] ?? null,
            'message' => $test['message'],
            'host' => config('services.issabel.host'),
            'port' => config('services.issabel.port'),
            'source_extension' => config('services.issabel.source_extension'),
            'sip_driver' => config('services.issabel.sip_driver'),
        ]);
    }

    /**
     * Obtiene el listado en tiempo real de las extensiones SIP y su estado desde Asterisk.
     */
    public function extensions(IssabelAmiService $amiService): JsonResponse
    {
        $peers = $amiService->getExtensions();

        $onlineCount = count(array_filter($peers, fn ($p) => ! empty($p['online'])));

        return response()->json([
            'success' => true,
            'extensions' => $peers,
            'total' => count($peers),
            'online_count' => $onlineCount,
        ]);
    }

    /**
     * Sube una imagen para adjuntarla al comunicado, la envía al servidor Flexisip HFT (hft.php)
     * y obtiene el paquete XML oficial GSMA RCS compatible con Linphone.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpeg,png,jpg,webp,gif|max:10240',
        ]);

        $file = $request->file('image');
        $filename = 'callcenter_'.date('Ymd_His').'_'.uniqid().'.'.$file->getClientOriginalExtension();

        // 1. Almacenar localmente en public/callcenter
        $path = $file->storeAs('callcenter', $filename, 'public');
        $localFullPath = storage_path('app/public/'.$path);

        // 2. Subir a Flexisip HFT en Issabel (hft.php)
        $hftUrl = config('services.issabel.hft_url', 'https://callcenter.tamep.bo/hft.php');
        $rcsXml = null;
        $downloadUrl = null;

        try {
            $ch = curl_init($hftUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file' => new \CURLFile($localFullPath, $file->getMimeType(), $file->getClientOriginalName()),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && ! empty($response) && str_contains($response, '<file xmlns=')) {
                $rcsXml = trim($response);
                if (preg_match('/<data\s+url="([^"]+)"/i', $rcsXml, $matches)) {
                    $downloadUrl = $matches[1];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('HFT upload error: '.$e->getMessage());
        }

        // Fallback si no hubo URL desde HFT
        if (! $downloadUrl) {
            $baseUrl = rtrim((string) (config('services.issabel.media_base_url') ?: 'https://callcenter.tamep.bo/upload'), '/');
            $downloadUrl = $baseUrl.'/'.$filename;
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagen procesada y sincronizada con el servidor HFT.',
            'image_url' => $downloadUrl,
            'image_path' => $path,
            'image_size' => $file->getSize(),
            'image_mime' => $file->getMimeType(),
            'image_name' => $file->getClientOriginalName(),
            'rcs_xml' => $rcsXml,
        ]);
    }

    /**
     * Dispara un comunicado masivo o selectivo hacia las extensiones indicadas.
     */
    public function send(Request $request, IssabelAmiService $amiService): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'extensions' => 'required|array|min:1',
            'extensions.*' => 'required|string|max:30',
            'source_extension' => 'nullable|string|max:30',
            'image_url' => 'nullable|string|max:500',
            'image_path' => 'nullable|string|max:255',
            'image_size' => 'nullable|integer',
            'image_mime' => 'nullable|string|max:50',
            'image_name' => 'nullable|string|max:150',
            'rcs_xml' => 'nullable|string',
        ]);

        $sourceExt = $validated['source_extension'] ?: (string) config('services.issabel.source_extension', '1');
        $rawExtensions = array_values(array_unique(array_filter(array_map('trim', $validated['extensions']))));

        if (empty($rawExtensions)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar al menos una extensión válida.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $finalImageUrl = $validated['image_url'] ?? null;
            if (! empty($validated['image_path']) && empty($finalImageUrl)) {
                $filename = basename($validated['image_path']);
                $baseUrl = rtrim((string) (config('services.issabel.media_base_url') ?: 'https://callcenter.tamep.bo/upload'), '/');
                $finalImageUrl = $baseUrl.'/'.$filename;
            }

            // 1. Crear el registro maestro del comunicado
            $broadcast = CallCenterBroadcast::create([
                'user_id' => $request->user()?->id,
                'title' => $validated['title'],
                'source_extension' => $sourceExt,
                'message' => $validated['message'],
                'image_path' => $validated['image_path'] ?? null,
                'image_url' => $finalImageUrl,
                'total_extensions' => count($rawExtensions),
                'sent_count' => 0,
                'failed_count' => 0,
                'status' => 'processing',
            ]);

            $mediaMeta = [
                'size' => $validated['image_size'] ?? null,
                'mime' => $validated['image_mime'] ?? null,
                'name' => $validated['image_name'] ?? null,
                'rcs_xml' => $validated['rcs_xml'] ?? null,
            ];

            // 2. Ejecutar el despacho a través de Issabel AMI
            $bulkResult = $amiService->sendBulk(
                $rawExtensions,
                $validated['message'],
                $finalImageUrl,
                $sourceExt,
                $mediaMeta
            );

            $now = now();
            $recipientsData = [];

            foreach ($bulkResult['details'] as $ext => $detail) {
                $isSent = ! empty($detail['success']);
                $recipientsData[] = [
                    'broadcast_id' => $broadcast->id,
                    'extension' => (string) $ext,
                    'extension_name' => "Extensión {$ext}",
                    'status' => $isSent ? 'sent' : 'failed',
                    'response_message' => $detail['message'] ?? ($isSent ? 'Entregado' : 'Fallo en entrega'),
                    'sent_at' => $isSent ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Inserción en bloque para óptimo rendimiento
            CallCenterRecipient::insert($recipientsData);

            // 3. Determinar el estado final
            $sentCount = $bulkResult['sent'];
            $failedCount = $bulkResult['failed'];
            $status = 'completed';

            if ($sentCount === 0 && $failedCount > 0) {
                $status = 'failed';
            } elseif ($failedCount > 0) {
                $status = 'partial';
            }

            $broadcast->update([
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'status' => $status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Comunicado procesado: {$sentCount} entregados, {$failedCount} fallidos.",
                'broadcast' => $broadcast->fresh(['recipients']),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CallCenterBroadcast: Error al emitir comunicado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al emitir el comunicado: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el historial paginado de comunicados emitidos.
     */
    public function index(Request $request): JsonResponse
    {
        $broadcasts = CallCenterBroadcast::with('user:id,name,email')
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'broadcasts' => $broadcasts,
        ]);
    }

    /**
     * Retorna los detalles de un comunicado con todas las extensiones alcanzadas.
     */
    public function show(CallCenterBroadcast $broadcast): JsonResponse
    {
        $broadcast->load(['user:id,name,email', 'recipients']);

        return response()->json([
            'success' => true,
            'broadcast' => $broadcast,
        ]);
    }

    /**
     * Reintenta el envío a las extensiones que fallaron en una emisión anterior.
     */
    public function retryFailed(CallCenterBroadcast $broadcast, IssabelAmiService $amiService): JsonResponse
    {
        $failedRecipients = $broadcast->recipients()->where('status', 'failed')->get();

        if ($failedRecipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay extensiones fallidas pendientes de reintento en este comunicado.',
            ], 422);
        }

        $extensionsToRetry = $failedRecipients->pluck('extension')->toArray();

        $result = $amiService->sendBulk(
            $extensionsToRetry,
            $broadcast->message,
            $broadcast->image_url,
            $broadcast->source_extension
        );

        $now = now();
        foreach ($failedRecipients as $recipient) {
            $ext = $recipient->extension;
            if (isset($result['details'][$ext]) && $result['details'][$ext]['success']) {
                $recipient->update([
                    'status' => 'sent',
                    'response_message' => $result['details'][$ext]['message'],
                    'sent_at' => $now,
                ]);
            } else {
                $recipient->update([
                    'response_message' => $result['details'][$ext]['message'] ?? 'Fallo en reintento',
                ]);
            }
        }

        // Recalcular métricas
        $totalSent = $broadcast->recipients()->where('status', 'sent')->count();
        $totalFailed = $broadcast->recipients()->where('status', 'failed')->count();

        $status = 'completed';
        if ($totalSent === 0) {
            $status = 'failed';
        } elseif ($totalFailed > 0) {
            $status = 'partial';
        }

        $broadcast->update([
            'sent_count' => $totalSent,
            'failed_count' => $totalFailed,
            'status' => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Reintento finalizado: {$result['sent']} recuperados, {$result['failed']} aún sin entrega.",
            'broadcast' => $broadcast->fresh(['recipients']),
        ]);
    }
}
