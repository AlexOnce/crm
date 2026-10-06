<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_successfully_change_password(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'role' => 'agent',
            'is_active' => true,
        ]);

        $token = $agent->createToken('agent_panel_session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'old-password-123',
                'password' => 'new-secure-password-456',
                'password_confirmation' => 'new-secure-password-456',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tu contraseña ha sido actualizada con éxito.',
            ]);

        $this->assertTrue(Hash::check('new-secure-password-456', $agent->fresh()->password));
        $this->assertFalse(Hash::check('old-password-123', $agent->fresh()->password));
    }

    public function test_other_active_tokens_are_revoked_upon_password_change(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('current-password-123'),
            'role' => 'agent',
            'is_active' => true,
        ]);

        $otherToken = $agent->createToken('other_device_session');
        $currentToken = $agent->createToken('agent_panel_session');

        $this->assertEquals(2, $agent->tokens()->count());

        $response = $this->withHeader('Authorization', "Bearer {$currentToken->plainTextToken}")
            ->putJson('/api/auth/password', [
                'current_password' => 'current-password-123',
                'password' => 'brand-new-pass-789',
                'password_confirmation' => 'brand-new-pass-789',
            ]);

        $response->assertStatus(200);

        // El token anterior de otro dispositivo debe haber sido revocado
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        // La sesión actual debe mantenerse activa
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
    }

    public function test_password_change_fails_if_current_password_is_incorrect(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('real-password-123'),
            'role' => 'agent',
            'is_active' => true,
        ]);

        $token = $agent->createToken('agent_panel_session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'wrong-password-999',
                'password' => 'new-password-789',
                'password_confirmation' => 'new-password-789',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Asegurarse de que no cambió la contraseña
        $this->assertTrue(Hash::check('real-password-123', $agent->fresh()->password));
    }

    public function test_password_change_fails_if_confirmation_does_not_match(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('current-pass-123'),
            'role' => 'agent',
            'is_active' => true,
        ]);

        $token = $agent->createToken('agent_panel_session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'current-pass-123',
                'password' => 'new-password-456',
                'password_confirmation' => 'mismatched-password-999',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_fails_if_new_password_is_same_as_current(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('same-password-123'),
            'role' => 'agent',
            'is_active' => true,
        ]);

        $token = $agent->createToken('agent_panel_session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'same-password-123',
                'password' => 'same-password-123',
                'password_confirmation' => 'same-password-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_fails_if_new_password_is_too_short(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('valid-password-123'),
            'role' => 'agent',
            'is_active' => true,
        ]);

        $token = $agent->createToken('agent_panel_session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'valid-password-123',
                'password' => '12345',
                'password_confirmation' => '12345',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_user_cannot_change_password(): void
    {
        $response = $this->putJson('/api/auth/password', [
            'current_password' => 'any-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_suspended_user_cannot_change_password(): void
    {
        $agent = User::factory()->create([
            'password' => Hash::make('valid-password-123'),
            'role' => 'agent',
            'is_active' => false,
        ]);

        $token = $agent->createToken('agent_panel_session')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'valid-password-123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertStatus(403);
    }
}
