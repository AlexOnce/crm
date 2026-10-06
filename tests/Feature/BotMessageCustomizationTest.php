<?php

namespace Tests\Feature;

use App\Models\BotMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatbotEngineService;
use App\Services\FlightMicroservice;
use App\Services\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BotMessageCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_message_model_returns_fallback_when_record_missing(): void
    {
        $fallback = 'Texto de respaldo en caso de emergencia';
        $result = BotMessage::getText('clave_inexistente', $fallback);

        $this->assertEquals($fallback, $result);
    }

    public function test_bot_message_model_replaces_dynamic_variables(): void
    {
        BotMessage::create([
            'key' => 'saludo_test',
            'title' => 'Saludo Test',
            'category' => 'Test',
            'message' => 'Hola {nombre}, tu vuelo es {vuelo} en la fecha {fecha}.',
            'default_message' => 'Hola {nombre}, tu vuelo es {vuelo} en la fecha {fecha}.',
            'variables' => ['nombre', 'vuelo', 'fecha'],
        ]);

        $result = BotMessage::getText('saludo_test', null, [
            'nombre' => 'Carlos Ramos',
            'vuelo' => 'TM-204',
            'fecha' => '2026-10-15',
        ]);

        $this->assertEquals('Hola Carlos Ramos, tu vuelo es TM-204 en la fecha 2026-10-15.', $result);
    }

    public function test_admin_can_list_bot_messages(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        BotMessage::create([
            'key' => 'test_key_1',
            'title' => 'Mensaje 1',
            'category' => 'Menús y Saludos',
            'message' => 'Contenido 1',
            'default_message' => 'Contenido 1',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/bot-messages');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'key' => 'test_key_1',
                'title' => 'Mensaje 1',
            ]);
    }

    public function test_regular_agent_cannot_access_bot_messages(): void
    {
        $agent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
        ]);
        $token = $agent->createToken('agent-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/bot-messages');

        $response->assertStatus(403);
    }

    public function test_admin_can_update_bot_message(): void
    {
        $admin = User::factory()->create([
            'name' => 'Gerente Comercial',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $msg = BotMessage::create([
            'key' => 'politica_equipaje',
            'title' => 'Equipaje',
            'category' => 'Políticas y FAQ',
            'message' => 'Equipaje original 20kg',
            'default_message' => 'Equipaje original 20kg',
        ]);

        $updatedText = '🎒 NUEVA POLÍTICA COMERCIAL: Equipaje incluido de 23kg por promoción de temporada.';

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/admin/bot-messages/{$msg->id}", [
                'message' => $updatedText,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('bot_messages', [
            'id' => $msg->id,
            'message' => $updatedText,
            'updated_by' => 'Gerente Comercial',
        ]);

        // Verificar que el método estático entrega el nuevo texto actualizado
        $this->assertEquals($updatedText, BotMessage::getText('politica_equipaje'));
    }

    public function test_admin_can_restore_bot_message_to_default(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $defaultText = 'Texto de fábrica original';
        $msg = BotMessage::create([
            'key' => 'info_oficinas',
            'title' => 'Oficinas',
            'category' => 'Oficinas y Carga',
            'message' => 'Texto alterado temporalmente',
            'default_message' => $defaultText,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/bot-messages/{$msg->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('bot_messages', [
            'id' => $msg->id,
            'message' => $defaultText,
        ]);

        $this->assertEquals($defaultText, BotMessage::getText('info_oficinas'));
    }

    public function test_chatbot_engine_uses_updated_bot_message_in_real_time(): void
    {
        // 1. Mensaje personalizado en BD
        $customText = '🧳 *NUEVA FRANQUICIA COMERCIAL:* Ahora permitimos 2 piezas de 23kg sin costo.';
        BotMessage::create([
            'key' => 'politica_equipaje',
            'title' => 'Política de Equipaje',
            'category' => 'Políticas y FAQ',
            'message' => $customText,
            'default_message' => 'Equipaje 20kg',
        ]);

        $metaService = Mockery::mock(MetaWhatsAppService::class);
        $flightService = Mockery::mock(FlightMicroservice::class);

        $engine = new ChatbotEngineService($metaService, $flightService);

        // Conversación de prueba
        $conv = Conversation::create([
            'phone' => '59178945612',
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
        ]);

        // Simular que el cliente pregunta por equipaje
        $metaService->shouldReceive('sendInteractiveButtons')
            ->once()
            ->withArgs(function ($phone, $message, $buttons, $header, $footer) use ($conv, $customText) {
                return $phone === $conv->phone && $message === $customText;
            })
            ->andReturn(true);

        $engine->processIncomingMessage($conv, '¿Cuánto equipaje puedo llevar en mi maleta?');

        // Confirmar que el mensaje guardado por el bot en la BD tiene el texto comercial personalizado
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'bot',
            'message' => $customText,
        ]);
    }
}
