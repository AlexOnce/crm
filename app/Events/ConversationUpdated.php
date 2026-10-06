<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;

    public array $conversationData;

    public string $action;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation, string $action = 'updated')
    {
        $this->conversationId = $conversation->id;
        $this->action = $action;
        $this->conversationData = [
            'id' => $conversation->id,
            'phone' => $conversation->phone,
            'contact_name' => $conversation->contact_name,
            'status' => $conversation->status,
            'current_state' => $conversation->current_state,
            'assigned_user_id' => $conversation->assigned_user_id,
            'assigned_user' => $conversation->assignedUser ? [
                'id' => $conversation->assignedUser->id,
                'name' => $conversation->assignedUser->name,
            ] : null,
            'last_interaction_at' => $conversation->last_interaction_at?->toIso8601String(),
        ];
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
        return 'ConversationUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'conversation' => $this->conversationData,
            'action' => $this->action,
        ];
    }
}
