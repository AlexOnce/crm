<?php

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Services\BroadcastHelper;
use App\Services\ChatbotEngineService;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppWebhookService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meta:status', function (MetaWhatsAppService $service) {
    $this->info('=== VERIFICACIÓN DE CONEXIÓN CON META CLOUD API ===');

    $phoneDetails = $service->getPhoneNumberDetails();
    if (isset($phoneDetails['error'])) {
        $this->error('Error al consultar Phone Number ID: '.json_encode($phoneDetails));
    } else {
        $this->info('✅ Phone Number ID Conectado Exitosamente:');
        $this->line(' - ID: '.($phoneDetails['id'] ?? 'N/A'));
        $this->line(' - Nombre Verificado: '.($phoneDetails['verified_name'] ?? 'N/A'));
        $this->line(' - Número Mostrado: '.($phoneDetails['display_phone_number'] ?? 'N/A'));
        $this->line(' - Calidad: '.($phoneDetails['quality_rating'] ?? 'N/A'));
        $this->line(' - Plataforma: '.($phoneDetails['platform_type'] ?? 'N/A'));
        if (isset($phoneDetails['webhook_configuration'])) {
            $this->line(' - Webhook Meta Configurado: '.json_encode($phoneDetails['webhook_configuration']));
        }
    }

    $this->newLine();
    $wabaDetails = $service->getWabaDetails();
    if (isset($wabaDetails['error'])) {
        $this->warn('Aviso WABA: '.json_encode($wabaDetails));
    } else {
        $this->info('✅ WhatsApp Business Account (WABA):');
        $this->line(' - ID: '.($wabaDetails['id'] ?? 'N/A'));
        $this->line(' - Nombre: '.($wabaDetails['name'] ?? 'N/A'));
        $this->line(' - Zona Horaria: '.($wabaDetails['timezone_id'] ?? 'N/A'));
    }
})->purpose('Verificar estado y conectividad con Meta Cloud API');

Artisan::command('meta:send {phone} {message=Hola, prueba de conexión desde CRM TAMEP}', function (MetaWhatsAppService $service, $phone, $message) {
    $this->info("Enviando mensaje de texto a: {$phone}...");
    $success = $service->sendMessage($phone, $message);
    if ($success) {
        $this->info('✅ Mensaje enviado exitosamente.');
    } else {
        $this->error('❌ Error al enviar mensaje. Revisa storage/logs/laravel.log.');
    }
})->purpose('Enviar mensaje de texto de prueba vía Meta Cloud API');

Artisan::command('meta:template {phone} {template=hello_world}', function (MetaWhatsAppService $service, $phone, $template) {
    $this->info("Enviando plantilla '{$template}' a: {$phone}...");
    $res = $service->sendTemplate($phone, $template);
    if ($res['success']) {
        $this->info('✅ Plantilla enviada con éxito: '.json_encode($res['body']));
    } else {
        $this->error('❌ Error al enviar plantilla: '.json_encode($res));
    }
})->purpose('Enviar plantilla oficial de prueba vía Meta Cloud API');

Artisan::command('meta:flow:list', function (MetaWhatsAppService $service) {
    $this->info('Consultando WhatsApp Flows registrados en Meta Cloud API...');
    $res = $service->listFlows();

    if (isset($res['error'])) {
        $this->error('❌ Error al consultar Flows: '.json_encode($res));

        return 1;
    }

    $flows = $res['data'] ?? [];
    if (empty($flows)) {
        $this->warn('No hay Flows registrados en esta cuenta de WhatsApp Business (WABA).');

        return 0;
    }

    $headers = ['ID', 'Nombre', 'Estado', 'Categorías', 'Versión'];
    $data = collect($flows)->map(function ($f) {
        $statusIcon = match ($f['status'] ?? '') {
            'PUBLISHED' => '🟢 Publicado',
            'DRAFT' => '🟡 Borrador',
            'DEPRECATED' => '🔴 Deprecado',
            default => $f['status'] ?? 'N/A'
        };

        return [
            $f['id'] ?? 'N/A',
            $f['name'] ?? 'N/A',
            $statusIcon,
            implode(', ', $f['categories'] ?? []),
            $f['json_version'] ?? '7.3',
        ];
    });

    $this->table($headers, $data);
    $activeFlowId = config('services.meta.flow_passenger_id');
    $this->newLine();
    $this->line('Flow configurado en CRM (.env META_FLOW_PASSENGER_ID): '.(! empty($activeFlowId) ? "<fg=green>{$activeFlowId}</>" : '<fg=yellow>No configurado</>'));

    return 0;
})->purpose('Listar todos los WhatsApp Flows asociados a la cuenta de WhatsApp');

