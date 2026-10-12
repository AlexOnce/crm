<?php

namespace App\Events;

use App\Models\SystemVerificationCode;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemVerificationCodeReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $id;

    public string $code;

    public string $service;

    public ?string $senderPhone;

    public string $fullMessage;

    public ?string $expiresAt;

    public string $receivedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(SystemVerificationCode $verificationCode)
    {
        $this->id = $verificationCode->id;
        $this->code = $verificationCode->code;
        $this->service = $verificationCode->service;
        $this->senderPhone = $verificationCode->sender_phone;
        $this->fullMessage = $verificationCode->full_message;
        $this->expiresAt = $verificationCode->expires_at?->toIso8601String();
        $this->receivedAt = $verificationCode->created_at?->toIso8601String() ?? now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversations'),
            new PrivateChannel('system.verifications'),
        ];
    }

    /**
     * Nombre del evento emitido en el cliente WebSocket.
     */
    public function broadcastAs(): string
    {
        return 'SystemVerificationCodeReceived';
    }

    /**
     * Datos transferidos al cliente vía WebSocket.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'service' => $this->service,
            'sender_phone' => $this->senderPhone,
            'full_message' => $this->fullMessage,
            'expires_at' => $this->expiresAt,
            'received_at' => $this->receivedAt,
        ];
    }
}
