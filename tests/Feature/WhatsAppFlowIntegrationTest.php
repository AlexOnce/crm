<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Services\FlightMicroservice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_trigger_passenger_flow_message_via_api(): void
    {
        config(['services.meta.flow_passenger_id' => '123456789098765']);

        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $conversation = Conversation::create([
            'phone' => '59178889900',
            'status' => 'human_live',
            'assigned_user_id' => $agent->id,
            'current_state' => 'HUMAN_SUPPORT',
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/send-flow");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Formulario interactivo de WhatsApp enviado al pasajero.',
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'agent_id' => $agent->id,
        ]);
    }

    public function test_meta_webhook_processes_official_nfm_reply_passenger_flow(): void
    {
        $conv = Conversation::create([
            'phone' => '59177112233',
            'status' => 'bot_active',
            'current_state' => 'R2_SOLICITAR_DATOS',
            'session_data' => [
                'origin' => 'VVI',
                'destination' => 'LPB',
                'flight_date' => '2026-09-30',
                'selected_flight' => [
                    'flight_number' => 'TM-702',
                    'departure_time' => '06:10',
                    'price' => 450.00,
                ],
            ],
        ]);

        $flowData = [
            'full_name' => 'Alejandro',
            'full_ape' => 'Vargas Mamani',
            'doc_type' => 'CI',
            'doc_number' => '8934521',
            'doc_telefonico' => '72223344',
            'email' => 'alejandro.vargas@gmail.com',
            'privacy_terms' => true,
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1393041265542970',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59157034222',
                                    'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Alejandro Vargas'],
                                        'wa_id' => '59177112233',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59177112233',
                                        'id' => 'wamid.HBgLNTkxNzcxMTIyMzM',
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'nfm_reply',
                                            'nfm_reply' => [
                                                'response_json' => json_encode($flowData),
                                                'body' => 'Sent',
                                                'name' => 'flow',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        // 1. Debe haber hecho handoff a la cola de agentes humanos
        $this->assertEquals('waiting_agent', $conv->status);
        $this->assertEquals('AGENT_HANDOFF', $conv->current_state);
        $this->assertEquals('Alejandro Vargas Mamani', $conv->contact_name);

        // 2. Debe haber guardado los datos del Flow en session_data
        $this->assertEquals('Alejandro Vargas Mamani', $conv->session_data['passenger_name']);
        $this->assertEquals('CI: 8934521', $conv->session_data['passport_number']);
        $this->assertEquals('72223344', $conv->session_data['contact_phone']);
        $this->assertEquals('alejandro.vargas@gmail.com', $conv->session_data['contact_email']);

        // 3. Debe haberse creado el Lead en BD con todos los datos
        $this->assertDatabaseHas('leads', [
            'conversation_id' => $conv->id,
            'passenger_name' => 'Alejandro Vargas Mamani',
            'passport_number' => 'CI: 8934521',
            'flight_number' => 'TM-702',
        ]);

        // 4. Debe haber registrado el mensaje en el historial del chat
        $latestMsg = $conv->messages()->where('sender', 'user')->latest()->first();
        $this->assertNotNull($latestMsg);
        $this->assertStringContainsString('Alejandro Vargas Mamani', $latestMsg->message);
        $this->assertStringContainsString('CI 8934521', $latestMsg->message);
        $this->assertStringContainsString('alejandro.vargas@gmail.com', $latestMsg->message);
    }

    public function test_meta_webhook_processes_flight_search_flow_and_returns_itineraries(): void
    {
        $mockFlightService = $this->createMock(FlightMicroservice::class);
        $mockFlightService->method('searchFlights')->willReturn([
            [
                'flight_number' => 'TM-702',
                'origin' => 'LPB',
                'origin_city' => 'La Paz',
                'destination' => 'VVI',
                'destination_city' => 'Santa Cruz',
                'departure_time' => '07:30',
                'arrival_time' => '08:35',
                'aircraft' => 'Avro RJ70 CP-3106',
                'price' => 450.00,
            ],
        ]);
        $mockFlightService->method('getCityName')->willReturnCallback(fn ($c) => $c === 'LPB' ? 'La Paz' : 'Santa Cruz');
        $this->app->instance(FlightMicroservice::class, $mockFlightService);

        $conv = Conversation::create([
            'phone' => '59170055443',
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
        ]);

        $searchData = [
            'origin' => 'LPB',
            'destination' => 'VVI',
            'flight_date' => '2026-09-28',
            'passengers' => '2',
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1393041265542970',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59157034222',
                                    'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Maria Fernandez'],
                                        'wa_id' => '59170055443',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59170055443',
                                        'id' => 'wamid.HBgLNTkxNzAwNTU0NDM',
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'nfm_reply',
                                            'nfm_reply' => [
                                                'response_json' => json_encode($searchData),
                                                'body' => 'Sent',
                                                'name' => 'flow',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        // 1. Debe haber cambiado de estado a VUELOS_MOSTRADOS
        $this->assertEquals('VUELOS_MOSTRADOS', $conv->current_state);

        // 2. Debe guardar origen, destino y fecha en sesión
        $this->assertEquals('LPB', $conv->session_data['origin']);
        $this->assertEquals('VVI', $conv->session_data['destination']);
        $this->assertEquals('2026-09-28', $conv->session_data['flight_date']);
        $this->assertEquals(2, $conv->session_data['passengers']);

        // 3. Debe haber vuelos disponibles consultados reales
        $this->assertNotEmpty($conv->session_data['available_flights']);

        // 4. El bot debe haber respondido con los itinerarios disponibles
        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNotNull($botMsg);
        $this->assertStringContainsString('ITINERARIOS DISPONIBLES', $botMsg->message);
        $this->assertStringContainsString('LPB', $botMsg->message);
        $this->assertStringContainsString('VVI', $botMsg->message);
        $this->assertStringContainsString('2 pasajeros', $botMsg->message);
    }

    public function test_meta_webhook_truthfully_informs_when_no_flights_are_available(): void
    {
        Http::fake([
            '*agair.transclicksolutions.com/sales/ext/flights*' => Http::response(['flights' => []], 200),
        ]);

        $conv = Conversation::create([
            'phone' => '59171122334',
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
        ]);

        $searchData = [
            'origin' => 'LPB',
            'destination' => 'VVI',
            'flight_date' => '2026-09-29', // Fecha sin vuelos en Airclick
            'passengers' => '1',
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1393041265542970',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59157034222',
                                    'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Carlos Perez'],
                                        'wa_id' => '59171122334',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59171122334',
                                        'id' => 'wamid.HBgLNTkxNzExMjIzMzQ',
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'nfm_reply',
                                            'nfm_reply' => [
                                                'response_json' => json_encode($searchData),
                                                'body' => 'Sent',
                                                'name' => 'flow',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        // No debe pasar a VUELOS_MOSTRADOS con datos falsos; pasa a FECHAS_CERCANAS o MENU_PRINCIPAL
        $this->assertContains($conv->current_state, ['FECHAS_CERCANAS', 'MENU_PRINCIPAL']);
        $this->assertEmpty($conv->session_data['available_flights'] ?? []);

        // El bot debe haber respondido diciendo claramente que no hay vuelos para esa fecha
        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNotNull($botMsg);
        $this->assertTrue(
            str_contains($botMsg->message, 'NO HAY VUELOS') || str_contains($botMsg->message, 'NO SE ENCONTRARON VUELOS')
        );
        $this->assertTrue(
            str_contains($botMsg->message, '29/09/2026') || str_contains($botMsg->message, '2026-09-29')
        );
    }

    public function test_carga_option_replies_only_with_designated_info_message_without_agent_transfer(): void
    {
        $conv = Conversation::create([
            'phone' => '59170099887',
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1486429229966534',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59157034222',
                                    'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Cliente Carga'], 'wa_id' => '59170099887'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59170099887',
                                        'id' => 'wamid.HBgLNTkxNzAwOTk4ODc='.time(),
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'button_reply',
                                            'button_reply' => [
                                                'id' => 'menu_carga',
                                                'title' => 'Carga',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        // 1. No debe transferirse a agente humano
        $this->assertEquals('bot_active', $conv->status);
        $this->assertNull($conv->assigned_user_id);

        // 2. Debe responder con la información designada de carga
        $botMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNotNull($botMsg);
        $this->assertTrue(str_contains($botMsg->message, 'TAMEP') && (str_contains($botMsg->message, 'Carga') || str_contains($botMsg->message, 'CARGA')));
        $this->assertStringNotContainsString('Cotizar con Asesor', $botMsg->message);
        $this->assertStringNotContainsString('hablar con un asesor', $botMsg->message);
    }

    public function test_webhook_ignores_messages_sent_to_secondary_phone_number(): void
    {
        config([
            'services.meta.phone_number_id' => '1239768275896736',
            'services.meta.filter_secondary_numbers' => true,
        ]);

        $conv = Conversation::create([
            'phone' => '59178811223',
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
        ]);

        // Payload donde phone_number_id pertenece a la línea secundaria (no activa en .env)
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1486429229966534',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59172223334',
                                    'phone_number_id' => '1392137520643564', // Número secundario
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Usuario Secundario'], 'wa_id' => '59178811223'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59178811223',
                                        'id' => 'wamid.HBgLNTkxNzg4MTEyMjM='.time(),
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Hola, quiero info'],
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

        // El webhook debe responder 200 a Meta pero NO debe haber generado ningún mensaje del bot
        $botMsg = $conv->messages()->where('sender', 'bot')->first();
        $this->assertNull($botMsg, 'El bot no debió responder a un mensaje dirigido a una línea secundaria.');
    }

    public function test_passenger_flow_records_passenger_count_in_lead_and_issues_ticket(): void
    {
        $conv = Conversation::create([
            'phone' => '59173344556',
            'status' => 'bot_active',
            'current_state' => 'R2_SOLICITAR_DATOS',
            'session_data' => [
                'origin' => 'VVI',
                'destination' => 'LPB',
                'flight_date' => '2026-10-05',
                'selected_flight' => [
                    'flight_number' => 'TM-301',
                    'departure_time' => '14:30',
                    'price' => 1200.00,
                ],
            ],
        ]);

        $flowData = [
            'full_name' => 'Beatriz',
            'full_ape' => 'Condori Lopez',
            'doc_type' => 'CI',
            'doc_number' => '6781234',
            'doc_telefonico' => '73344556',
            'email' => 'beatriz.condori@tamep.bo',
            'passengers_count' => '3',
            'privacy_terms' => true,
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1393041265542970',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59157034222',
                                    'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Beatriz Condori'],
                                        'wa_id' => '59173344556',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59173344556',
                                        'id' => 'wamid.HBgLNTkxNzMzNDQ1NTY',
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'nfm_reply',
                                            'nfm_reply' => [
                                                'response_json' => json_encode($flowData),
                                                'body' => 'Sent',
                                                'name' => 'flow',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();
        $this->assertEquals(3, $conv->session_data['passengers_count']);
        $this->assertEquals(3, $conv->session_data['passengers']);

        $lead = Lead::where('conversation_id', $conv->id)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Beatriz Condori Lopez', $lead->passenger_name);
        $this->assertEquals(3, $lead->passengers_count);
        $this->assertEquals(1200.00, (float) $lead->total_price);

        // Agente emite el boleto verificando passengers_count
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);
        $issueResponse = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/leads/{$lead->id}/issue-ticket", [
                'passenger_name' => 'Beatriz Condori Lopez',
                'passport_number' => 'CI: 6781234',
                'origin' => 'VVI',
                'destination' => 'LPB',
                'flight_date' => '2026-10-05',
                'flight_number' => 'TM-301',
                'passengers_count' => 3,
                'total_price' => 1200.00,
            ]);

        $issueResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $lead->refresh();
        $this->assertEquals('emitido', $lead->status);
        $this->assertEquals(3, $lead->passengers_count);
    }

    public function test_main_menu_presents_direct_flow_button_and_shortcut_options(): void
    {
        config(['services.meta.flow_search_id' => '1591684852737811']);

        // 1. Pasajero saluda
        $payloadGreeting = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['profile' => ['name' => 'Carla Mendoza'], 'wa_id' => '59178877665'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59178877665',
                                        'id' => 'wamid.greeting_'.uniqid(),
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Hola'],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payloadGreeting);
        $response->assertStatus(200);

        $conv = Conversation::where('phone', '59178877665')->first();
        $this->assertNotNull($conv);
        $this->assertEquals('bot_active', $conv->status);
        $this->assertEquals('MENU_PRINCIPAL', $conv->current_state);

        // El bot responde directamente con el Flow interactivo de Buscar Vuelos y atajos
        $latestBotMsg = $conv->messages()->where('sender', 'bot')->latest()->first();
        $this->assertNotNull($latestBotMsg);
        $this->assertStringContainsString('¡Hola! 👋 Te damos una cordial bienvenida a *TAMep* ✈️', $latestBotMsg->message);
        $this->assertStringContainsString('buscador interactivo', $latestBotMsg->message);
        $this->assertStringContainsString('CARGA', $latestBotMsg->message);
        $this->assertStringContainsString('OFICINAS', $latestBotMsg->message);
        $this->assertStringContainsString('[Formulario interactivo de WhatsApp Flow enviado]', $latestBotMsg->message);

        // 2. Pasajero escribe "2" para información de carga
        $payloadCarga = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['profile' => ['name' => 'Carla Mendoza'], 'wa_id' => '59178877665'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59178877665',
                                        'id' => 'wamid.carga_'.uniqid(),
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => '2'],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $responseCarga = $this->postJson('/api/webhooks/whatsapp', $payloadCarga);
        $responseCarga->assertStatus(200);

        $cargaBotMsg = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender', 'bot')
            ->latest('id')
            ->first();
        $this->assertNotNull($cargaBotMsg);
        $this->assertTrue(str_contains($cargaBotMsg->message, 'TAMEP') && (str_contains($cargaBotMsg->message, 'Carga') || str_contains($cargaBotMsg->message, 'CARGA')));

        // 3. Pasajero presiona volver al menú principal
        $payloadVolver = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['profile' => ['name' => 'Carla Mendoza'], 'wa_id' => '59178877665'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59178877665',
                                        'id' => 'wamid.btn_'.uniqid(),
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'button_reply',
                                            'button_reply' => [
                                                'id' => 'menu_volver',
                                                'title' => '🏠 Menú Principal',
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

        $responseVolver = $this->postJson('/api/webhooks/whatsapp', $payloadVolver);
        $responseVolver->assertStatus(200);

        $menuBotMsg = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender', 'bot')
            ->latest('id')
            ->first();
        $this->assertNotNull($menuBotMsg);
        $this->assertStringContainsString('¡Hola! 👋 Te damos una cordial bienvenida a *TAMep* ✈️', $menuBotMsg->message);
        $this->assertStringContainsString('[Formulario interactivo de WhatsApp Flow enviado]', $menuBotMsg->message);

        // 4. Pasajero escribe "3" para información de Oficinas y Horarios
        $payloadOficinas = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['profile' => ['name' => 'Carla Mendoza'], 'wa_id' => '59178877665'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59178877665',
                                        'id' => 'wamid.oficinas_'.uniqid(),
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => '3'],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $responseOficinas = $this->postJson('/api/webhooks/whatsapp', $payloadOficinas);
        $responseOficinas->assertStatus(200);

        $oficinasBotMsg = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender', 'bot')
            ->latest('id')
            ->first();
        $this->assertNotNull($oficinasBotMsg);
        $this->assertStringContainsString('TAMEP CONECTA BOLIVIA', $oficinasBotMsg->message);
        $this->assertStringContainsString('REGIONAL LA PAZ', $oficinasBotMsg->message);
        $this->assertStringContainsString('REGIONAL SANTA CRUZ', $oficinasBotMsg->message);
        $this->assertStringContainsString('73748234 - 71593829', $oficinasBotMsg->message);
        $this->assertStringContainsString('maps.app.goo.gl', $oficinasBotMsg->message);
    }

    public function test_meta_webhook_handles_large_group_numeric_passengers_without_cap(): void
    {
        $mockFlightService = $this->createMock(\App\Services\FlightMicroservice::class);
        $mockFlightService->method('searchFlights')->willReturn([ [
            'flight_number'    => 'TM-705',
            'origin'           => 'LPB',
            'origin_city'      => 'La Paz',
            'destination'      => 'VVI',
            'destination_city' => 'Santa Cruz',
            'departure_time'   => '08:30',
            'arrival_time'     => '09:35',
            'aircraft'         => 'Avro RJ70 CP-3106',
            'price'            => 4500.00,
        ] ]);
        $mockFlightService->method('getCityName')->willReturnCallback(fn($c) => $c === 'LPB' ? 'La Paz' : 'Santa Cruz');
        $this->app->instance(\App\Services\FlightMicroservice::class, $mockFlightService);

        $conv = Conversation::create([
            'phone'         => '59179988776',
            'status'        => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
        ]);

        $searchData = [
            'origin'      => 'LOB',
            'destination' => 'VVI',
            'flight_date' => '2026-10-15',
            'adults'      => '10',
            'children'    => '2',
            'babies'      => '0',
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1393041265542970',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                     'display_phone_number' => '59157034222',
                                     'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                     [
                                         'profile' => ['name' => 'Delegacion Militar'],
                                        'wa_id' => '59179988776',
                                     ],
                                ],
                                'messages' => [
                                     [
                                         'from' => '59179988776',
                                         'id' => 'wamid.group_pax_' . uniqid(),
                                         'timestamp' => (string) time(),
                                         'type' => 'interactive',
                                         'interactive' => [
                                             'type' => 'nfm_reply',
                                             'nfm_reply' => [
                                                 'response_json' => json_encode($searchData),
                                                 'body' => 'Sent',
                                                 'name' => 'flow',
                                             ],
                                         ],
                                     ],
                                ],
                            ],
                        ],
                        'field' => 'messages',
                    ],
                ],
            ],
        ];
        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        // 1. Debe haber guardado los 10 adultos y 2 ninos (12 pasajeros en total), sin limitar a 4
        $this->assertEquals(10, $conv->session_data['adults']);
        $this->assertEquals(2, $conv->session_data['children']);
        $this->assertEquals(12, $conv->session_data['passengers']);
        $this->assertEquals(12, $conv->session_data['passengers_count']);

        // 2. El bot debe haber respondido con los 12 pasajeros en el texto del itinerario
        $botMsg = $conv->messages()->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($botMsg);
        $this->assertStringContainsString('12 pasajeros', $botMsg->message);
        $this->assertStringContainsString('10 Adultos', $botMsg->message);
        $this->assertStringContainsString('2 Niños', $botMsg->message);
    }

    public function test_meta_webhook_handles_independent_terms_and_privacy_checkboxes()
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.HBgL']]], 200),
        ]);

        $conv = Conversation::create([
            'phone' => '59170099881',
            'contact_name' => 'Carlos Mendoza',
            'status' => 'bot_active',
            'current_state' => 'R2_SOLICITAR_DATOS',
            'session_data' => [
                'selected_flight' => [
                    'flight_number' => 'TAM-101',
                    'route' => 'LPB - TJA',
                    'price' => 450,
                ],
            ],
        ]);

        $flowData = [
            'full_name' => 'Carlos',
            'full_ape' => 'Mendoza Roca',
            'doc_type' => 'CI',
            'doc_number' => '4567890',
            'doc_telefonico' => '70099881',
            'email' => 'carlos.mendoza@gmail.com',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1393041265542970',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '59157034222',
                                    'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Carlos Mendoza'],
                                        'wa_id' => '59170099881',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59170099881',
                                        'id' => 'wamid.TEST_INDEPENDENT_TERMS_'.time(),
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'nfm_reply',
                                            'nfm_reply' => [
                                                'response_json' => json_encode($flowData),
                                                'body' => 'Sent',
                                                'name' => 'flow',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        $this->assertEquals('waiting_agent', $conv->status);
        $this->assertTrue($conv->session_data['passenger_flow']['terms_accepted']);
        $this->assertTrue($conv->session_data['passenger_flow']['privacy_accepted']);

        // Verificar el mensaje registrado en el CRM
        $userMsg = $conv->messages()->where('sender', 'user')->latest('id')->first();
        $this->assertNotNull($userMsg);
        $this->assertStringContainsString('🔒 *Términos y Privacidad:* Aceptados ✅', $userMsg->message);
    }

    public function test_chatbot_interactively_collects_companions_for_multiple_passengers(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.HBgL']]], 200),
        ]);

        $conv = Conversation::create([
            'phone' => '59178899001',
            'contact_name' => 'Roberto Vaca',
            'status' => 'bot_active',
            'current_state' => 'R2_SOLICITAR_DATOS',
            'session_data' => [
                'origin' => 'VVI',
                'destination' => 'LPB',
                'flight_date' => '2026-10-10',
                'passengers' => 3,
                'passengers_count' => 3,
                'selected_flight' => [
                    'flight_number' => 'TM-101',
                    'departure_time' => '07:30',
                    'price' => 1350.00,
                ],
            ],
        ]);

        // 1. El titular envía el Flow con 3 pasajeros
        $flowData = [
            'full_name' => 'Roberto',
            'full_ape' => 'Vaca Justiniano',
            'doc_type' => 'CI',
            'doc_number' => '5432109',
            'doc_telefonico' => '78899001',
            'email' => 'roberto.vaca@gmail.com',
            'passengers_count' => '3',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $payloadFlow = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1393041265542970',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '59157034222',
                            'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                        ],
                        'contacts' => [['profile' => ['name' => 'Roberto Vaca'], 'wa_id' => '59178899001']],
                        'messages' => [[
                            'from' => '59178899001',
                            'id' => 'wamid.COMP_FLOW_1',
                            'timestamp' => (string) time(),
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'nfm_reply',
                                'nfm_reply' => [
                                    'response_json' => json_encode($flowData),
                                    'body' => 'Sent',
                                    'name' => 'flow',
                                ],
                            ],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $res1 = $this->postJson('/api/webhooks/whatsapp', $payloadFlow);
        $res1->assertStatus(200);

        $conv->refresh();
        // El bot debe continuar activo solicitando los datos del primer acompañante
        $this->assertEquals('bot_active', $conv->status);
        $this->assertEquals('COLLECTING_COMPANIONS', $conv->current_state);

        $botMsg1 = $conv->messages()->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($botMsg1);
        $this->assertStringContainsString('Acompañante 1 de 2', $botMsg1->message);

        // 2. El cliente escribe el acompañante 1
        $payloadComp1 = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1393041265542970',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '59157034222',
                            'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                        ],
                        'contacts' => [['profile' => ['name' => 'Roberto Vaca'], 'wa_id' => '59178899001']],
                        'messages' => [[
                            'from' => '59178899001',
                            'id' => 'wamid.COMP_TEXT_1',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'Valeria Vaca Mercado - 8765432 SC'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $res2 = $this->postJson('/api/webhooks/whatsapp', $payloadComp1);
        $res2->assertStatus(200);

        $conv->refresh();
        $this->assertEquals('bot_active', $conv->status);
        $this->assertEquals('COLLECTING_COMPANIONS', $conv->current_state);
        $this->assertCount(1, $conv->session_data['companions']);
        $this->assertEquals('Valeria Vaca Mercado', $conv->session_data['companions'][0]['name']);
        $this->assertEquals('8765432 SC', $conv->session_data['companions'][0]['doc']);

        $botMsg2 = $conv->messages()->where('sender', 'bot')->latest('id')->first();
        $this->assertStringContainsString('Acompañante 2 de 2', $botMsg2->message);

        // 3. El cliente escribe el acompañante 2 (último acompañante)
        $payloadComp2 = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1393041265542970',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '59157034222',
                            'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                        ],
                        'contacts' => [['profile' => ['name' => 'Roberto Vaca'], 'wa_id' => '59178899001']],
                        'messages' => [[
                            'from' => '59178899001',
                            'id' => 'wamid.COMP_TEXT_2',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'Joaquin Vaca Mercado - 9876543 SC'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $res3 = $this->postJson('/api/webhooks/whatsapp', $payloadComp2);
        $res3->assertStatus(200);

        $conv->refresh();
        // Nómina completa -> pasa al asesor
        $this->assertEquals('waiting_agent', $conv->status);
        $this->assertEquals('AGENT_HANDOFF', $conv->current_state);
        $this->assertCount(2, $conv->session_data['companions']);

        // Verificar el Lead actualizado con la nómina en details
        $lead = Lead::where('conversation_id', $conv->id)->first();
        $this->assertNotNull($lead);
        $this->assertEquals(3, $lead->passengers_count);
        $this->assertStringContainsString('Valeria Vaca Mercado', $lead->details);
        $this->assertStringContainsString('Joaquin Vaca Mercado', $lead->details);

        // Verificar mensaje final con resumen consolidado
        $botMsg3 = $conv->messages()->where('sender', 'bot')->latest('id')->first();
        $this->assertStringContainsString('NÓMINA DE PASAJEROS REGISTRADA CON ÉXITO', $botMsg3->message);
        $this->assertStringContainsString('1️⃣ *Roberto Vaca Justiniano*', $botMsg3->message);
        $this->assertStringContainsString('2️⃣ *Valeria Vaca Mercado*', $botMsg3->message);
        $this->assertStringContainsString('3️⃣ *Joaquin Vaca Mercado*', $botMsg3->message);
    }

    public function test_chatbot_directly_registers_companions_included_in_multiscreen_flow(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.HBgL']]], 200),
        ]);

        $conv = Conversation::create([
            'phone' => '59176543210',
            'contact_name' => 'Gonzalo Morales',
            'status' => 'bot_active',
            'current_state' => 'R2_SOLICITAR_DATOS',
            'session_data' => [
                'origin' => 'LPB',
                'destination' => 'VVI',
                'flight_date' => '2026-10-15',
                'passengers' => 2,
                'passengers_count' => 2,
                'selected_flight' => [
                    'flight_number' => 'TM-202',
                    'departure_time' => '09:00',
                    'price' => 900.00,
                ],
            ],
        ]);

        // El usuario envía el formulario con el titular y el acompañante 1 llenados directamente en el Flow
        $flowData = [
            'full_name' => 'Gonzalo',
            'full_ape' => 'Morales Teran',
            'doc_type' => 'CI',
            'doc_number' => '3344556',
            'doc_telefonico' => '76543210',
            'email' => 'gonzalo.morales@tamep.bo',
            'comp1_name' => 'Carla Prado Suarez',
            'comp1_doc' => '7788990 SC',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1393041265542970',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '59157034222',
                            'phone_number_id' => (string) config('services.meta.phone_number_id', '1239768275896736'),
                        ],
                        'contacts' => [['profile' => ['name' => 'Gonzalo Morales'], 'wa_id' => '59176543210']],
                        'messages' => [[
                            'from' => '59176543210',
                            'id' => 'wamid.DIRECT_COMP_1',
                            'timestamp' => (string) time(),
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'nfm_reply',
                                'nfm_reply' => [
                                    'response_json' => json_encode($flowData),
                                    'body' => 'Sent',
                                    'name' => 'flow',
                                ],
                            ],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $conv->refresh();

        // Al haber enviado todos los acompañantes en el Flow, pasa directamente al asesor sin necesidad de chatear
        $this->assertEquals('waiting_agent', $conv->status);
        $this->assertEquals('AGENT_HANDOFF', $conv->current_state);
        $this->assertCount(1, $conv->session_data['companions']);
        $this->assertEquals('Carla Prado Suarez', $conv->session_data['companions'][0]['name']);
        $this->assertEquals('7788990 SC', $conv->session_data['companions'][0]['doc']);

        // Verificar el Lead en base de datos
        $lead = Lead::where('conversation_id', $conv->id)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Gonzalo Morales Teran', $lead->passenger_name);
        $this->assertEquals(2, $lead->passengers_count);
        $this->assertStringContainsString('Carla Prado Suarez', $lead->details);
        $this->assertStringContainsString('7788990 SC', $lead->details);

        // Mensaje registrado en CRM con acompañantes
        $userMsg = $conv->messages()->where('sender', 'user')->latest('id')->first();
        $this->assertNotNull($userMsg);
        $this->assertStringContainsString('Carla Prado Suarez', $userMsg->message);

        // Mensaje del bot confirmando la nómina completa registrada
        $botMsg = $conv->messages()->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($botMsg);
        $this->assertStringContainsString('NÓMINA DE PASAJEROS REGISTRADA CON ÉXITO', $botMsg->message);
        $this->assertStringContainsString('1️⃣ *Gonzalo Morales Teran*', $botMsg->message);
        $this->assertStringContainsString('2️⃣ *Carla Prado Suarez*', $botMsg->message);
    }
}
