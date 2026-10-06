<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_agent_create_creates_new_agent(): void
    {
        $this->artisan('agent:create', [
            'name' => 'Laura Paz',
            'email' => 'laura@aerolinea.com',
            'password' => 'secret1234',
            '--role' => 'agent',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'Laura Paz',
            'email' => 'laura@aerolinea.com',
            'role' => 'agent',
            'is_active' => true,
        ]);
    }

    public function test_artisan_agent_password_resets_agent_password(): void
    {
        $user = User::factory()->create([
            'email' => 'marcos@aerolinea.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $this->artisan('agent:password', [
            'email' => 'marcos@aerolinea.com',
            'password' => 'newpassword999',
        ])->assertExitCode(0);

        $this->assertTrue(auth()->attempt([
            'email' => 'marcos@aerolinea.com',
            'password' => 'newpassword999',
        ]));
    }

    public function test_artisan_agent_toggle_suspends_and_activates(): void
    {
        $user = User::factory()->create([
            'email' => 'pedro@aerolinea.com',
            'is_active' => true,
        ]);

        $this->artisan('agent:toggle', ['email' => 'pedro@aerolinea.com'])
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->is_active);

        $this->artisan('agent:toggle', ['email' => 'pedro@aerolinea.com'])
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_api_agents_crud_operations(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        // 1. Listar agentes
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/agents');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'agents']);

        // 2. Crear nuevo agente vía API
        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/agents', [
                'name' => 'Ana Morales',
                'email' => 'ana.morales@aerolinea.com',
                'password' => 'claveSegura2026',
                'role' => 'agent',
            ]);

        $createRes->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['email' => 'ana.morales@aerolinea.com']);
    }

    public function test_conversation_can_be_transferred_to_another_agent(): void
    {
        $agent1 = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $agent2 = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $token = $agent1->createToken('agent-token')->plainTextToken;

        $conv = Conversation::create([
            'phone' => '59178888888',
            'contact_name' => 'Pasajero Transfer',
            'status' => 'human_live',
            'assigned_user_id' => $agent1->id,
            'last_interaction_at' => now(),
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/conversations/{$conv->id}/assign", [
                'agent_id' => $agent2->id,
            ]);

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $conv->refresh();
        $this->assertEquals($agent2->id, $conv->assigned_user_id);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'agent',
            'message' => "↪️ Conversación transferida a {$agent2->name}.",
        ]);
    }

    public function test_agent_can_send_ticket_image_to_customer(): void
    {
        Storage::fake('public');

        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $token = $agent->createToken('agent-token')->plainTextToken;

        $conv = Conversation::create([
            'phone' => '59179999999',
            'contact_name' => 'Carlos Pasajero',
            'status' => 'human_live',
            'assigned_user_id' => $agent->id,
            'last_interaction_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('boleto_emitido.jpg', 600, 400);

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/conversations/{$conv->id}/reply", [
                'message' => 'Estimado Carlos, te adjunto la captura de tu boleto comprado.',
                'image' => $file,
            ]);

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'agent',
            'media_type' => 'image',
        ]);
    }

    public function test_admin_can_delete_agent_and_unassign_conversations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $agent = User::factory()->create(['name' => 'Roberto Vaca', 'role' => 'agent', 'is_active' => true]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $conv = Conversation::create([
            'phone' => '59177112233',
            'contact_name' => 'Pasajero Desasignado',
            'status' => 'human_live',
            'assigned_user_id' => $agent->id,
            'last_interaction_at' => now(),
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/agents/{$agent->id}");

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('users', ['id' => $agent->id]);

        $conv->refresh();
        $this->assertNull($conv->assigned_user_id);
        $this->assertEquals('waiting_agent', $conv->status);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/agents/{$admin->id}");

        $res->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_artisan_agent_delete_command(): void
    {
        $agent = User::factory()->create([
            'email' => 'borrar@tamep.bo',
            'role' => 'agent',
        ]);

        $this->artisan('agent:delete', [
            'email' => 'borrar@tamep.bo',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => 'borrar@tamep.bo']);
    }
}
