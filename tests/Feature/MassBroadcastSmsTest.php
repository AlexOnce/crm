<?php

namespace Tests\Feature;

use App\Jobs\ProcessMassBroadcastJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Services\FlightMicroservice;
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

    public function test_message_personalization_sends_only_paternal_surname_and_predefined_message(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        // 1. Mensaje predefinido sin placeholder explícito: debe anteponer el apellido paterno
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/preview', [
                'message' => 'EPTAM informa: El vuelo 302 La Paz-Santa Cruz de las 15:30 fue cancelado. Para mayor informacion, comuniquese con nuestra oficina o espere nuevas instrucciones.',
                'manual_phones' => '73748234',
                'manifest_passengers' => [
                    [
                        'passenger_name' => 'CALLIZAYA ANTONIO VICTOR HUGO',
                        'paternal_surname' => 'CALLIZAYA',
                        'phone' => '73748234',
                        'origin' => 'VVI',
                        'destination' => 'LPB',
                        'flight_number' => '302',
                    ],
                ],
            ]);

        $response1->assertStatus(200);
        $sampleMsg1 = $response1->json('sample_message');
        $this->assertStringStartsWith('Sr/Sra CALLIZAYA: EPTAM informa:', $sampleMsg1);
        $this->assertStringNotContainsString('ANTONIO', $sampleMsg1);
        $this->assertStringNotContainsString('VICTOR', $sampleMsg1);
        $this->assertStringNotContainsString('HUGO', $sampleMsg1);

        // 2. Mensaje con fórmula 'Estimado pasajero {apellido}:' debe normalizarse a 'Sr/Sra {apellido}:'
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/preview', [
                'message' => 'Estimado pasajero {apellido}: TAMEP informa que su vuelo {vuelo} fue cancelado por mantenimiento.',
                'manual_phones' => '73748234',
                'manifest_passengers' => [
                    [
                        'passenger_name' => 'MENDOZA QUISPE JUAN',
                        'paternal_surname' => 'MENDOZA',
                        'phone' => '73748234',
                        'flight_number' => '702',
                    ],
                ],
            ]);

        $response2->assertStatus(200);
        $sampleMsg2 = $response2->json('sample_message');
        $this->assertEquals('Sr/Sra MENDOZA: TAMEP informa que su vuelo 702 fue cancelado por mantenimiento.', $sampleMsg2);
        $this->assertStringNotContainsString('Estimado pasajero', $sampleMsg2);
        $this->assertStringNotContainsString('QUISPE', $sampleMsg2);
    }

    public function test_manual_flight_time_overrides_manifest_departure_time(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        // Pasajero del manifiesto original tenía hora 08:00, pero se cambió manualmente a 12:00
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/preview', [
                'message' => 'Sr/Sra {apellido}: TAMEP informa que su vuelo {vuelo} de las {hora} fue reprogramado para las 12:00.',
                'flight_number' => '708',
                'flight_time' => '12:00', // Modificación manual
                'manifest_passengers' => [
                    [
                        'passenger_name' => 'ARCE ZACONETA CARLOS',
                        'paternal_surname' => 'ARCE',
                        'phone' => '74844348',
                        'departure_time' => '08:00', // Original del PDF
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $msg = $response->json('sample_message');
        $this->assertStringContainsString('Sr/Sra ARCE:', $msg);
        $this->assertStringContainsString('12:00', $msg);
    }

    public function test_admin_can_process_sms_queue_on_demand(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/process-queue');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_check_flight_status_via_microservice(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $mockFlightService = $this->createMock(FlightMicroservice::class);
        $mockFlightService->method('searchFlights')->willReturn([
            [
                'flight_number' => 'TM-708',
                'departure_time' => '19:00',
                'departure_date' => '2026-10-15',
                'origin' => 'VVI',
                'destination' => 'LPB',
                'origin_city' => 'Santa Cruz',
                'destination_city' => 'La Paz',
                'status' => 'Programado',
            ],
        ]);
        $this->app->instance(FlightMicroservice::class, $mockFlightService);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/check-flight', [
                'flight_number' => '708',
                'flight_date' => '2026-10-15',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'found' => true,
                'departure_time' => '19:00',
                'origin' => 'VVI',
                'destination' => 'LPB',
            ]);
    }
}
