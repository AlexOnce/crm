<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_traversal_is_blocked_on_storage_route(): void
    {
        $response = $this->get('/storage/../../.env');
        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertStringNotContainsString('APP_KEY', (string) $response->getContent());
        $this->assertStringNotContainsString('DB_PASSWORD', (string) $response->getContent());
    }

    public function test_unauthorized_access_to_unauthenticated_api_is_blocked(): void
    {
        $response = $this->getJson('/api/conversations');
        $response->assertStatus(401);

        $responseAdmin = $this->getJson('/api/admin/metrics');
        $responseAdmin->assertStatus(401);
    }

    public function test_agent_cannot_access_admin_endpoints(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $token = $agent->createToken('agent-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/metrics');

        $response->assertStatus(403);
    }

    public function test_suspended_user_cannot_access_any_crm_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'agent', 'is_active' => false]);
        $token = $user->createToken('agent-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/conversations');

        $response->assertStatus(403);
    }

    public function test_login_rate_limiting_protects_against_brute_force(): void
    {
        // El throttle es 5 intentos por minuto
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'attacker@fake.com',
                'password' => 'wrongpass',
            ]);
        }

        // El 6to intento debe recibir 429 Too Many Requests
        $response = $this->postJson('/api/auth/login', [
            'email' => 'attacker@fake.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(429);
    }
}