Artisan::command('meta:flow:send {phone} {--flow_id=}', function (MetaWhatsAppService $service, $phone) {
    $flowId = $this->option('flow_id') ?: config('services.meta.flow_passenger_id');

    if (empty($flowId)) {
        $this->error('❌ No se especificó --flow_id y META_FLOW_PASSENGER_ID no está configurado en .env.');
        $this->line('Uso: php artisan meta:flow:send +591XXXXXXXX --flow_id=TU_FLOW_ID');

        return 1;
    }

    $this->info("Enviando WhatsApp Flow ({$flowId}) a: {$phone}...");

    $success = $service->sendFlowMessage(
        phone: $phone,
        bodyText: "✈️ *Formulario de Emisión de Pasaje TAMep*\n\nPor favor presiona el botón a continuación para ingresar los datos del pasajero en nuestro formulario oficial y seguro.",
        flowId: $flowId,
        flowCta: '📝 Llenar Datos',
        flowToken: 'test_'.time(),
        headerText: '✈️ TAMep Línea Aérea',
        footerText: 'Formulario Oficial Seguro',
        mode: 'published',
        screenId: 'PASSENGER_INFO'
    );

    if ($success) {
        $this->info("✅ Mensaje interactivo Flow enviado con éxito a {$phone}.");

        return 0;
    } else {
        $this->error('❌ Error al enviar el Flow. Revisa los logs en storage/logs/laravel.log.');

        return 1;
    }
})->purpose('Enviar mensaje interactivo de WhatsApp Flow a un teléfono de prueba');

Artisan::command('meta:flow:create {name} {--category=CUSTOMER_SUPPORT}', function (MetaWhatsAppService $service, $name) {
    $category = $this->option('category') ?: 'CUSTOMER_SUPPORT';
    $this->info("Creando WhatsApp Flow '{$name}' en Meta...");
    $res = $service->createFlow($name, [$category]);

    if (isset($res['id'])) {
        $this->info("✅ Flow creado exitosamente con ID: {$res['id']}");
        $this->line("Para subir el JSON: php artisan meta:flow:upload {$res['id']}");

        return 0;
    } else {
        $this->error('❌ Error al crear Flow: '.json_encode($res));

        return 1;
    }
})->purpose('Crear un nuevo WhatsApp Flow en Meta Cloud API');

Artisan::command('meta:flow:upload {flow_id} {--file=}', function (MetaWhatsAppService $service, $flow_id) {
    $filePath = $this->option('file') ?: resource_path('flows/tamep_passenger_flow.json');

    if (! file_exists($filePath)) {
        $this->error("❌ No se encontró el archivo JSON en: {$filePath}");

        return 1;
    }

    $jsonContent = file_get_contents($filePath);
    $this->info("Subiendo JSON de Flow a Meta (Flow ID: {$flow_id})...");
    $res = $service->uploadFlowJson($flow_id, $jsonContent);

    if (isset($res['success']) && $res['success']) {
        $this->info('✅ JSON de Flow subido y validado exitosamente en Meta.');

        return 0;
    } else {
        $this->error('❌ Error al subir JSON de Flow: '.json_encode($res));

        return 1;
    }
})->purpose('Subir o actualizar el JSON de un WhatsApp Flow existente en Meta');

Artisan::command('meta:flow:publish {flow_id}', function (MetaWhatsAppService $service, $flow_id) {
    $this->info("Publicando Flow ID: {$flow_id} en Meta...");
    $res = $service->publishFlow($flow_id);

    if (isset($res['success']) && $res['success']) {
        $this->info('✅ Flow publicado exitosamente en Meta Cloud API.');

        return 0;
    } else {
        $this->error('❌ Error al publicar Flow: '.json_encode($res));

        return 1;
    }
})->purpose('Publicar un WhatsApp Flow en Meta Cloud API');

