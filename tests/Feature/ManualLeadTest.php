<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_create_manual_lead_for_conversation(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $conversation = Conversation::create([
            'phone' => '59170012345',
            'contact_name' => 'Carlos Cliente',
            'status' => 'human_live',
            'current_state' => 'MENU_PRINCIPAL',
            'last_interaction_at' => now(),
        ]);

        $response = $this->actingAs($agent, 'sanctum')->postJson("/api/conversations/{$conversation->id}/lead", [
            'passenger_name' => 'Carlos Cliente',
            'passport_number' => '7894561 SC',
            'origin' => 'VVI',
            'destination' => 'TJA',
            'flight_date' => '2026-10-20',
            'flight_number' => 'TM-204',
            'passengers_count' => 2,
            'total_price' => 900.00,
            'invoice_razon_social' => 'Cliente SRL',
            'invoice_nit' => '1020304050',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lead.passenger_name', 'Carlos Cliente')
            ->assertJsonPath('lead.origin', 'VVI')
            ->assertJsonPath('lead.destination', 'TJA')
            ->assertJsonPath('lead.passengers_count', 2);

        $this->assertDatabaseHas('leads', [
            'conversation_id' => $conversation->id,
            'passenger_name' => 'Carlos Cliente',
            'destination' => 'TJA',
            'status' => 'contactado',
        ]);
    }

    public function test_agent_can_update_existing_lead(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $conversation = Conversation::create([
            'phone' => '59170012345',
            'status' => 'human_live',
            'current_state' => 'MENU_PRINCIPAL',
            'last_interaction_at' => now(),
        ]);

        $lead = Lead::create([
            'conversation_id' => $conversation->id,
            'phone' => '59170012345',
            'type' => 'compra_pasaje',
            'passenger_name' => 'Carlos Inicial',
            'passport_number' => '123456',
            'origin' => 'VVI',
            'destination' => 'LPB',
            'flight_date' => '2026-10-15',
            'flight_number' => 'TM-101',
            'passengers_count' => 1,
            'total_price' => 450.00,
            'status' => 'nuevo',
        ]);

        $response = $this->actingAs($agent, 'sanctum')->putJson("/api/leads/{$lead->id}", [
            'passenger_name' => 'Carlos Modificado',
            'destination' => 'CBB',
            'total_price' => 520.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lead.passenger_name', 'Carlos Modificado')
            ->assertJsonPath('lead.destination', 'CBB');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'passenger_name' => 'Carlos Modificado',
            'destination' => 'CBB',
        ]);
    }
}
