<?php

namespace Database\Seeders;

use App\Models\BotMessage;
use Illuminate\Database\Seeder;

class BotMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $messages = [
            // ==========================================
            // 1. MENÚS Y SALUDOS
            // ==========================================
            [
                'key' => 'menu_principal_flow',
                'title' => 'Menú Principal (Buscador Flow)',
                'category' => 'Menús y Saludos',
                'description' => 'Mensaje oficial de bienvenida mostrado cuando el buscador interactivo WhatsApp Flow está activo.',
                'variables' => [],
                'message' => "¡Hola! 👋 Te damos una cordial bienvenida a *TAMep* ✈️\n\nPresiona el botón a continuación 👇 para abrir nuestro buscador interactivo y consultar rutas, fechas y asientos disponibles:\n\n💡 *Otras opciones directas:*\n📦 Para información de carga escribe *CARGA* o el número *2*\n🏢 Para oficinas y horarios escribe *OFICINAS* o el número *3*\n\n🔄 ¿NECESITAS CAMBIAR TU VUELO?\nSolicita el cambio de vuelo o cambio de ruta mediante nuestros canales oficiales de WhatsApp:\n📱 73748234 (https://wa.me/59173748234) | 57075796 (https://wa.me/59157075796)",
            ],
            [
                'key' => 'menu_principal_text',
                'title' => 'Menú Principal (Texto y Botones)',
                'category' => 'Menús y Saludos',
                'description' => 'Mensaje oficial de bienvenida y opciones estándar mostrado cuando no se utiliza WhatsApp Flow.',
                'variables' => [],
                'message' => "¡Hola! 👋 Te damos una cordial bienvenida a *TAMep* ✈️\n\n✨ *MENÚ PRINCIPAL - TAMEP*\nEstamos aquí para ayudarte a planificar tu viaje o gestionar tus envíos de manera rápida y segura.\n\nElige una de las siguientes opciones:\n\n1️⃣ *Consultar Rutas y Vuelos*\n2️⃣ *Información sobre Carga*\n3️⃣ *Nuestras Oficinas y Horarios*\n\n🔄 ¿NECESITAS CAMBIAR TU VUELO?\nSolicita el cambio de vuelo o cambio de ruta mediante nuestros canales oficiales de WhatsApp:\n📱 73748234 (https://wa.me/59173748234) | 57075796 (https://wa.me/59157075796)\n\n_Presiona uno de los botones abajo o escribe el número (1-3):_",
            ],

            // ==========================================
            // 2. ATENCIÓN HUMANA Y ASESORES
            // ==========================================
            [
                'key' => 'transferencia_asesor',
                'title' => 'Transferencia a Asesor Humano',
                'category' => 'Atención Humana',
                'description' => 'Aviso enviado al pasajero cuando su solicitud es transferida a un asesor de ventas o soporte.',
                'variables' => [],
                'message' => "*CONECTANDO CON UN ASESOR DE VENTAS*\n\n¡Hemos transferido tu conversación a nuestro equipo de atención!\n\nUn asesor te atenderá en unos momentos para asistirte. ¡Agradecemos tu espera!",
            ],
            [
                'key' => 'nota_voz_recibida',
                'title' => 'Recepción de Nota de Voz',
                'category' => 'Atención Humana',
                'description' => 'Respuesta automática cuando el cliente envía un audio al bot.',
                'variables' => [],
                'message' => 'He recibido tu nota de voz. Para escuchar tu consulta y asistirte de la mejor manera, te estoy comunicando con un asesor de ventas.',
            ],
            [
                'key' => 'confirmacion_datos_recibidos',
                'title' => 'Confirmación de Datos de Pasajero',
                'category' => 'Atención Humana',
                'description' => 'Confirmación enviada al cliente luego de enviar sus datos para la emisión de su pasaje.',
                'variables' => ['nombre', 'vuelo'],
                'message' => "✅ *¡DATOS DE PASAJERO RECIBIDOS CON ÉXITO!*\n\nMuchas gracias, *{nombre}*. Hemos registrado de manera segura tus datos para el vuelo *{vuelo}*.\n\nUn asesor de TAMep está revisando tu solicitud. ¡En unos instantes se comunicará contigo! ✈️",
            ],

            // ==========================================
            // 3. OFICINAS Y CARGA
            // ==========================================
            [
                'key' => 'info_carga',
                'title' => 'Servicios y Oficinas de TAMep Cargo',
                'category' => 'Oficinas y Carga',
                'description' => 'Información completa sobre envíos de encomiendas, agencias regionales de carga, horarios, teléfonos y ubicaciones.',
                'variables' => [],
                'message' => "✈️📦 *EMPRESA PÚBLICA DE TRANSPORTE AÉREO MILITAR – TAMEP* 🛬\n📦 *SERVICIOS DE CARGA AÉREA*\nAtendemos consultas relacionadas con:\n• Cotización y tarifas de carga\n• Envíos de carga a destinos nacionales\n• Seguimiento y estado de la carga vía Web https://www.tamep.bo\n• Requisitos y documentación para el envío de carga especial y mercancías sujetas a condiciones particulares como animales vivos y féretros o restos humanos\n\n✈️📦 *TAMEP CARGO A TU SERVICIO* 📦✈️\n\n*ENVIAMOS TUS ENCOMIENDAS Y CARGA A DIFERENTES DESTINOS DE BOLIVIA*\n\nPara brindarte una mejor atención, ponemos a tu disposición nuestras *OFICINAS REGIONALES*, donde podrás realizar tus envíos, recibir información y consultar sobre nuestros servicios de *CARGA Y ENCOMIENDAS*.\n\n📍 *REGIONAL AV. MONTES – LA PAZ*\n📌 Av. Montes esq. José María Serrano N° 734\n🕐 Lunes a viernes: 08:00 – 16:00\n📞 *72005633*\n📍 Ubicación: https://maps.app.goo.gl/uNhcw3m1bhizLs2u7\n\n📍 *REGIONAL EL ALTO*\n📌 Av. Juan Pablo II, lado Polideportivo Héroes de Octubre – Transporte Aéreo Militar (TAM)\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *72593881*\n📍 Ubicación: https://maps.app.goo.gl/g62pQMn4DE9xvzca6\n\n📍 *REGIONAL COBIJA*\n📌 Av. 9 de febrero frente al regimiento policial, *TAMEP CARGO*\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *71494215*\n📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n📍 *REGIONAL COCHABAMBA*\n📌 Aeropuerto Internacional Jorge Wilstermann\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *67406524*\n📍 Ubicación: https://maps.app.goo.gl/iRpueTMRb495XB926\n\n📍 *REGIONAL SANTA CRUZ*\n📌 Aeropuerto El Trompillo – ingreso al aeropuerto, al fondo, *TAMEP CARGO*\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *71343233*\n📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n📍 *REGIONAL TRINIDAD*\n📌 Aeropuerto Teniente Jorge Henrich Arauz (TTD)\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *72059370*\n📍 Ubicación: https://maps.app.goo.gl/HBGy7sk2oE2egDaF6\n\n📍 *REGIONAL RIBERALTA*\n📌 Aeropuerto de Riberalta\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *72053313*\n📍 Ubicación: https://maps.app.goo.gl/XPiwwLSWxcLcKQjZ7\n\n📍 *REGIONAL GUAYARAMERÍN*\n📌 Aeropuerto de Guayaramerín\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 13:00\n📞 *75898364*\n📍 Ubicación: https://maps.app.goo.gl/4YQQsNRnmdrLTnTA8\n\n📍 Acércate a cualquiera de nuestras oficinas regionales y recibe información sobre nuestros servicios de *envío de carga y encomiendas*.\n\n_Escribe *MENU* para volver al inicio o *BUSCAR* para consultar vuelos._",
            ],
            [
                'key' => 'info_oficinas',
                'title' => 'Oficinas Comerciales y Horarios (Pasajeros)',
                'category' => 'Oficinas y Carga',
                'description' => 'Direcciones, horarios de atención, teléfonos de contacto y ubicaciones en Google Maps para compra de pasajes.',
                'variables' => [],
                'message' => "🇧🇴✈️ *TAMEP CONECTA BOLIVIA*\n\nPara brindarte una atención más cercana, ponemos a tu disposición nuestras *OFICINAS REGIONALES*:\n\n📍 *REGIONAL LA PAZ*\n📌 Av. Montes esq. José María Serrano N.º 734\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/uNhcw3m1bhizLs2u7\n\n📍 *REGIONAL EL ALTO*\n📌 Av. Juan Pablo II, lado Polideportivo Héroes de Octubre – Transporte Aéreo Militar (TAM)\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/g62pQMn4DE9xvzca6\n\n📍 *REGIONAL COBIJA*\n📌 Av. 9 de Febrero, frente al Regimiento Policial\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n📍 *REGIONAL COCHABAMBA*\n📌 Aeropuerto Internacional Jorge Wilstermann\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/iRpueTMRb495XB926\n\n📍 *REGIONAL SANTA CRUZ*\n📌 Aeropuerto El Trompillo – ingreso al aeropuerto, al fondo\n📌 Aeropuerto Internacional de Viru Viru\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/evjdQxK9kgyaxiKP9?g_st=aw\n📍 Ubicación: https://maps.app.goo.gl/yzSmP5BgTJ8bT9au7\n\n📍 *REGIONAL TRINIDAD*\n📌 Aeropuerto Teniente Jorge Henrich Arauz (TTD)\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/HBGy7sk2oE2egDaF6\n\n📍 *REGIONAL RIBERALTA*\n📌 Aeropuerto de Riberalta\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/XPiwwLSWxcLcKQjZ7\n\n📍 *REGIONAL GUAYARAMERÍN*\n📌 Aeropuerto de Guayaramerín\n🕐 Lunes a viernes: 08:00 – 16:00\n🕐 Sábados: 08:00 – 12:00\n📞 73748234 - 71593829\n📍 Ubicación: https://maps.app.goo.gl/4YQQsNRnmdrLTnTA8\n\n_Escribe *MENU* para volver al inicio o *BUSCAR* para consultar vuelos._",
            ],

            // ==========================================
            // 4. POLÍTICAS Y PREGUNTAS FRECUENTES (FAQ)
            // ==========================================
            [
                'key' => 'politica_equipaje',
                'title' => 'Política de Equipaje (Mano y Bodega)',
                'category' => 'Políticas y FAQ',
                'description' => 'Respuesta cuando el cliente consulta sobre peso de maletas, dimensiones o equipaje permitido.',
                'variables' => [],
                'message' => "🧳 *POLÍTICA DE EQUIPAJE - TAMEP*\n\n• *Equipaje de Mano (Cabina):* 1 pieza de hasta 5 kg sin costo adicional.\n• *Equipaje Facturado (Bodega):* 1 pieza de hasta 20 kg incluida en tu boleto.\n• *Exceso de Equipaje:* Sujeto a disponibilidad de peso en aeronave con cobro por kg adicional en mostrador.",
            ],
            [
                'key' => 'formas_pago',
                'title' => 'Formas de Pago Aceptadas',
                'category' => 'Políticas y FAQ',
                'description' => 'Detalle de métodos de pago (QR Simple, transferencias bancarias y oficinas).',
                'variables' => [],
                'message' => "💳 *FORMAS DE PAGO ACEPTADAS*\n\nPuedes abonar tus boletos y envíos de carga mediante:\n• *Código QR Simple:* Válido desde cualquier aplicación bancaria de Bolivia.\n• *Transferencia Bancaria Directa.*\n• *Tarjeta de Débito/Crédito y Efectivo:* En cualquiera de nuestras oficinas comerciales.",
            ],
            [
                'key' => 'telefonos_contacto',
                'title' => 'Canales de Atención y Teléfonos',
                'category' => 'Políticas y FAQ',
                'description' => 'Líneas telefónicas de soporte y canales de contacto oficiales.',
                'variables' => [],
                'message' => "📞 *CANALES DE ATENCIÓN OFICIAL - TAMEP*\n\n• *Teléfonos de Atención:* 73748234 - 71593829\n• *Oficinas Presenciales:* Mostradores en aeropuertos y agencias regionales a nivel nacional.",
            ],
            [
                'key' => 'transporte_mascotas',
                'title' => 'Transporte de Mascotas (PETC)',
                'category' => 'Políticas y FAQ',
                'description' => 'Condiciones para transporte de animales domésticos en cabina, peso máximo y vacunas.',
                'variables' => [],
                'message' => "🐾 *TRANSPORTE DE MASCOTAS (PETC)*\n\nSe permite el viaje de perros y gatos en cabina hasta 7 kg (incluyendo kennel o bolso blando impermeable con ventilación).\n\n📋 *Requisitos:*\n• Certificado de salud emitido por médico veterinario.\n• Carnet de vacunación antirrábica vigente.",
            ],
            [
                'key' => 'viaje_menores',
                'title' => 'Viaje con Menores e Infantes',
                'category' => 'Políticas y FAQ',
                'description' => 'Requisitos de documentación, permisos de la defensoría y condiciones para bebés y niños.',
                'variables' => [],
                'message' => "👶 *VIAJE CON MENORES DE EDAD*\n\n• *Infantes (0 a 23 meses):* Pagan tarifa reducida y viajan en brazos de un adulto responsable.\n• *Niños (2 a 11 años):* Cuentan con tarifa especial de menor con asiento individual.\n• *Requisitos:* Cédula de Identidad o Certificado de Nacimiento original. Si viaja solo o con uno de los padres, se requiere permiso emitido por la Defensoría de la Niñez.",
            ],
            [
                'key' => 'cambios_reembolsos',
                'title' => 'Políticas de Cambios y Reembolsos',
                'category' => 'Políticas y FAQ',
                'description' => 'Condiciones tarifarias para reprogramación de fechas o devoluciones.',
                'variables' => [],
                'message' => "🔄 *POLÍTICA DE CAMBIOS Y REEMBOLSOS*\n\n• Los cambios de fecha están permitidos según la clase tarifaria adquirida (aplicando penalidad y diferencia tarifaria si corresponde).\n• Para gestionar una modificación o reembolso, escribe *ASESOR* para que un agente de ventas te asista personalmente.",
            ],
            [
                'key' => 'consulta_tarifas',
                'title' => 'Consulta de Tarifas de Vuelos',
                'category' => 'Políticas y FAQ',
                'description' => 'Respuesta explicativa cuando el usuario pregunta de forma general por precios o costos.',
                'variables' => [],
                'message' => "✈️ *CONSULTA DE TARIFAS Y VUELOS*\n\nNuestras tarifas varían según la ruta y fecha de viaje. Puedes consultar itinerarios y disponibilidad en tiempo real seleccionando la opción *1 (Rutas y Vuelos)* de nuestro menú principal.",
            ],
            [
                'key' => 'faq_fallback',
                'title' => 'Respuesta de Orientación General (Fallback)',
                'category' => 'Políticas y FAQ',
                'description' => 'Mensaje enviado cuando el texto del pasajero no coincide con ninguna opción ni palabra clave.',
                'variables' => [],
                'message' => 'Gracias por tu mensaje. Para asistirte de forma inmediata, puedes elegir una de las opciones de nuestro menú principal o escribir *ASESOR* para hablar con nuestro equipo de atención.',
            ],

            // ==========================================
            // 5. VUELOS Y DISPONIBILIDAD
            // ==========================================
            [
                'key' => 'info_itinerario',
                'title' => 'Pie de Itinerario de Vuelos',
                'category' => 'Vuelos y Disponibilidad',
                'description' => 'Texto descriptivo que acompaña la imagen o enlace web del itinerario oficial.',
                'variables' => [],
                'message' => "📅 *ITINERARIO OFICIAL DE VUELOS*\n\nAquí puedes revisar las frecuencias semanales, horarios de salida y destinos programados.\n\n_Para cotizar una fecha del itinerario o consultar tarifas, comunícate con un asesor o usa nuestro buscador de vuelos._",
            ],
            [
                'key' => 'sin_vuelos_disponibles',
                'title' => 'Sin Vuelos en la Ruta Seleccionada',
                'category' => 'Vuelos y Disponibilidad',
                'description' => 'Aviso enviado cuando no existen vuelos disponibles en la fecha solicitada ni en días cercanos.',
                'variables' => ['origen', 'destino', 'fecha'],
                'message' => "❌ *NO SE ENCONTRARON VUELOS EN ESTA RUTA*\n\nNo disponemos de vuelos programados en la ruta *{origen} ➔ {destino}* para la fecha *{fecha}* ni en los días cercanos.\n\nTe sugerimos consultar nuestro itinerario completo de vuelos o comunicarte con un asesor de ventas para itinerarios especiales.",
            ],
        ];

        foreach ($messages as $data) {
            BotMessage::updateOrCreate(
                ['key' => $data['key']],
                [
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'message' => $data['message'],
                    'default_message' => $data['message'],
                    'variables' => $data['variables'],
                    'updated_by' => 'Sistema (TAMep)',
                ]
            );
        }
    }
}
