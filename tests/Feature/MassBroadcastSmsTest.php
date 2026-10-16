<?php

namespace Tests\Feature;

use App\Jobs\ProcessMassBroadcastJob;
use App\Jobs\SendGoipSmsNotification;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Services\FlightMicroservice;
use App\Services\GoipSmsService;
use App\Services\MetaWhatsAppService;
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

    public function test_admin_can_search_flights_by_date_only_without_origin_and_destination(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $mockFlightService = $this->createMock(FlightMicroservice::class);
        $mockFlightService->method('searchRoutesParallel')->willReturn([
            [
                'flight_number' => 'TM-706',
                'departure_time' => '15:40',
                'departure_date' => '2026-10-15',
                'origin' => 'VVI',
                'destination' => 'CBB',
                'origin_city' => 'Santa Cruz',
                'destination_city' => 'Cochabamba',
                'status' => 'Programado',
            ],
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
                'flight_date' => '2026-10-15',
                'origin' => '',
                'destination' => '',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'found' => true,
            ]);

        $this->assertCount(2, $response->json('flights'));
    }

    public function test_admin_can_filter_broadcast_campaigns_by_flight_and_date(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $c1 = BroadcastCampaign::create([
            'user_id' => $admin->id,
            'title' => 'Aviso Vuelo TM-708 - 15/10/2026',
            'channel' => 'sms',
            'template_type' => 'flight_delay',
            'message' => 'Test message',
            'filters' => [
                'flight_number' => '708',
                'flight_date' => '2026-10-15',
            ],
            'total_recipients' => 10,
            'status' => 'completed',
        ]);
        $c1->created_at = '2026-10-15 10:00:00';
        $c1->saveQuietly();

        $c2 = BroadcastCampaign::create([
            'user_id' => $admin->id,
            'title' => 'Aviso Vuelo TM-702 - 16/10/2026',
            'channel' => 'sms',
            'template_type' => 'flight_cancellation',
            'message' => 'Other flight message',
            'filters' => [
                'flight_number' => '702',
                'flight_date' => '2026-10-16',
            ],
            'total_recipients' => 5,
            'status' => 'completed',
        ]);

        // Filtrar por vuelo 708
        $resFlight = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/broadcasts?flight_number=708');

        $resFlight->assertStatus(200);
        $this->assertCount(1, $resFlight->json('campaigns.data'));
        $this->assertEquals($c1->id, $resFlight->json('campaigns.data.0.id'));

        // Filtrar por fecha 2026-10-16
        $resDate = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/broadcasts?date=2026-10-16');

        $resDate->assertStatus(200);
        $this->assertCount(1, $resDate->json('campaigns.data'));
        $this->assertEquals($c2->id, $resDate->json('campaigns.data.0.id'));
    }

    public function test_admin_can_get_goip_gateway_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $mockGoip = $this->createMock(GoipSmsService::class);
        $mockGoip->method('checkGatewayHealth')->willReturn([
            'online' => true,
            'host' => '186.121.251.149',
            'port' => 80,
            'line' => 1,
            'status_message' => 'Gateway GoIP alcanzable en red local/VPN.',
            'latency_ms' => 45,
            'compliance' => [
                'hourly_sent' => 5,
                'daily_sent' => 18,
                'max_hourly' => 120,
                'max_daily' => 800,
                'safe_status' => 'safe',
            ],
        ]);
        $this->app->instance(GoipSmsService::class, $mockGoip);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/broadcasts/gateway-status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'gateway' => [
                    'online' => true,
                    'line' => 1,
                ],
            ]);
    }

    public function test_admin_can_validate_entel_compliance(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        // 1. Mensaje no conforme (sin identificación y con enlace sospechoso)
        $resNonCompliant = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/validate-compliance', [
                'message' => 'Haga clic en bit.ly/vuelo para ver su reprogramacion.',
            ]);

        $resNonCompliant->assertStatus(200);
        $this->assertFalse($resNonCompliant->json('compliance.compliant'));
        $this->assertNotEmpty($resNonCompliant->json('compliance.warnings'));

        // 2. Mensaje conforme con normativa ATT y Entel
        $resCompliant = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/validate-compliance', [
                'message' => 'TAMep Informa: Estimado pasajero, su vuelo TM-708 se encuentra confirmado.',
            ]);

        $resCompliant->assertStatus(200);
        $this->assertTrue($resCompliant->json('compliance.compliant'));
        $this->assertEmpty($resCompliant->json('compliance.warnings'));
    }

    public function test_admin_can_cancel_broadcast_campaign(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $campaign = BroadcastCampaign::create([
            'user_id' => $admin->id,
            'title' => 'Campaña a Cancelar',
            'message' => 'TAMep Informa: Prueba cancelacion',
            'channel' => 'sms',
            'total_recipients' => 2,
            'sent_count' => 0,
            'failed_count' => 0,
            'status' => 'processing',
        ]);

        $r1 = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '74844348',
            'passenger_name' => 'Pasajero 1',
            'message' => 'TAMep Informa: Prueba cancelacion',
            'status' => 'pending',
        ]);

        $r2 = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '71523456',
            'passenger_name' => 'Pasajero 2',
            'message' => 'TAMep Informa: Prueba cancelacion',
            'status' => 'processing',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/broadcasts/{$campaign->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'cancelled_count' => 2,
            ]);

        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaign->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('broadcast_recipients', [
            'id' => $r1->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('broadcast_recipients', [
            'id' => $r2->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_preview_and_store_supports_manual_passengers_with_surname(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // 1. Probar preview con entrada manual con apellido ("74844348 - PEREZ")
        $previewRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts/preview', [
                'message' => 'TAMep Informa: Vuelo reprogramado a las 17:00.',
                'manual_phones' => "74844348 - PEREZ\n71234567 MAMANI",
                'flight_number' => '707',
            ]);

        $previewRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_recipients' => 2,
            ]);

        $sampleRecipients = $previewRes->json('sample_recipients');
        $this->assertEquals('PEREZ', $sampleRecipients[0]['passenger_name']);
        $this->assertEquals('74844348', $sampleRecipients[0]['phone']);
        $this->assertEquals('MAMANI', $sampleRecipients[1]['passenger_name']);
        $this->assertEquals('71234567', $sampleRecipients[1]['phone']);

        // El mensaje simulado debe incluir el apellido del primer destinatario
        $this->assertStringStartsWith('Sr/Sra PEREZ: TAMep Informa:', $previewRes->json('sample_message'));

        // 2. Probar creación de campaña con destinatario manual con apellido
        $storeRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts', [
                'title' => 'Aviso Manual Reprogramado',
                'message' => 'TAMep Informa: Vuelo reprogramado a las 17:00.',
                'manual_phones' => '74844348 - PEREZ',
                'flight_number' => '707',
            ]);

        $storeRes->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('broadcast_recipients', [
            'phone' => '74844348',
            'passenger_name' => 'PEREZ',
            'flight_number' => '707',
            'status' => 'pending',
        ]);

        $recipient = BroadcastRecipient::where('phone', '74844348')->latest()->first();
        $this->assertNotNull($recipient);
        $this->assertStringStartsWith('Sr/Sra PEREZ: TAMep Informa:', $recipient->message);
    }

    public function test_goip_service_alternates_between_line_1_and_line_2(): void
    {
        config(['services.goip.lines' => [1, 2]]);
        $service = app(GoipSmsService::class);

        $lineA = $service->getNextAlternatingLine();
        $lineB = $service->getNextAlternatingLine();
        $lineC = $service->getNextAlternatingLine();
        $lineD = $service->getNextAlternatingLine();

        $this->assertContains($lineA, [1, 2]);
        $this->assertContains($lineB, [1, 2]);
        $this->assertNotEquals($lineA, $lineB);
        $this->assertEquals($lineA, $lineC);
        $this->assertEquals($lineB, $lineD);
    }

    public function test_process_mass_broadcast_job_interleaves_lines_between_recipients(): void
    {
        Queue::fake();
        config(['services.goip.lines' => [1, 2]]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $campaign = BroadcastCampaign::create([
            'user_id' => $admin->id,
            'title' => 'Campaña Intercalada Dual-SIM',
            'message' => 'TAMep Informa: Prueba Intercalado',
            'channel' => 'sms',
            'total_recipients' => 4,
            'status' => 'pending',
        ]);

        $r1 = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '74844348',
            'message' => 'TAMep Informa: Pasajero 1',
            'status' => 'pending',
        ]);

        $r2 = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '71234567',
            'message' => 'TAMep Informa: Pasajero 2',
            'status' => 'pending',
        ]);

        $r3 = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '76543210',
            'message' => 'TAMep Informa: Pasajero 3',
            'status' => 'pending',
        ]);

        $r4 = BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '79998877',
            'message' => 'TAMep Informa: Pasajero 4',
            'status' => 'pending',
        ]);

        $job = new ProcessMassBroadcastJob($campaign);
        $job->handle(app(MetaWhatsAppService::class));

        // Verificar que se despacharon 4 jobs a SendGoipSmsNotification
        Queue::assertPushed(SendGoipSmsNotification::class, 4);

        // Verificar que los jobs alternaron 1, 2, 1, 2 en los slots
        Queue::assertPushed(SendGoipSmsNotification::class, function (SendGoipSmsNotification $job) use ($r1) {
            return $job->phone === $r1->phone && $job->line === 1;
        });

        Queue::assertPushed(SendGoipSmsNotification::class, function (SendGoipSmsNotification $job) use ($r2) {
            return $job->phone === $r2->phone && $job->line === 2;
        });

        Queue::assertPushed(SendGoipSmsNotification::class, function (SendGoipSmsNotification $job) use ($r3) {
            return $job->phone === $r3->phone && $job->line === 1;
        });

        Queue::assertPushed(SendGoipSmsNotification::class, function (SendGoipSmsNotification $job) use ($r4) {
            return $job->phone === $r4->phone && $job->line === 2;
        });
    }

    public function test_process_mass_broadcast_job_can_send_exclusively_through_port_1(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $campaign = BroadcastCampaign::create([
            'user_id' => $admin->id,
            'title' => 'Campaña Solo Puerto 1',
            'message' => 'TAMep Informa: Solo Puerto 1',
            'channel' => 'sms',
            'filters' => ['goip_line' => '1'],
            'total_recipients' => 2,
            'status' => 'pending',
        ]);

        BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '74844348',
            'message' => 'TAMep Informa: Pasajero 1',
            'status' => 'pending',
        ]);

        BroadcastRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '71234567',
            'message' => 'TAMep Informa: Pasajero 2',
            'status' => 'pending',
        ]);

        $job = new ProcessMassBroadcastJob($campaign);
        $job->handle(app(MetaWhatsAppService::class));

        Queue::assertPushed(SendGoipSmsNotification::class, 2);
        Queue::assertPushed(SendGoipSmsNotification::class, function (SendGoipSmsNotification $job) {
            return $job->line === 1;
        });
    }

    public function test_admin_can_store_campaign_with_mixed_channel_and_specific_port(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/broadcasts', [
                'title' => 'Campaña Mixta Puerto 2',
                'channel' => 'hybrid',
                'goip_line' => '2',
                'message' => 'TAMep Informa: Estimado pasajero, aviso operacional.',
                'manual_phones' => '74844348 - FLORES',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'campaign' => [
                    'title' => 'Campaña Mixta Puerto 2',
                    'channel' => 'hybrid',
                ],
            ]);

        $campaign = BroadcastCampaign::where('title', 'Campaña Mixta Puerto 2')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals('hybrid', $campaign->channel);
        $this->assertEquals('2', $campaign->filters['goip_line']);
    }
}