/*
|--------------------------------------------------------------------------
| Comandos de Gestión de Agentes del CRM
|--------------------------------------------------------------------------
*/
Artisan::command('agent:create {name} {email} {password} {--role=agent}', function ($name, $email, $password) {
    $email = strtolower(trim($email));
    $role = $this->option('role') ?: 'agent';

    if (User::where('email', $email)->exists()) {
        $this->error("❌ Ya existe un usuario registrado con el correo: {$email}");

        return 1;
    }

    $user = User::create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
        'role' => $role,
        'is_active' => true,
    ]);

    $this->info('✅ Agente creado exitosamente:');
    $this->line(" - ID: {$user->id}");
    $this->line(" - Nombre: {$user->name}");
    $this->line(" - Email: {$user->email}");
    $this->line(" - Rol: {$user->role}");
    $this->line(' - Estado: Activo');

    return 0;
})->purpose('Crear una cuenta de agente para el panel CRM');

Artisan::command('agent:list', function () {
    $agents = User::withCount('assignedConversations')->get();

    if ($agents->isEmpty()) {
        $this->warn('No hay agentes registrados en la base de datos.');

        return 0;
    }

    $headers = ['ID', 'Nombre', 'Email', 'Rol', 'Estado', 'Chats Atendidos', 'Fecha Registro'];
    $data = $agents->map(function ($agent) {
        return [
            $agent->id,
            $agent->name,
            $agent->email,
            $agent->role,
            $agent->is_active ? '🟢 Activo' : '🔴 Inactivo',
            $agent->assigned_conversations_count,
            $agent->created_at?->format('Y-m-d H:i') ?? 'N/A',
        ];
    });

    $this->table($headers, $data);

    return 0;
})->purpose('Listar todos los agentes de atención y ventas');

Artisan::command('agent:password {email} {password}', function ($email, $password) {
    $email = strtolower(trim($email));
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("❌ No se encontró ningún usuario con el correo: {$email}");

        return 1;
    }

    $user->password = Hash::make($password);
    $user->save();

    // Revocar tokens previos por seguridad
    $user->tokens()->delete();

    $this->info("✅ Contraseña actualizada correctamente para el agente: {$user->name} ({$user->email}).");
    $this->line('Se revocaron las sesiones anteriores por seguridad.');

    return 0;
})->purpose('Actualizar la contraseña de un agente');

Artisan::command('agent:toggle {email}', function ($email) {
    $email = strtolower(trim($email));
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("❌ No se encontró ningún usuario con el correo: {$email}");

        return 1;
    }

    $user->is_active = ! $user->is_active;
    $user->save();

    if (! $user->is_active) {
        $user->tokens()->delete();
    }

    $status = $user->is_active ? '🟢 Activada' : '🔴 Suspendida';
    $this->info("Estado de la cuenta {$user->email}: {$status}");

    return 0;
})->purpose('Activar o suspender la cuenta de un agente');

Artisan::command('agent:delete {email} {--force}', function ($email) {
    $email = strtolower(trim($email));
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("❌ No se encontró ningún usuario con el correo: {$email}");

        return 1;
    }

    if ($user->role === 'admin' && User::where('role', 'admin')->where('id', '!=', $user->id)->count() === 0) {
        $this->error('❌ No es posible eliminar al único administrador del sistema.');

        return 1;
    }

    if (! $this->option('force') && ! $this->confirm("¿Estás seguro de eliminar permanentemente al agente '{$user->name}' ({$user->email})?")) {
        $this->info('Operación cancelada.');

        return 0;
    }

    $user->assignedConversations()->where('status', 'human_live')->update([
        'status' => 'waiting_agent',
        'assigned_user_id' => null,
    ]);
    $user->assignedConversations()->update(['assigned_user_id' => null]);
    $user->tokens()->delete();
    $name = $user->name;
    $user->delete();

    $this->info("✅ Agente {$name} ({$email}) eliminado exitosamente.");

    return 0;
})->purpose('Eliminar permanentemente la cuenta de un agente');

