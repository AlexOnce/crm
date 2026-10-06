<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_metrics(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'kpis' => [
                    'total_revenue',
                    'revenue_today',
                    'total_tickets',
                    'tickets_today',
                    'total_leads',
                    'conversion_rate',
                ],
                'conversations',
                'agents',
                'recent_tickets',
            ]);
    }

    public function test_regular_agent_is_forbidden_from_admin_metrics(): void
    {
        $agent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
        ]);
        $token = $agent->createToken('agent-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/metrics');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_admin(): void
    {
        $response = $this->getJson('/api/admin/metrics');

        $response->assertStatus(401);
    }

    public function test_admin_can_access_tickets_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $conv = Conversation::create([
            'phone' => '59171112233',
            'contact_name' => 'Pasajero Prueba',
            'status' => 'human_live',
            'assigned_user_id' => $admin->id,
            'last_interaction_at' => now(),
        ]);

        Lead::create([
            'conversation_id' => $conv->id,
            'phone' => $conv->phone,
            'passenger_name' => 'Pasajero Prueba',
            'passport_number' => 'CI-123456',
            'flight_number' => 'TM-204',
            'origin' => 'VVI',
            'destination' => 'LPB',
            'flight_date' => now()->format('Y-m-d'),
            'total_price' => 450.00,
            'booking_reference' => 'TM99XX',
            'status' => 'emitido',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/tickets');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'tickets'])
            ->assertJsonFragment(['booking_reference' => 'TM99XX']);
    }

    public function test_admin_can_access_conversations_monitor(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        Conversation::create([
            'phone' => '59178899001',
            'contact_name' => 'Pasajero Monitoreo',
            'status' => 'waiting_agent',
            'last_interaction_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/conversations/monitor');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'conversations'])
            ->assertJsonFragment(['phone' => '59178899001']);
    }
}
