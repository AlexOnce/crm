<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Inicializar plantillas y textos dinámicos del Bot de WhatsApp
        $this->call(BotMessageSeeder::class);

        // 0. Administrador del sistema
        $admin = User::updateOrCreate(
            ['email' => 'admin@tamep.bo'],
            [
                'name' => 'Administrador General (Gerencia)',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 1. Agente de prueba principal
        $agent = User::updateOrCreate(
            ['email' => 'agente@tamep.bo'],
            [
                'name' => 'Carlos Mendoza (Agente Ventas)',
                'password' => Hash::make('password123'),
                'role' => 'agent',
                'is_active' => true,
            ]
        );

        // 2. Agente de ventas adicional
        $agent2 = User::updateOrCreate(
            ['email' => 'laura@tamep.bo'],
            [
                'name' => 'Laura Morales (Agente Ventas)',
                'password' => Hash::make('password123'),
                'role' => 'agent',
                'is_active' => true,
            ]
        );

        // Si estamos en entorno de producción, NO creamos chats ni leads de prueba
        if (app()->isProduction()) {
            return;
        }

        // 2. Conversación 1: Valeria Torrez (EN COLA DE ESPERA - HANDOVER LISTO PARA EMITIR)
        $conv1 = Conversation::updateOrCreate(
            ['phone' => '59171234567'],
            [
                'contact_name' => 'Valeria Torrez',
                'status' => 'waiting_agent',
                'current_state' => 'AGENT_HANDOFF',
                'session_data' => [
                    'origin' => 'VVI',
                    'destination' => 'LPB',
                    'flight_date' => now()->addDay()->format('Y-m-d'),
                    'passenger_name' => 'Valeria Torrez',
                    'passport_number' => 'BO-839201',
                    'selected_flight' => [
                        'flight_number' => 'TM-204',
                        'departure_time' => '07:30',
                        'arrival_time' => '08:35',
                        'price' => 450.00,
                        'currency' => 'BOB',
                    ],
                ],
                'last_interaction_at' => now()->subMinutes(3),
            ]
        );

        // Mensajes del flujo previo con el Bot
        $dialogue1 = [
            ['sender' => 'user', 'msg' => 'Hola, buenas tardes'],
            ['sender' => 'bot',  'msg' => "*MENÚ PRINCIPAL - TAMEP*\n\n1️⃣ Consultar Rutas y Disponibilidad\n2️⃣ Información sobre Carga Aérea\n3️⃣ Nuestras Oficinas y Horarios\n\n_Selecciona una opción (1-3) o escribe 'volver'_"],
            ['sender' => 'user', 'msg' => '1'],
            ['sender' => 'bot',  'msg' => "*CONSULTA DE ITINERARIOS*\n\nPor favor escribe la ciudad de Origen y Destino que deseas cotizar."],
            ['sender' => 'user', 'msg' => 'Santa Cruz a La Paz'],
            ['sender' => 'bot',  'msg' => "*FECHA DE VUELO*\n\nRuta: *Santa Cruz ➔ La Paz*\n\nPor favor indica la fecha de tu viaje (Ejemplo: 2026-10-15 o escribe 'mañana'):"],
            ['sender' => 'user', 'msg' => 'mañana'],
            ['sender' => 'bot',  'msg' => "✈️ *ITINERARIOS DISPONIBLES*\n\n1️⃣ *Vuelo TM-204* (Salida: 07:30 | Llegada: 08:35)\n2️⃣ *Vuelo TM-208* (Salida: 14:15 | Llegada: 15:20)\n3️⃣ *Vuelo TM-312* (Salida: 19:45 | Llegada: 20:50)"],
            ['sender' => 'user', 'msg' => 'TM-204 (07:30)'],
            ['sender' => 'bot',  'msg' => "*DATOS PARA LA RESERVA*\n\nHas seleccionado el vuelo *TM-204*. Por favor envía tu Nombre y Documento."],
            ['sender' => 'user', 'msg' => 'Valeria Torrez - Pasaporte BO-839201'],
            ['sender' => 'bot',  'msg' => "*CONECTANDO CON UN ASESOR DE VENTAS*\n\n¡Hemos transferido tu solicitud a nuestro equipo de emisión! Un asesor te atenderá por este mismo canal."],
        ];

        foreach ($dialogue1 as $i => $d) {
            ChatMessage::create([
                'conversation_id' => $conv1->id,
                'sender' => $d['sender'],
                'message' => $d['msg'],
                'created_at' => now()->subMinutes(14 - $i),
            ]);
        }

        // Lead pre-llenado listo para emitir en el formulario de la derecha
        Lead::create([
            'conversation_id' => $conv1->id,
            'phone' => $conv1->phone,
            'type' => 'compra_pasaje',
            'passenger_name' => 'Valeria Torrez',
            'passport_number' => 'BO-839201',
            'origin' => 'VVI',
            'destination' => 'LPB',
            'flight_date' => now()->addDay()->format('Y-m-d'),
            'flight_number' => 'TM-204',
            'total_price' => 450.00,
            'status' => 'nuevo',
            'details' => 'Reserva iniciada por el bot para vuelo TM-204.',
            'bot_payload' => $conv1->session_data,
        ]);

        // 3. Conversación 2: Roberto Gómez (Atendida actualmente por el agente)
        $conv2 = Conversation::updateOrCreate(
            ['phone' => '59178945612'],
            [
                'contact_name' => 'Roberto Gómez',
                'status' => 'human_live',
                'current_state' => 'AGENT_HANDOFF',
                'assigned_user_id' => $agent->id,
                'last_interaction_at' => now()->subMinutes(12),
            ]
        );

        ChatMessage::create([
            'conversation_id' => $conv2->id,
            'sender' => 'user',
            'message' => '¿Tienen disponibilidad de carga para 40kg a Cochabamba?',
            'created_at' => now()->subMinutes(20),
        ]);

        ChatMessage::create([
            'conversation_id' => $conv2->id,
            'sender' => 'agent',
            'agent_id' => $agent->id,
            'message' => 'Hola Roberto, claro que sí. En TAMep Carga contamos con tarifa preferencial para más de 30kg. ¿Qué tipo de mercancía trasladas?',
            'created_at' => now()->subMinutes(12),
        ]);

        Lead::create([
            'conversation_id' => $conv2->id,
            'phone' => $conv2->phone,
            'type' => 'carga_aerea',
            'passenger_name' => 'Roberto Gómez',
            'origin' => 'VVI',
            'destination' => 'CBB',
            'status' => 'contactado',
            'details' => 'Cotización de carga aérea 40kg',
        ]);
    }
}