/*
|--------------------------------------------------------------------------
| Reactivación Automática del Bot por Inactividad
|--------------------------------------------------------------------------
*/
Artisan::command('crm:auto-release-inactive {--minutes=30} {--notify}', function (MetaWhatsAppService $whatsapp) {
    $minutes = (int) ($this->option('minutes') ?: config('crm.bot_inactivity_minutes', 30));
    $shouldNotify = (bool) $this->option('notify');
    $threshold = now()->subMinutes($minutes);

    $conversations = Conversation::whereIn('status', ['human_live', 'waiting_agent'])
        ->where(function ($q) use ($threshold) {
            $q->where('last_interaction_at', '<=', $threshold)
                ->orWhere(function ($sub) use ($threshold) {
                    $sub->whereNull('last_interaction_at')
                        ->where('updated_at', '<=', $threshold);
                });
        })
        ->get();

    if ($conversations->isEmpty()) {
        $this->info("No hay conversaciones inactivas que superen los {$minutes} minutos.");

        return 0;
    }

    $count = 0;
    foreach ($conversations as $conversation) {
        $conversation->update([
            'status' => 'bot_active',
            'current_state' => 'MENU_PRINCIPAL',
            'session_data' => [],
            'last_interaction_at' => now(),
        ]);

        BroadcastHelper::safeBroadcast(new ConversationUpdated($conversation, 'auto_released_inactivity'));

        if ($shouldNotify) {
            $farewell = "✅ *Atención Finalizada por Inactividad*\n\n".
                        "Tu consulta previa ha sido cerrada tras un período de inactividad de {$minutes} minutos. Si requieres nueva información o deseas realizar una cotización, escribe *MENU* en cualquier momento.";

            $farewellMsg = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender' => 'bot',
                'message' => $farewell,
            ]);

            BroadcastHelper::safeBroadcast(new ChatMessageSent($farewellMsg, $conversation->id, $conversation));
            $whatsapp->sendMessage($conversation->phone, $farewell);
        }

        $count++;
    }

    $this->info("✅ Se reactivó el bot automáticamente en {$count} conversaciones inactivas (umbral: {$minutes} min).");

    return 0;
})->purpose('Reactivar el bot automáticamente en conversaciones humanas inactivas por más de 30 minutos');

Schedule::command('crm:auto-release-inactive')->everyFiveMinutes();

