<?php

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Services\MetaWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMassBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos del orquestador.
     */
    public int $tries = 1;

    /**
     * Tiempo máximo de ejecución del job de orquestación (10 minutos).
     */
    public int $timeout = 600;

    /**
     * Crea una nueva instancia del Job.
     */
    public function __construct(
        public BroadcastCampaign $campaign
    ) {
        $this->onQueue('default');
    }

    /**
     * Ejecuta la orquestación de la campaña masiva.
     */
    public function handle(MetaWhatsAppService $whatsapp): void
    {
        $this->campaign->refresh();
        if ($this->campaign->status === 'cancelled') {
            Log::info("ProcessMassBroadcastJob: Campaña #{$this->campaign->id} cancelada antes de iniciar orquestación.");

            return;
        }

        Log::info("ProcessMassBroadcastJob: Iniciando campaña masiva #{$this->campaign->id} ('{$this->campaign->title}')");

        $this->campaign->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $recipients = $this->campaign->recipients()->where('status', 'pending')->get();

        if ($recipients->isEmpty()) {
            if ($this->campaign->status !== 'cancelled') {
                $this->campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            return;
        }

        $channel = $this->campaign->channel; // 'sms', 'whatsapp', 'hybrid'

        $selectedLine = $this->campaign->filters['goip_line'] ?? 'both';
        if ($selectedLine === '1' || $selectedLine === 1) {
            $activeLines = [1];
        } elseif ($selectedLine === '2' || $selectedLine === 2) {
            $activeLines = [2];
        } else {
            $activeLines = config('services.goip.lines', [1, 2]);
            if (empty($activeLines)) {
                $activeLines = [1, 2];
            }
        }
        $totalLines = count($activeLines);
        $lineIndex = 0;

        foreach ($recipients as $recipient) {
            $this->campaign->refresh();
            if ($this->campaign->status === 'cancelled') {
                Log::info("ProcessMassBroadcastJob: Campaña #{$this->campaign->id} cancelada durante el procesamiento.");
                break;
            }

            $assignedLine = $totalLines > 0 ? (int) $activeLines[$lineIndex % $totalLines] : null;
            $lineIndex++;

            $this->processRecipient($recipient, $channel, $whatsapp, $assignedLine);
        }

        // Si todos fueron procesados inmediatamente, verificar estado
        $pendingCount = $this->campaign->recipients()->whereIn('status', ['pending', 'processing'])->count();
        if ($pendingCount === 0 && $this->campaign->status !== 'cancelled') {
            $this->campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Procesa un destinatario según el canal configurado en la campaña.
     */
    protected function processRecipient(
        BroadcastRecipient $recipient,
        string $channel,
        MetaWhatsAppService $whatsapp,
        ?int $assignedLine = null
    ): void {
        $recipient->update(['status' => 'processing']);

        // 1. Canal Exclusivo SMS GoIP (Entel)
        if ($channel === 'sms') {
            SendGoipSmsNotification::dispatch(
                $recipient->phone,
                $recipient->message,
                $recipient->lead_id,
                $assignedLine,
                $recipient->id
            );

            return;
        }

        // 2. Canal Exclusivo WhatsApp Oficial (Meta Cloud API)
        if ($channel === 'whatsapp') {
            try {
                $sent = $whatsapp->sendMessage($recipient->phone, $recipient->message);
                if ($sent) {
                    $recipient->update([
                        'status' => 'sent',
                        'channel_used' => 'whatsapp',
                        'sent_at' => now(),
                    ]);
                    $this->campaign->increment('sent_count');
                } else {
                    $recipient->update([
                        'status' => 'failed',
                        'channel_used' => 'whatsapp',
                        'error_message' => 'Meta WhatsApp no pudo entregar el mensaje (ventana de 24h cerrada o número sin WhatsApp).',
                    ]);
                    $this->campaign->increment('failed_count');
                }
            } catch (\Throwable $e) {
                Log::error("ProcessMassBroadcastJob: Excepción enviando WhatsApp a {$recipient->phone}: ".$e->getMessage());
                $recipient->update([
                    'status' => 'failed',
                    'channel_used' => 'whatsapp',
                    'error_message' => $e->getMessage(),
                ]);
                $this->campaign->increment('failed_count');
            }

            return;
        }

        // 3. Canal Híbrido Inteligente (Intenta WhatsApp; si falla, desvía automáticamente a SMS GoIP)
        if ($channel === 'hybrid') {
            $whatsappSent = false;
            try {
                $whatsappSent = $whatsapp->sendMessage($recipient->phone, $recipient->message);
                if ($whatsappSent) {
                    $recipient->update([
                        'status' => 'sent',
                        'channel_used' => 'whatsapp',
                        'sent_at' => now(),
                    ]);
                    $this->campaign->increment('sent_count');

                    return;
                }
            } catch (\Throwable $e) {
                Log::warning("ProcessMassBroadcastJob [Híbrido]: WhatsApp falló para {$recipient->phone}, activando fallback a SMS GoIP: ".$e->getMessage());
            }

            // WhatsApp falló -> Despachar automáticamente vía SMS GoIP
            SendGoipSmsNotification::dispatch(
                $recipient->phone,
                $recipient->message,
                $recipient->lead_id,
                $assignedLine,
                $recipient->id
            );
        }
    }
}
