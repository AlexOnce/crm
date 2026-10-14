<?php

namespace Tests\Feature;

use App\Jobs\ProcessMassBroadcastJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MassBroadcastSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_sms_recipients(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $conv = Conversation::create([
            'phone' => '59174844348',
            'contact_name' => 'Carlos Arce',
            'status' => 'bot_active',
        ]);

        Lead::create([
            'conversation_id' => $conv->id,
            'phone' => '59174844348',
            'passenger_name' => 'Carlos Arce',
            'flight_number' => 'TM-716',
            'flight_date' => now()->format('Y-m-d'),
            'status' => 'emitido',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/preview', [
                'flight_number' => 'TM-716',
                'flight_date' => now()->format('Y-m-d'),
                'status' => 'emitido',
                'message' => 'TAMEP Informa: Su vuelo {vuelo} ha sido demorado.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_recipients' => 1,
            ])
            ->assertJsonStructure([
                'sample_recipients',
                'sms_metrics' => [
                    'chars_count',
                    'sms_per_recipient',
                    'total_sms_estimated',
                ],
            ]);
    }

    public function test_admin_can_create_broadcast_campaign_and_dispatch_sms_job(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $conv = Conversation::create([
            'phone' => '74844348',
            'contact_name' => 'Marcelo Martins',
            'status' => 'bot_active',
        ]);

        $lead = Lead::create([
            'conversation_id' => $conv->id,
            'phone' => '74844348',
            'passenger_name' => 'Marcelo Martins',
            'flight_number' => 'TM-101',
            'flight_date' => now()->format('Y-m-d'),
            'status' => 'emitido',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts', [
                'title' => 'Aviso Operacional SMS GoIP',
                'flight_number' => 'TM-101',
                'flight_date' => now()->format('Y-m-d'),
                'status' => 'emitido',
                'channel' => 'sms',
                'message' => 'TAMEP Informa: Estimado pasajero {pasajero}, su vuelo {vuelo} se encuentra a tiempo.',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'campaign' => [
                    'title' => 'Aviso Operacional SMS GoIP',
                    'channel' => 'sms',
                    'total_recipients' => 1,
                ],
            ]);

        $this->assertDatabaseHas('broadcast_campaigns', [
            'title' => 'Aviso Operacional SMS GoIP',
            'channel' => 'sms',
            'total_recipients' => 1,
        ]);

        $this->assertDatabaseHas('broadcast_recipients', [
            'phone' => '74844348',
            'lead_id' => $lead->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessMassBroadcastJob::class);
    }

    public function test_agent_is_forbidden_from_broadcast_campaigns(): void
    {
        $agent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
        ]);
        $token = $agent->createToken('agent-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/broadcasts');

        $response->assertStatus(403);
    }

    public function test_admin_can_retry_failed_recipients(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $campaign = BroadcastCampaign::create([
            'user_id' => $admin->id,
            'title' => 'Campaña con Fallo',
            'message' => 'TAMEP Informa: Prueba',
            'channel' => 'sms',
            'total_recipients' => 1,
            'failed_count' => 1,
            'status' => 'completed',
        ]);

        BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '74844348',
            'passenger_name' => 'Pasajero Fallido',
            'message' => 'TAMEP Informa: Prueba',
            'status' => 'failed',
            'error_message' => 'GoIP Gateway Timeout',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/broadcasts/{$campaign->id}/retry-failed");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('broadcast_recipients', [
            'status' => 'pending',
            'error_message' => null,
        ]);

        Queue::assertPushed(ProcessMassBroadcastJob::class);
    }

    public function test_admin_can_import_pdf_flight_manifest(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/import-pdf', [
                'use_default_doc' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'manifest' => [
                    'flight_number' => '708',
                    'total_passengers' => 66,
                    'valid_phones_count' => 66,
                ],
            ])
            ->assertJsonStructure([
                'manifest' => [
                    'flight_number',
                    'departure_date',
                    'departure_time',
                    'origin',
                    'destination',
                    'total_passengers',
                    'valid_phones_count',
                    'passengers',
                ],
            ]);
    }
}