/*
|--------------------------------------------------------------------------
| Comando de Simulación: Flow con 5 Pasajeros y 3 Llenados
|--------------------------------------------------------------------------
*/
Artisan::command('crm:simulate-partial-pax {--phone=59178541236}', function (ChatbotEngineService $engine) {
    $phone = (string) $this->option('phone');

    $this->info('=== SIMULANDO REGISTRO DE 5 PASAJEROS CON 3 LLENADOS ===');
    $this->line("Teléfono de simulación: {$phone}");

    // 1. Crear o reiniciar conversación de prueba con sesión previa de 5 PAX
    $conv = Conversation::updateOrCreate(
        ['phone' => $phone],
        [
            'contact_name' => 'Carlos Montaño',
            'status' => 'bot_active',
            'current_state' => 'R2_SOLICITAR_DATOS',
            'last_interaction_at' => now(),
            'session_data' => [
                'origin' => 'LPB',
                'destination' => 'VVI',
                'flight_date' => '2026-10-15',
                'passengers' => 5,
                'passengers_count' => 5,
                'adults' => 5,
                'children' => 0,
                'babies' => 0,
                'selected_flight' => [
                    'flight_number' => 'TM-705',
                    'departure_time' => '08:30',
                    'arrival_time' => '09:35',
                    'aircraft' => 'Avro RJ70',
                    'price' => 4215.00,
                ],
            ],
        ]
    );

    // 2. Datos que Meta devolvería en el Flow (5 solicitados, pero solo 3 personas llenadas)
    $flowData = [
        'full_name' => 'Carlos Eduardo',
        'full_ape' => 'Montaño Salvatierra',
        'doc_type' => 'CI',
        'doc_number' => '7821940 SC',
        'gender' => 'Masculino',
        'birth_date' => '1988-04-15',
        'doc_telefonico' => '+59178541236',
        'email' => 'carlos.montano@gmail.com',
        'passengers_count' => 5,
        'comp1_name' => 'Mariana',
        'comp1_ape' => 'Roca Banzer',
        'comp1_doc' => '8912345 SC',
        'comp1_gender' => 'Femenino',
        'comp1_birth' => '1992-08-22',
        'comp2_name' => 'Lucas',
        'comp2_ape' => 'Montaño Roca',
        'comp2_doc' => '12984561 SC',
        'comp2_gender' => 'Masculino',
        'comp2_birth' => '2018-11-10',
        'observation' => 'Requiere asiento junto a la ventana para el menor',
        'invoice_razon_social' => 'Montaño Servicios SRL',
        'invoice_doc_type' => 'NIT',
        'invoice_nit' => '3490182019',
        'terms_accepted' => true,
        'privacy_accepted' => true,
    ];

    // 3. Texto del formulario tal como lo genera MetaWebhookController
    $cardText = "📋 *DATOS DE PASAJERO REGISTRADOS (FORMULARIO)*\n"
              ."👤 *Pasajero:* Carlos Eduardo Montaño Salvatierra\n"
              ."📄 *Documento:* CI 7821940 SC\n"
              ."👥 *Pasajeros:* 5\n"
              ."📱 *Teléfono:* +59178541236\n"
              ."✉️ *Email:* carlos.montano@gmail.com\n"
              ."🎂 *F. Nacimiento:* 1988-04-15\n"
              ."🧾 *Factura:* Razón Social: Montaño Servicios SRL | Tipo: NIT | NIT/Doc: 3490182019\n"
              ."🧳 *Equipaje/Req:* Requiere asiento junto a la ventana para el menor\n\n"
              ."👥 *Acompañantes en Formulario:*\n"
              ."2. Mariana Roca Banzer (8912345 SC)\n"
              ."3. Lucas Montaño Roca (12984561 SC)\n\n"
              .'🔒 *Términos y Privacidad:* Aceptados ✅';

    // 4. Procesar a través del motor del Chatbot
    $engine->processIncomingMessage(
        conversation: $conv,
        text: $cardText,
        mediaUrl: null,
        mediaType: null,
        flowData: $flowData
    );

    $conv->refresh();
    $lead = Lead::where('conversation_id', $conv->id)->latest('id')->first();
    $lastMsg = ChatMessage::where('conversation_id', $conv->id)->latest('id')->first();

    $this->info('✅ Simulación completada con éxito:');
    $this->line(" - Conversación ID: {$conv->id}");
    $this->line(" - Contacto: {$conv->contact_name} ({$conv->phone})");
    $this->line(" - Estado: {$conv->status} ({$conv->current_state})");
    $this->line(' - Lead creado ID: '.($lead?->id ?? 'N/A'));
    $this->line(' - Lead detalles: '.($lead?->details ?? 'N/A'));
    $this->newLine();
    $this->info('📨 Mensaje enviado por el bot:');
    $this->line($lastMsg?->message ?? 'N/A');

    return 0;
})->purpose('Simular envío de Flow con 5 pasajeros y solo 3 datos llenados');

Artisan::command('meta:simulate-otp {code=582914} {sender=16505550100}', function (WhatsAppWebhookService $service, $code = '582914', $sender = '16505550100') {
    $this->info('=== SIMULANDO RECEPCIÓN DE CÓDIGO OTP DE META / FACEBOOK ===');
    $simulatedText = "Tu código de confirmación de Facebook es {$code}. No lo compartas con nadie.";

    $record = $service->detectAndCaptureVerificationCode(
        body: $simulatedText,
        senderPhone: $sender,
        metadata: ['simulated' => true, 'timestamp' => time()]
    );

    if ($record) {
        $this->info('✅ Código OTP detectado, guardado y emitido vía WebSockets:');
        $this->line(" - Código: {$record->code}");
        $this->line(" - Servicio: {$record->service}");
        $this->line(" - Remitente: {$record->sender_phone}");
        $this->line(" - Expira: {$record->expires_at}");
        $this->newLine();
        $this->comment('👉 Si tienes el CRM abierto en el navegador, el Toast flotante apareció en la esquina superior derecha.');
    } else {
        $this->error('❌ El servicio no detectó el texto como OTP.');
    }

    return 0;
})->purpose('Simular código de verificación OTP de Meta para visualizar la notificación flotante en el CRM');

