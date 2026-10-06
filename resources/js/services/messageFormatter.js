/**
 * Servicio de formateo amigable de mensajes para el CRM de WhatsApp
 * Convierte IDs técnicos de botones (flight_1, menu_rutas, etc.) en etiquetas legibles para los agentes.
 */

export function isInteractiveButtonReply(msg) {
  if (!msg) return false;
  const text = typeof msg === 'string' ? msg.trim() : (msg.message ? msg.message.trim() : '');
  if (!text) return false;

  if (text.startsWith('🔘')) return true;
  return /^(flight_\d+|menu_\w+|btn_\w+|date_[\w\-]+|ruta_\d+)$/i.test(text);
}

export function getInteractiveButtonDetails(msg, sessionData = null) {
  const text = typeof msg === 'string' ? msg.trim() : (msg.message ? msg.message.trim() : '');
  if (!text) {
    return { category: 'Botón WhatsApp', title: '', subtitle: '' };
  }

  // 1. Si ya viene formateado desde backend con el emoji indicador 🔘
  if (text.startsWith('🔘')) {
    const cleanTitle = text.replace(/^🔘\s*/, '');
    let cat = 'Botón WhatsApp';
    const lower = cleanTitle.toLowerCase();
    if (lower.includes('vuelo')) cat = '✈️ Selección de Vuelo';
    else if (lower.includes('asesor')) cat = '👨‍💼 Solicitud de Asesor';
    else if (lower.includes('carga')) cat = '📦 Carga y Envíos';
    else if (lower.includes('oficina')) cat = '🏢 Nuestras Oficinas';
    else if (lower.includes('menú') || lower.includes('menu')) cat = '🏠 Menú Principal';
    else if (lower.includes('fecha')) cat = '📅 Fecha de Vuelo';

    return {
      category: cat,
      title: cleanTitle,
      subtitle: 'Opción pulsada por el cliente en WhatsApp'
    };
  }

  // 2. Selección de Vuelo (ej: flight_1, flight_2, flight_3)
  const flightMatch = text.match(/^flight_(\d+)$/i);
  if (flightMatch) {
    const flightIndex = parseInt(flightMatch[1], 10) - 1;
    const available = sessionData?.available_flights || [];
    const selectedFlight = available[flightIndex] || (flightIndex === 0 ? sessionData?.selected_flight : null);

    if (selectedFlight) {
      const fNum = selectedFlight.flight_number || `Vuelo #${flightIndex + 1}`;
      const fDep = selectedFlight.departure_time ? ` (Salida: ${selectedFlight.departure_time})` : '';
      const fRoute = (selectedFlight.origin && selectedFlight.destination) 
        ? ` • ${selectedFlight.origin} ➔ ${selectedFlight.destination}` 
        : '';
      return {
        category: '✈️ Selección de Vuelo',
        title: `${fNum}${fDep}`,
        subtitle: `Opción #${flightIndex + 1} elegida por el pasajero${fRoute}`
      };
    }

    return {
      category: '✈️ Selección de Vuelo',
      title: `Vuelo #${flightIndex + 1} Seleccionado`,
      subtitle: `El cliente eligió la opción #${flightIndex + 1} de los itinerarios mostrados`
    };
  }

  // 3. Menús y Botones de Acción del Chatbot
  switch (text.toLowerCase()) {
    case 'menu_rutas':
      return {
        category: '🔘 Botón WhatsApp',
        title: '👉 Buscar Vuelos / Rutas',
        subtitle: 'El cliente pulsó el botón para consultar itinerarios'
      };
    case 'menu_carga':
      return {
        category: '🔘 Botón WhatsApp',
        title: '📦 Carga y Envíos',
        subtitle: 'El cliente pulsó el botón para consultar envíos y tarifas de carga'
      };
    case 'menu_oficinas':
      return {
        category: '🔘 Botón WhatsApp',
        title: '🏢 Nuestras Oficinas y Horarios',
        subtitle: 'El cliente pulsó el botón para ver sucursales y puntos de atención'
      };
    case 'menu_volver':
      return {
        category: '🔘 Botón WhatsApp',
        title: '🏠 Menú Principal',
        subtitle: 'El cliente solicitó regresar al inicio del menú'
      };
    case 'btn_asesor':
      return {
        category: '👨‍💼 Solicitud de Asesor',
        title: 'Hablar con un Asesor de Ventas',
        subtitle: 'El cliente solicitó atención directa de un asesor humano'
      };
    case 'btn_cotizar':
      return {
        category: '💵 Cotización',
        title: 'Cotizar Pasaje',
        subtitle: 'El cliente solicitó la cotización del vuelo'
      };
    default:
      if (/^date_[\w\-]+$/i.test(text)) {
        const dateStr = text.replace(/^date_/i, '');
        return {
          category: '📅 Fecha Seleccionada',
          title: `Fecha de Vuelo: ${dateStr}`,
          subtitle: 'Fecha elegida entre las fechas cercanas disponibles'
        };
      }
      if (/^ruta_\d+$/i.test(text)) {
        return {
          category: '🗺️ Ruta Seleccionada',
          title: 'Ruta Seleccionada',
          subtitle: 'Opción de ruta elegida en el menú interactivo'
        };
      }
      return {
        category: '🔘 Botón WhatsApp',
        title: text,
        subtitle: 'Interacción interactiva del cliente'
      };
  }
}

/**
 * Retorna texto limpio para la previsualización del último mensaje en la barra lateral
 */
export function formatMessagePreview(rawMessage, sessionData = null) {
  if (!rawMessage) return '';
  if (!isInteractiveButtonReply(rawMessage)) return rawMessage;
  const details = getInteractiveButtonDetails(rawMessage, sessionData);
  return `🔘 ${details.title}`;
}
