<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $message;

    public int $conversationId;

    public ?array $conversationData;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatMessage $message, int $conversationId, ?Conversation $conversation = null)
    {
        $this->message = $message->loadMissing('agent:id,name');
        $this->conversationId = $conversationId;

        if ($conversation) {
            $this->conversationData = [
                'id' => $conversation->id,
                'phone' => $conversation->phone,
                'contact_name' => $conversation->contact_name,
                'status' => $conversation->status,
                'current_state' => $conversation->current_state,
                'assigned_user_id' => $conversation->assigned_user_id,
                'last_interaction_at' => $conversation->last_interaction_at?->toIso8601String(),
            ];
        } else {
            $this->conversationData = null;
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->conversationId),
            new PrivateChannel('conversations'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toArray(),
            'conversation_id' => $this->conversationId,
            'conversation' => $this->conversationData,
        ];
    }
}