Artisan::command('meta:simulate-ad {phone=59178999888} {headline?}', function (WhatsAppWebhookService $service, $phone = '59178999888', $headline = null) {
    $this->info('=== SIMULANDO ATRIBUCIÓN DE ANUNCIO META (CLICK-TO-WHATSAPP) ===');
    $headlineText = $headline ?: 'Campaña Vuelos Promo 30% Off';

    $conv = Conversation::firstOrCreate(
        ['phone' => $phone],
        [
            'contact_name' => 'Pasajero Interesado (Meta Ads)',
            'status' => 'human_live',
            'current_state' => 'HUMAN_SUPPORT',
        ]
    );

    $service->extractAndApplyReferral($conv, [
        'source_type' => 'ad',
        'source_id' => '23847293847298',
        'source_url' => 'https://facebook.com/ads/sample-vuelos',
        'headline' => $headlineText,
        'body' => '¡Reserva tus pasajes con descuento de temporada! Oferta por tiempo limitado.',
        'media_type' => 'image',
    ]);

    $conv->refresh();
    BroadcastHelper::safeBroadcast(new ConversationUpdated($conv));

    $this->info('✅ Atribución de anuncio aplicada a la conversación:');
    $this->line(" - Conversación ID: {$conv->id}");
    $this->line(" - Teléfono: +{$conv->phone}");
    $this->line(" - Campaña / Headline: {$conv->headline}");
    $this->line(" - Ventana 72h expira: {$conv->free_window_expires_at} ({$conv->remaining_free_window_hours} horas restantes)");

    return 0;
})->purpose('Simular conversación iniciada desde anuncio de Facebook');

Artisan::command('meta:simulate-ad-user {name=Valeria Morales} {phone=59176543210} {headline=Promo Vuelos Directos 30% Off}', function (WhatsAppWebhookService $webhookService, ChatbotEngineService $engine, $name = 'Valeria Morales', $phone = '59176543210', $headline = 'Promo Vuelos Directos 30% Off') {
    $this->info('=== SIMULANDO USUARIO ENTRANTE DESDE PUBLICIDAD DE FACEBOOK (CLICK-TO-WHATSAPP) ===');

    // 1. Crear o reiniciar conversación limpia para la prueba
    $conv = Conversation::firstOrNew(['phone' => $phone]);
    $conv->contact_name = $name;
    $conv->status = 'bot_active';
    $conv->current_state = 'MENU_PRINCIPAL';
    $conv->last_interaction_at = now();
    $conv->session_data = [];
    $conv->save();

    // 2. Aplicar atribución de anuncio con ventana de 72 horas gratis de Meta
    $webhookService->extractAndApplyReferral($conv, [
        'source_type' => 'ad',
        'source_id' => '23851948201948',
        'source_url' => 'https://facebook.com/ads/promo-vuelos',
        'headline' => $headline,
        'body' => '¡Reserva tus vuelos nacionales con 30% de descuento! Cupos limitados para esta temporada.',
        'media_type' => 'image',
    ]);

    $conv->refresh();

    // 3. Simular mensaje del usuario que hace clic en el anuncio
    $incomingText = 'Hola, vi su anuncio de vuelos en Facebook y quiero consultar tarifas y fechas disponibles.';
    $engine->processIncomingMessage(
        conversation: $conv,
        text: $incomingText,
        mediaUrl: null,
        mediaType: null
    );

    $conv->refresh();
    $lastBotMsg = ChatMessage::where('conversation_id', $conv->id)->where('sender', 'bot')->latest('id')->first();

    $this->info('✅ Simulación ejecutada con éxito:');
    $this->line(" 👤 Pasajero: {$conv->contact_name} (+{$conv->phone})");
    $this->line(" 📢 Anuncio de Origen: {$conv->headline}");
    $this->line(" 🟢 Ventana Gratuita 72h: {$conv->free_window_expires_at} ({$conv->remaining_free_window_hours} horas restantes)");
    $this->line(" 💬 Mensaje del Pasajero: \"{$incomingText}\"");
    $this->line(' 🤖 Respuesta del Bot: '.mb_substr($lastBotMsg?->message ?? 'Sin respuesta', 0, 70).'...');
    $this->newLine();
    $this->comment("👉 Abre el CRM en tu navegador: verás a '{$conv->contact_name}' en la lista de chats.");
    $this->comment("👉 Al hacer clic en su chat, en la cabecera verás su nombre y el distintivo minimalista [🟢 72h Gratis ({$conv->remaining_free_window_hours}h)].");

    return 0;
})->purpose('Simular usuario completo que entra desde anuncio de Facebook con mensaje y respuesta del bot');
