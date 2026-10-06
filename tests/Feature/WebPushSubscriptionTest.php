<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'name' => 'Asesor Ventas TAMep',
            'email' => 'asesor@tamep.bo',
            'role' => 'agent',
            'is_active' => true,
        ]);
    }

    public function test_can_get_vapid_public_key(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/push/vapid-public-key');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'publicKey',
            ]);

        $this->assertNotEmpty($response->json('publicKey'));
    }

    public function test_can_subscribe_device_to_push_notifications(): void
    {
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test_device_token_123',
            'keys' => [
                'p256dh' => 'BCc34N-test-p256dh-key',
                'auth' => 'test-auth-token-xyz',
            ],
            'contentEncoding' => 'aes128gcm',
        ];

        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/push/subscribe', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->agent->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test_device_token_123',
            'public_key' => 'BCc34N-test-p256dh-key',
            'auth_token' => 'test-auth-token-xyz',
        ]);
    }

    public function test_can_unsubscribe_device(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test_device_token_to_remove';

        PushSubscription::create([
            'user_id' => $this->agent->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'key123',
            'auth_token' => 'auth123',
        ]);

        $this->assertDatabaseCount('push_subscriptions', 1);

        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/push/unsubscribe', [
                'endpoint' => $endpoint,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_test_endpoint_fails_if_no_devices_subscribed(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/push/test');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_web_push_service_payload_structure(): void
    {
        $service = app(WebPushService::class);
        $this->assertNotNull($service);

        $conversation = Conversation::create([
            'phone' => '59170012345',
            'contact_name' => 'Pasajero Prueba',
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
            'last_interaction_at' => now(),
        ]);

        // Si no hay suscriptores, notifyWaitingPassenger retorna 0 enviados sin fallar
        $sent = $service->notifyWaitingPassenger($conversation, 'Vuelo Santa Cruz - La Paz');
        $this->assertEquals(0, $sent);
    }
}
