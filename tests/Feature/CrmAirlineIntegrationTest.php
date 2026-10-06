<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Services\FlightMicroservice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrmAirlineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simular llamadas salientes hacia Meta Cloud API y Flight Service
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.HBg123']]], 200),
            'http://127.0.0.1:8080/*' => Http::response(['status' => 'SUCCESS'], 200),
        ]);
    }

    public function test_meta_webhook_challenge_handshake_succeeds(): void
    {
        $verifyToken = config('services.meta.verify_token', 'crm_airline_verify_token_2026');

        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $verifyToken,
            'hub_challenge' => 'CHALLENGE_ACCEPTED_7788',
        ]));

        $response->assertStatus(200);
        $this->assertEquals('CHALLENGE_ACCEPTED_7788', $response->getContent());
    }

    public function test_root_meta_webhook_challenge_handshake_succeeds(): void
    {
        $verifyToken = config('services.meta.verify_token', 'crm_airline_verify_token_2026');

        $response = $this->get('/webhook/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $verifyToken,
            'hub_challenge' => 'CHALLENGE_ROOT_OK_9999',
        ]));

        $response->assertStatus(200);
        $this->assertEquals('CHALLENGE_ROOT_OK_9999', $response->getContent());
    }

    public function test_meta_webhook_challenge_rejects_invalid_token(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'invalid_token_123',
            'hub_challenge' => 'CHALLENGE_ACCEPTED_7788',
        ]));

        $response->assertStatus(403);
    }

    public function test_meta_webhook_processes_official_payload(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1029384756',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59170011223',
                                    'phone_number_id' => '10982374',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Diego Morales'],
                                        'wa_id' => '59170011223',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59170011223',
                                        'id' => 'wamid.HBgMTA5ODIzNzQx',
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Hola'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv = Conversation::where('phone', '59170011223')->first();
        $this->assertNotNull($conv);
        $this->assertEquals('Diego Morales', $conv->contact_name);
        $this->assertEquals('MENU_PRINCIPAL', $conv->current_state);
        $this->assertEquals('bot_active', $conv->status);
    }

    public function test_agent_can_login_take_chat_reply_and_issue_ticket(): void
    {
        $agent = User::factory()->create([
            'email' => 'agente@aerolinea.com',
            'password' => bcrypt('password123'),
            'role' => 'agent',
        ]);

        $conv = Conversation::create([
            'phone' => '59176543210',
            'contact_name' => 'Camila Rojas',
            'status' => 'waiting_agent',
            'current_state' => 'AGENT_HANDOFF',
        ]);

        $lead = Lead::create([
            'conversation_id' => $conv->id,
            'phone' => $conv->phone,
            'passenger_name' => 'Camila Rojas',
            'passport_number' => 'BO-998877',
            'origin' => 'VVI',
            'destination' => 'LPB',
            'flight_date' => now()->addDay()->format('Y-m-d'),
            'flight_number' => 'TM-204',
            'total_price' => 450.00,
            'status' => 'nuevo',
        ]);

        // Login
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'agente@aerolinea.com',
            'password' => 'password123',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('token');

        // Tomar Chat
        $takeRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/conversations/{$conv->id}/take");

        $takeRes->assertStatus(200);
        $conv->refresh();
        $this->assertEquals('human_live', $conv->status);

        // Responder como Agente por WhatsApp (Meta Cloud API)
        $replyRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/conversations/{$conv->id}/reply", [
                'message' => 'Hola Camila, tus datos están validados.',
            ]);

        $replyRes->assertStatus(200);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'agent',
            'agent_id' => $agent->id,
        ]);

        // Emitir Boleto Transaccional
        $issueRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/leads/{$lead->id}/issue-ticket", [
                'passenger_name' => 'Camila Rojas',
                'passport_number' => 'BO-998877',
                'origin' => 'VVI',
                'destination' => 'LPB',
                'flight_date' => now()->addDay()->format('Y-m-d'),
                'flight_number' => 'TM-204',
                'total_price' => 450.00,
            ]);

        $issueRes->assertStatus(200);
        $lead->refresh();
        $this->assertEquals('emitido', $lead->status);
        $this->assertNotEmpty($lead->booking_reference);

        // Devolver al Bot
        $releaseRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/conversations/{$conv->id}/release");

        $releaseRes->assertStatus(200);
        $conv->refresh();
        $this->assertEquals('bot_active', $conv->status);
    }

    public function test_incoming_audio_voice_note_triggers_download_and_handoff(): void
    {
        // Fake Meta Graph API endpoints for media metadata and download
        Http::fake([
            'https://graph.facebook.com/*/audio_media_123' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/whatsapp_media/sample_audio.ogg',
                'mime_type' => 'audio/ogg; codecs=opus',
                'file_size' => 1024,
                'id' => 'audio_media_123',
            ], 200),
            'https://lookaside.fbsbx.com/*' => Http::response('FAKE_OGG_BINARY_CONTENT', 200, [
                'Content-Type' => 'audio/ogg',
            ]),
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.HBg999']],
            ], 200),
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550001',
                                    'phone_number_id' => 'PHONE_ID_123',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Marcos Morales'],
                                        'wa_id' => '59178889900',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59178889900',
                                        'id' => 'wamid.TEST_AUDIO_MSG',
                                        'timestamp' => (string) time(),
                                        'type' => 'audio',
                                        'audio' => [
                                            'id' => 'audio_media_123',
                                            'mime_type' => 'audio/ogg; codecs=opus',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        // Validar que la conversación se creó y pasó a waiting_agent
        $conv = Conversation::where('phone', '59178889900')->first();
        $this->assertNotNull($conv);
        $this->assertEquals('waiting_agent', $conv->status);

        // Validar que se guardó el mensaje del usuario con media_type audio
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'user',
            'media_type' => 'audio',
        ]);

        // Validar que el bot respondió notificando que se transfiere a un asesor humano
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'bot',
        ]);

        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertStringContainsString('asesor', strtolower($botMsg->message));
        $this->assertStringNotContainsString('aerolíneas!', strtolower($botMsg->message));
    }

    public function test_agent_can_reply_with_audio_file(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.HBgAgentAudio']],
            ], 200),
        ]);

        $agent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
            'email' => 'agente_audio@aerolinea.com',
            'password' => bcrypt('password123'),
        ]);

        $conv = Conversation::create([
            'phone' => '59175554433',
            'contact_name' => 'Pasajero Audio',
            'status' => 'waiting_agent',
            'current_state' => 'HUMAN_INTERVENTION',
        ]);

        $audioFile = UploadedFile::fake()->create('audio_respuesta.ogg', 50, 'audio/ogg');

        $response = $this->actingAs($agent, 'sanctum')->postJson("/api/conversations/{$conv->id}/reply", [
            'message' => 'Estimado pasajero, aquí tiene el detalle en audio.',
            'image' => $audioFile,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'agent',
            'media_type' => 'audio',
        ]);

        $conv->refresh();
        $this->assertEquals('human_live', $conv->status);
    }

    public function test_itinerary_selection_hides_ticket_prices_and_uses_interactive_buttons(): void
    {
        $mockFlightService = $this->createMock(FlightMicroservice::class);
        $mockFlightService->method('searchFlights')->willReturn([
            [
                'flight_number' => 'TM-204',
                'origin' => 'VVI',
                'origin_city' => 'Santa Cruz',
                'destination' => 'LPB',
                'destination_city' => 'La Paz',
                'departure_time' => '07:30',
                'arrival_time' => '08:35',
                'aircraft' => 'Avro RJ70 CP-3106',
                'price' => 450.00,
            ],
        ]);
        $mockFlightService->method('getCityName')->willReturnCallback(fn ($c) => $c === 'VVI' ? 'Santa Cruz' : 'La Paz');
        $this->app->instance(FlightMicroservice::class, $mockFlightService);

        $conv = Conversation::create([
            'phone' => '59173332211',
            'contact_name' => 'Carlos Rojas',
            'status' => 'bot_active',
            'current_state' => 'R1_SOLICITAR_FECHA',
            'session_data' => [
                'origin' => 'VVI',
                'destination' => 'LPB',
            ],
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550001',
                                    'phone_number_id' => 'PHONE_ID_123',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Carlos Rojas'],
                                        'wa_id' => '59173332211',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59173332211',
                                        'id' => 'wamid.FECHA_MSG',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'mañana'],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();
        $this->assertEquals('VUELOS_MOSTRADOS', $conv->current_state);

        // Obtener último mensaje del bot
        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNotNull($botMsg);

        // 1. Debe contener los itinerarios y los números de vuelo
        $this->assertStringContainsString('ITINERARIOS DISPONIBLES', $botMsg->message);
        $this->assertMatchesRegularExpression('/TM-\d+/', $botMsg->message);

        // 2. NO DEBE mostrar el precio o la tarifa al pasajero
        $this->assertStringNotContainsString('450', $botMsg->message);
        $this->assertStringNotContainsString('520', $botMsg->message);
        $this->assertStringNotContainsString('410', $botMsg->message);
        $this->assertStringNotContainsString('BOB', $botMsg->message);
        $this->assertStringNotContainsString('Tarifa:', $botMsg->message);

        // 3. Simular que el cliente pulsa el botón interactivo de WhatsApp "TM-204 (07:30)"
        $buttonPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550001',
                                    'phone_number_id' => 'PHONE_ID_123',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Carlos Rojas'],
                                        'wa_id' => '59173332211',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59173332211',
                                        'id' => 'wamid.BTN_CLICK',
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'button_reply',
                                            'button_reply' => [
                                                'id' => 'flight_1',
                                                'title' => 'TM-204 (07:30)',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $buttonResponse = $this->postJson('/api/webhooks/whatsapp', $buttonPayload);
        $buttonResponse->assertStatus(200);

        $conv->refresh();
        $this->assertEquals('R2_SOLICITAR_DATOS', $conv->current_state);
        $this->assertNotEmpty($conv->session_data['selected_flight']['flight_number']);
        // El precio se conserva internamente para la cotización y el agente
        $this->assertNotNull($conv->session_data['selected_flight']['price']);

        // El mensaje de confirmación del bot tampoco muestra el precio
        $confirmationMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertStringNotContainsString('450', $confirmationMsg->message);
        $this->assertStringNotContainsString('BOB', $confirmationMsg->message);
    }

    public function test_bot_auto_reactivates_after_30_minutes_of_inactivity_when_user_messages(): void
    {
        $conv = Conversation::create([
            'phone' => '59170099881',
            'contact_name' => 'Pasajero Inactivo',
            'status' => 'human_live',
            'current_state' => 'HUMAN_LIVE',
            'last_interaction_at' => now()->subMinutes(35),
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [['profile' => ['name' => 'Pasajero Inactivo'], 'wa_id' => '59170099881']],
                                'messages' => [
                                    [
                                        'from' => '59170099881',
                                        'id' => 'wamid.INACTIVE_MSG_1',
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Hola, quiero consultar un vuelo'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();
        $this->assertEquals('bot_active', $conv->status);
        $this->assertEquals('MENU_PRINCIPAL', $conv->current_state);

        // El bot responde con el menú principal (buscador interactivo o menú general)
        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNotNull($botMsg);
        $this->assertTrue(
            str_contains($botMsg->message, 'TAMep') || str_contains($botMsg->message, 'MENÚ PRINCIPAL')
        );
    }

    public function test_bot_does_not_reactivate_if_inactivity_is_under_30_minutes(): void
    {
        $conv = Conversation::create([
            'phone' => '59170099882',
            'contact_name' => 'Pasajero Activo',
            'status' => 'human_live',
            'current_state' => 'HUMAN_LIVE',
            'last_interaction_at' => now()->subMinutes(10),
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [['profile' => ['name' => 'Pasajero Activo'], 'wa_id' => '59170099882']],
                                'messages' => [
                                    [
                                        'from' => '59170099882',
                                        'id' => 'wamid.ACTIVE_MSG_1',
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Asesor, sigo aquí esperando la cotización'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();
        $this->assertEquals('human_live', $conv->status);

        // No hay respuesta del bot porque está en atención humana activa
        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNull($botMsg);
    }

    public function test_artisan_command_auto_releases_inactive_conversations(): void
    {
        $convInactive = Conversation::create([
            'phone' => '59170099883',
            'contact_name' => 'Cliente Inactivo 40min',
            'status' => 'human_live',
            'current_state' => 'HUMAN_LIVE',
            'last_interaction_at' => now()->subMinutes(40),
        ]);

        $convActive = Conversation::create([
            'phone' => '59170099884',
            'contact_name' => 'Cliente Reciente 5min',
            'status' => 'human_live',
            'current_state' => 'HUMAN_LIVE',
            'last_interaction_at' => now()->subMinutes(5),
        ]);

        Artisan::call('crm:auto-release-inactive', ['--minutes' => 30]);

        $convInactive->refresh();
        $convActive->refresh();

        $this->assertEquals('bot_active', $convInactive->status);
        $this->assertEquals('MENU_PRINCIPAL', $convInactive->current_state);

        $this->assertEquals('human_live', $convActive->status);
    }

    public function test_conversation_details_endpoint_returns_all_leads_history_ordered(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);

        $conv = Conversation::create([
            'phone' => '59178822334',
            'contact_name' => 'Mario Vargas',
            'status' => 'human_live',
        ]);

        $leadPast = Lead::create([
            'conversation_id' => $conv->id,
            'phone' => $conv->phone,
            'passenger_name' => 'Mario Vargas',
            'passport_number' => 'CI: 112233',
            'origin' => 'LPB',
            'destination' => 'CIJ',
            'flight_date' => '2026-08-15',
            'flight_number' => 'TM-301',
            'total_price' => 520.00,
            'status' => 'emitido',
            'created_at' => now()->subDays(10),
        ]);

        $leadLatest = Lead::create([
            'conversation_id' => $conv->id,
            'phone' => $conv->phone,
            'passenger_name' => 'Mario Vargas',
            'passport_number' => 'CI: 112233',
            'origin' => 'VVI',
            'destination' => 'LPB',
            'flight_date' => '2026-10-01',
            'flight_number' => 'TM-702',
            'total_price' => 450.00,
            'status' => 'nuevo',
            'created_at' => now(),
        ]);

        $res = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/conversations/{$conv->id}");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $json = $res->json();
        $this->assertEquals($leadLatest->id, $json['lead']['id']);
        $this->assertCount(2, $json['leads']);
        $this->assertEquals($leadLatest->id, $json['leads'][0]['id']);
        $this->assertEquals($leadPast->id, $json['leads'][1]['id']);
    }
}
