<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketIssuanceAndCounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_ticket_issuance_marks_emitido_finalizes_attention_and_does_not_message_passenger(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_test@tamep.bo',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-test')->plainTextToken;

        // 1. Verificar contadores iniciales en /api/admin/metrics
        $metricsBefore = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/metrics')
            ->assertStatus(200)
            ->json();

        $initialTotalTickets = $metricsBefore['kpis']['total_tickets'];
        $initialTicketsToday = $metricsBefore['kpis']['tickets_today'];
        $initialEmitidosCount = $metricsBefore['counts']['emitido'];

        // 2. Crear conversación y Lead en estado 'nuevo'
        $conversation = Conversation::create([
            'phone' => '59174844348',
            'contact_name' => 'Erlan Alex Condori',
            'status' => 'human_live',
            'assigned_user_id' => $admin->id,
        ]);

        $lead = Lead::create([
            'conversation_id' => $conversation->id,
            'phone' => '59174844348',
            'passenger_name' => 'Erlan Alex Condori',
            'passport_number' => 'CI: 9916791',
            'origin' => 'LPB',
            'destination' => 'VVI',
            'flight_date' => '2026-10-15',
            'flight_number' => 'TM-705',
            'passengers_count' => 2,
            'total_price' => 2236.00,
            'status' => 'nuevo',
        ]);

        // 3. Emitir el boleto vía POST /api/leads/{id}/issue-ticket
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/leads/{$lead->id}/issue-ticket", [
                'passenger_name' => 'Erlan Alex Condori',
                'passport_number' => 'CI: 9916791',
                'origin' => 'LPB',
                'destination' => 'VVI',
                'flight_date' => '2026-10-15',
                'flight_number' => 'TM-705',
                'passengers_count' => 2,
                'total_price' => 2236.00,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Asegurar que NO se envió ningún mensaje hacia WhatsApp al pasajero ni al chat
        Http::assertNothingSent();
        $this->assertEquals(0, ChatMessage::where('conversation_id', $conversation->id)->count());

        // Verificar que el Lead quedó marcado como 'emitido' con PNR en el sistema
        $lead->refresh();
        $this->assertEquals('emitido', $lead->status);
        $this->assertNotEmpty($lead->booking_reference);

        // Verificar que la atención fue finalizada y devuelta al bot
        $conversation->refresh();
        $this->assertEquals('bot_active', $conversation->status);
        $this->assertEquals('MENU_PRINCIPAL', $conversation->current_state);

        // 4. Verificar que el contador en el admin dashboard subió exactamente en 2 (por los 2 pasajeros)
        $metricsAfter = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/metrics')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($initialTotalTickets + 2, $metricsAfter['kpis']['total_tickets']);
        $this->assertEquals($initialTicketsToday + 2, $metricsAfter['kpis']['tickets_today']);
        $this->assertEquals($initialEmitidosCount + 2, $metricsAfter['counts']['emitido']);

        // 5. Verificar que la pestaña de tickets también cuenta los 2 boletos emitidos
        $ticketsListRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/tickets?status=emitido')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($initialEmitidosCount + 2, $ticketsListRes['counts']['emitido']);
        $this->assertTrue(collect($ticketsListRes['tickets']['data'])->contains('id', $lead->id));
    }

    public function test_five_passengers_booking_registers_five_tickets_in_metrics_and_agent_record(): void
    {
        $agent = User::factory()->create([
            'email' => 'agent_sales@tamep.bo',
            'role' => 'agent',
            'is_active' => true,
        ]);
        $token = $agent->createToken('agent-token')->plainTextToken;

        $admin = User::factory()->create([
            'email' => 'admin_supervisor@tamep.bo',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $adminToken = $admin->createToken('admin-supervisor')->plainTextToken;

        // Métricas iniciales
        $metricsBefore = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/admin/metrics')
            ->json();

        $initialTotalTickets = (int) $metricsBefore['kpis']['total_tickets'];

        // Crear conversación y Lead con 5 pasajeros
        $conversation = Conversation::create([
            'phone' => '59178877665',
            'contact_name' => 'Familia Morales',
            'status' => 'human_live',
            'assigned_user_id' => $agent->id,
        ]);

        $lead = Lead::create([
            'conversation_id' => $conversation->id,
            'phone' => '59178877665',
            'passenger_name' => 'Carlos Morales',
            'passport_number' => 'CI: 4567890',
            'origin' => 'LPB',
            'destination' => 'VVI',
            'flight_date' => '2026-11-20',
            'flight_number' => 'TM-301',
            'passengers_count' => 5,
            'total_price' => 2250.00,
            'status' => 'nuevo',
        ]);

        // Emitir venta de los 5 boletos
        $issueRes = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/leads/{$lead->id}/issue-ticket", [
                'passenger_name' => 'Carlos Morales',
                'passport_number' => 'CI: 4567890',
                'origin' => 'LPB',
                'destination' => 'VVI',
                'flight_date' => '2026-11-20',
                'flight_number' => 'TM-301',
                'passengers_count' => 5,
                'total_price' => 2250.00,
            ]);

        $issueRes->assertStatus(200);
        $lead->refresh();
        $this->assertEquals('emitido', $lead->status);
        $this->assertEquals(5, $lead->passengers_count);

        // Verificar que las métricas reflejan exactamente 5 boletos más
        $metricsAfter = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/metrics')
            ->json();

        $this->assertEquals($initialTotalTickets + 5, $metricsAfter['kpis']['total_tickets']);

        $agentStats = collect($metricsAfter['agents'])->firstWhere('id', $agent->id);
        $this->assertNotNull($agentStats);
        $this->assertEquals(5, $agentStats['tickets_count']);
    }
}
