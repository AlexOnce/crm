<?php

namespace Tests\Feature;

use App\Events\SystemVerificationCodeReceived;
use App\Models\Conversation;
use App\Services\WhatsAppWebhookService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Evitar llamadas externas a WhatsApp Cloud API en las pruebas
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.fake']]], 200),
        ]);
    }

    /**
     * Test 1: Verificar que el servicio detecta patrones de código OTP (5-8 dígitos)
     * acompañados de términos clave de Meta/Facebook.
     */
    public function test_service_correctly_detects_otp_verification_codes(): void
    {
        $service = app(WhatsAppWebhookService::class);

        $validMessages = [
            'Tu código de confirmación de Facebook es 492019' => '492019',
            'Your Facebook confirmation code is 859302. Do not share it.' => '859302',
            'Usa el código 719402 para verificar tu cuenta de Meta Business' => '719402',
            'Code: 1234567. Este es tu código de verificación de WhatsApp/Facebook.' => '1234567',
            'Código de seguridad: 98124' => '98124',
        ];

        foreach ($validMessages as $text => $expectedCode) {
            $extractedCode = null;
            $isOtp = $service->isVerificationCode($text, $extractedCode);
            $this->assertTrue($isOtp, "Falló en detectar OTP en: '{$text}'");
            $this->assertEquals($expectedCode, $extractedCode, "El código extraído no coincide para: '{$text}'");
        }

        $normalMessages = [
            'Hola, buenas tardes, quisiera información de vuelos para 2 personas',
            'Mi número de carnet es 8493021 LP',
            'Mi teléfono es 78999888 y mi código postal 0000',
            '¿Cuánto cuesta el pasaje a La Paz?',
        ];

        foreach ($normalMessages as $text) {
            $extractedCode = null;
            $isOtp = $service->isVerificationCode($text, $extractedCode);
            $this->assertFalse($isOtp, "Detectó falsamente como OTP el texto: '{$text}'");
        }
    }

    /**
     * Test 2: Intercepción de OTP entrante en el Webhook de Meta.
     * Debe almacenarse en system_verification_codes, emitir el evento Broadcasting
     * y NO crear conversación de bot de pasajeros.
     */
    public function test_webhook_intercepts_otp_code_stores_in_db_and_broadcasts_event(): void
    {
        Event::fake([
            SystemVerificationCodeReceived::class,
        ]);

        $otpPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550234567',
                                    'phone_number_id' => '123456789',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Meta Verification'],
                                        'wa_id' => '16505550100',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '16505550100',
                                        'id' => 'wamid.HBgLMTY1MDU1NTAxMDBVAgASGBQzQUJDREVGMDEyMzQ1Njc4OTA=',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Tu código de confirmación de Facebook es 739201. No compartas este código.',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $otpPayload);

        $response->assertStatus(200);

        // 1. Debe existir en la tabla system_verification_codes
        $this->assertDatabaseHas('system_verification_codes', [
            'code' => '739201',
            'sender_phone' => '16505550100',
            'service' => 'facebook_page_linking',
            'status' => 'pending',
        ]);

        // 2. Debe haberse emitido el evento de Broadcasting
        Event::assertDispatched(SystemVerificationCodeReceived::class, function ($event) {
            return $event->code === '739201' && $event->senderPhone === '16505550100';
        });

        // 3. No debe haberse contaminado la tabla de conversaciones creando un chat comercial
        $this->assertDatabaseMissing('conversations', [
            'phone' => '16505550100',
        ]);
    }

    /**
     * Test 3: Atribución de Referral (Facebook Ads / Click-to-WhatsApp)
     * y cálculo de la ventana gratuita de 72 horas.
     */
    public function test_webhook_extracts_referral_and_calculates_72h_free_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

        $adPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550234567',
                                    'phone_number_id' => '123456789',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Roberto Sánchez'],
                                        'wa_id' => '59171234567',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '59171234567',
                                        'id' => 'wamid.HBgLNTkxNzEyMzQ1NjdVAgASGBQxMjM0NTY3ODkwQUJDREVGMDEyMw==',
                                        'timestamp' => (string) Carbon::now()->timestamp,
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Hola, vi su oferta de pasajes a Santa Cruz y quiero cotizar.',
                                        ],
                                        'referral' => [
                                            'source_type' => 'ad',
                                            'source_id' => '23851948201948',
                                            'source_url' => 'https://fb.me/boletos-promocionales',
                                            'headline' => 'Oferta Especial Vuelos Santa Cruz - La Paz',
                                            'body' => 'Reserva hoy con 30% de descuento en boletos aéreos nacionales.',
                                            'media_type' => 'image',
                                            'image_url' => 'https://scontent.xx.fbcdn.net/v/sample.jpg',
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

        $response = $this->postJson('/api/webhooks/whatsapp', $adPayload);

        $response->assertStatus(200);

        // Verificar que la conversación se creó y guardó todos los datos de atribución
        $conversation = Conversation::where('phone', '59171234567')->first();
        $this->assertNotNull($conversation, 'La conversación debió crearse para el cliente');

        $this->assertEquals('ad', $conversation->source_type);
        $this->assertEquals('23851948201948', $conversation->source_id);
        $this->assertEquals('https://fb.me/boletos-promocionales', $conversation->source_url);
        $this->assertEquals('Oferta Especial Vuelos Santa Cruz - La Paz', $conversation->headline);
        $this->assertEquals('Reserva hoy con 30% de descuento en boletos aéreos nacionales.', $conversation->body);

        // Verificar que free_window_expires_at es exactamente 72 horas después de now
        $expectedExpiry = Carbon::now()->addHours(72);
        $this->assertNotNull($conversation->free_window_expires_at);
        $this->assertEquals($expectedExpiry->toDateTimeString(), $conversation->free_window_expires_at->toDateTimeString());

        // Verificar métodos auxiliares del modelo Eloquent
        $this->assertTrue($conversation->isFreeWindowActive());
        $this->assertEquals(72, $conversation->remaining_free_window_hours);

        // Simular que pasaron 73 horas y verificar que expire correctamente
        Carbon::setTestNow(Carbon::parse('2026-10-10 13:00:00'));
        $conversation->refresh();
        $this->assertFalse($conversation->isFreeWindowActive());
        $this->assertEquals(0, $conversation->remaining_free_window_hours);

        Carbon::setTestNow(); // Reset tiempo simulado
    }
}
