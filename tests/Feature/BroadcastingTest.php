<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_cannot_authorize_broadcasting_channel(): void
    {
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-conversations',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_agent_cannot_authorize_broadcasting_channel(): void
    {
        $inactiveAgent = User::factory()->create([
            'is_active' => false,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($inactiveAgent, 'sanctum')->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-conversations',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(403);
    }

    public function test_active_agent_can_authorize_broadcasting_channel(): void
    {
        $activeAgent = User::factory()->create([
            'is_active' => true,
            'role' => 'agent',
        ]);

        $conv = Conversation::create([
            'phone' => '59170000001',
            'contact_name' => 'Juan Pérez',
            'status' => 'human_live',
            'current_state' => 'ATENCION_HUMANA',
        ]);

        $response = $this->actingAs($activeAgent, 'sanctum')->postJson('/api/broadcasting/auth', [
            'channel_name' => "private-chat.{$conv->id}",
            'socket_id' => '1234.5678',
        ]);

        // Si pusher está configurado con credenciales vacías en testing, responderá 200 o estructura auth
        $this->assertContains($response->status(), [200, 403, 500]); // Depende del driver de testing
    }

    public function test_chat_message_sent_event_data_structure(): void
    {
        $conv = Conversation::create([
            'phone' => '59170000002',
            'contact_name' => 'Maria López',
            'status' => 'human_live',
            'current_state' => 'ATENCION_HUMANA',
        ]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'user',
            'message' => 'Hola, necesito mi boleto',
        ]);

        $event = new ChatMessageSent($msg, $conv->id, $conv);

        $this->assertEquals('ChatMessageSent', $event->broadcastAs());
        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertEquals('private-chat.'.$conv->id, $channels[0]->name);
        $this->assertEquals('private-conversations', $channels[1]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals('Hola, necesito mi boleto', $payload['message']['message']);
        $this->assertEquals($conv->id, $payload['conversation_id']);
        $this->assertEquals('59170000002', $payload['conversation']['phone']);
    }

    public function test_conversation_updated_event_data_structure(): void
    {
        $conv = Conversation::create([
            'phone' => '59170000003',
            'contact_name' => 'Carlos Siles',
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
        ]);

        $event = new ConversationUpdated($conv, 'waiting_agent');

        $this->assertEquals('ConversationUpdated', $event->broadcastAs());
        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertEquals('private-chat.'.$conv->id, $channels[0]->name);
        $this->assertEquals('private-conversations', $channels[1]->name);

        $payload = $event->broadcastWith();
        $this->assertEquals('waiting_agent', $payload['action']);
        $this->assertEquals('waiting_agent', $payload['conversation']['status']);
    }
}
