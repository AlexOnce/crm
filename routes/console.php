<?php

use App\Events\ChatMessageSent;
use App\Events\ConversationUpdated;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\BroadcastHelper;
use App\Services\MetaWhatsAppService;
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
