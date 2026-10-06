<template>
  <aside class="w-full lg:w-96 h-full flex flex-col border-l border-slate-200 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900/90 backdrop-blur-xl overflow-y-auto select-none relative transition-colors duration-200 text-slate-900 dark:text-slate-100">
    
    <!-- Header Minimalista y Limpio -->
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 sticky top-0 z-10 backdrop-blur-md flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
          <ClipboardList class="w-4 h-4" />
        </div>
        <div class="flex items-center gap-2">
          <h3 class="font-bold text-xs uppercase tracking-wider text-slate-800 dark:text-slate-200">
            Resumen Reserva
          </h3>
          <span 
            v-if="leadStatus === 'comprado' || leadStatus === 'emitido'"
            class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30"
          >
            VENDIDO
          </span>
          <span 
            v-else-if="leadStatus === 'contactado' || leadStatus === 'en_proceso' || chatStore.activeConversation?.assigned_user_id || chatStore.activeConversation?.status === 'human_live'"
            class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-400 border border-sky-500/30"
          >
            EN PROCESO
          </span>
          <span 
            v-else
            class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30"
          >
            PENDIENTE
          </span>
        </div>
      </div>

      <div class="flex items-center gap-1.5">
        <button 
          v-if="leadsList.length > 1"
          type="button"
          @click="showHistoryModal = true"
          class="px-2 py-1 rounded-md text-[10px] font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
          title="Ver historial de formularios de este chat"
        >
          Historial ({{ leadsList.length }})
        </button>

        <button 
          v-if="isMobile"
          @click="$emit('close')"
          class="p-1 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
          title="Cerrar panel"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Banner sutil si se visualiza solicitud anterior -->
    <div 
      v-if="selectedLeadIndex > 0" 
      class="px-4 py-1.5 bg-amber-500/10 border-b border-amber-500/20 text-amber-900 dark:text-amber-200 flex items-center justify-between text-[11px] font-medium"
    >
      <span>Viendo solicitud #{{ leadsList.length - selectedLeadIndex }} (anterior)</span>
      <button 
        type="button"
        @click="selectLatestLead"
        class="underline font-bold text-[10px] cursor-pointer hover:text-amber-950 dark:hover:text-amber-100"
      >
        Volver a actual
      </button>
    </div>

    <!-- Contenido del Panel -->
    <div class="p-3.5 sm:p-4 flex-1 space-y-3 text-xs">
      
      <!-- Si no hay conversación seleccionada -->
      <div v-if="!chatStore.activeConversation" class="text-center py-16 px-4 space-y-2.5">
        <div class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
          <ClipboardList class="w-5 h-5 text-slate-500" />
        </div>
        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Sin conversación seleccionada</h4>
        <p class="text-[11px] text-slate-500 leading-relaxed max-w-xs mx-auto">
          Selecciona un chat de la bandeja izquierda para ver los datos de vuelo y pasajeros.
        </p>
      </div>

      <!-- Si no hay Lead en esta conversación -->
      <div v-else-if="!currentLead" class="text-center py-12 px-4 space-y-2.5">
        <div class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
          <FileText class="w-5 h-5 text-slate-500" />
        </div>
        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Sin datos de reserva</h4>
        <p class="text-[11px] text-slate-500 leading-relaxed max-w-xs mx-auto">
          Esta conversación aún no tiene datos de vuelo recopilados por el bot.
        </p>
        <button 
          @click="initManualLead"
          class="px-3.5 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition-all cursor-pointer shadow-xs"
        >
          + Registro Manual
        </button>
      </div>

      <!-- Ficha de Reserva del Pasajero -->
      <template v-else>

        <!-- Indicador de Venta Realizada (si ya fue emitido) -->
        <div 
          v-if="leadStatus === 'comprado' || leadStatus === 'emitido'"
          class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between text-xs"
        >
          <div class="flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <div>
              <span class="font-bold text-emerald-950 dark:text-emerald-200 block text-[11px]">Boleto Emitido</span>
              <span class="font-mono text-[10px] text-emerald-800 dark:text-emerald-300">
                PNR: {{ currentLead?.booking_reference || chatStore.lastIssuedTicket?.pnr || 'Emitido' }}
              </span>
            </div>
          </div>
          <button 
            @click="toggleStatus('nuevo')"
            class="text-[10px] text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 underline cursor-pointer"
          >
            Revertir
          </button>
        </div>

        <!-- ========================================================
             BLOQUE 1: RESUMEN DE VUELO Y TARIFA (UNA SOLA TIRA COMPACTA)
             ======================================================== -->
        <div class="p-3 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-800 shadow-2xs space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="font-black text-sm text-slate-900 dark:text-white font-mono tracking-tight">{{ form.origin }} ➔ {{ form.destination }}</span>
              <span class="text-slate-300 dark:text-slate-700">•</span>
              <span class="font-medium text-slate-600 dark:text-slate-400 text-xs">{{ formatFlightDate(form.flight_date) }}</span>
            </div>
            <button 
              @click="copyToClipboard(`${form.origin} a ${form.destination} - ${formatFlightDate(form.flight_date)}`, 'flight_summary')"
              class="p-1 rounded text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors cursor-pointer"
              title="Copiar ruta y fecha"
            >
              <Check v-if="copiedField === 'flight_summary'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60 text-[11px]">
            <div class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
              <span class="font-mono font-bold text-slate-700 dark:text-slate-300">{{ form.flight_number || 'Vuelo TM' }}</span>
              <span>•</span>
              <span>{{ form.passengers_count }} {{ form.passengers_count === 1 ? 'PAX' : 'PAX' }} ({{ form.passenger_category || 'Adulto' }})</span>
            </div>
            <div class="font-mono font-black text-emerald-600 dark:text-emerald-400 text-xs">
              {{ form.total_price > 0 ? `${form.total_price} BOB` : 'Por cotizar' }}
              <span v-if="form.total_price > 0 && form.passengers_count > 1" class="text-[9px] font-normal text-slate-400">
                ({{ Math.round(form.total_price / form.passengers_count) }} c/u)
              </span>
            </div>
          </div>
        </div>

        <!-- ========================================================
             BLOQUE 2A: PASAJERO INDIVIDUAL (LISTA CLAVE-VALOR PLANA)
             ======================================================== -->
        <div v-if="form.passengers_count <= 1" class="rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-700/60 shadow-2xs overflow-hidden">
          
          <!-- Nombre -->
          <div class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
            <div class="min-w-0 pr-2">
              <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Pasajero</span>
              <span class="font-bold text-slate-900 dark:text-white text-xs truncate block mt-0.5">{{ form.passenger_name || 'Sin especificar' }}</span>
            </div>
            <button 
              @click="copyToClipboard(form.passenger_name, 'name')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
              title="Copiar nombre"
            >
              <Check v-if="copiedField === 'name'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Documento y Pasaporte -->
          <div class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
            <div class="min-w-0 pr-2">
              <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Documento / Pasaporte</span>
              <div class="flex items-center gap-1.5 font-mono text-xs mt-0.5">
                <span class="font-bold text-slate-900 dark:text-white">{{ form.passport_number || 'Pendiente' }}</span>
                <span v-if="customerPassport && !form.passport_number?.includes(customerPassport)" class="text-slate-400">
                  | PAS: {{ customerPassport }}
                </span>
              </div>
            </div>
            <button 
              @click="copyToClipboard(customerPassport || form.passport_number, 'doc')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
              title="Copiar documento"
            >
              <Check v-if="copiedField === 'doc'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Género y Fecha de Nacimiento -->
          <div v-if="customerGender || customerBirthDate" class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
            <div class="min-w-0 pr-2">
              <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Género & Nacimiento</span>
              <div class="flex items-center gap-2 text-xs mt-0.5">
                <span v-if="customerGender" class="font-medium text-slate-800 dark:text-slate-200">{{ customerGender }}</span>
                <span v-if="customerGender && customerBirthDate" class="text-slate-300 dark:text-slate-700">•</span>
                <span v-if="customerBirthDate" class="font-mono text-slate-600 dark:text-slate-400">{{ customerBirthDate }}</span>
              </div>
            </div>
            <button 
              @click="copyToClipboard(`${customerGender || ''} ${customerBirthDate ? `Nac: ${customerBirthDate}` : ''}`.trim(), 'gen_bd')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
              title="Copiar género y fecha"
            >
              <Check v-if="copiedField === 'gen_bd'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Contacto (Teléfono y Email) -->
          <div class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
            <div class="min-w-0 pr-2">
              <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Contacto</span>
              <div class="flex items-center gap-2 text-xs mt-0.5 truncate">
                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">+{{ chatStore.activeConversation?.phone }}</span>
                <span v-if="customerEmail" class="text-slate-300 dark:text-slate-700">•</span>
                <span v-if="customerEmail" class="text-slate-600 dark:text-slate-400 truncate">{{ customerEmail }}</span>
              </div>
            </div>
            <button 
              @click="copyToClipboard(chatStore.activeConversation?.phone, 'phone')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
              title="Copiar teléfono"
            >
              <Check v-if="copiedField === 'phone'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

        </div>

        <!-- ========================================================
             BLOQUE 2B: NÓMINA GRUPAL (PAX > 1)
             ======================================================== -->
        <div v-else class="rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-800 shadow-2xs overflow-hidden">
          <div class="px-3 py-2 bg-slate-50/70 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">
              Nómina ({{ form.passengers_count }} PAX)
            </span>
            <button 
              type="button"
              @click="copyAllPassengers"
              class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-600 hover:bg-sky-500 text-white transition-colors cursor-pointer flex items-center gap-1 active:scale-95"
            >
              <Check v-if="copiedField === 'all_pax'" class="w-3 h-3 text-white stroke-[3]" />
              <Copy v-else class="w-3 h-3 text-white" />
              <span>{{ copiedField === 'all_pax' ? 'Copiada' : 'Copiar Nómina' }}</span>
            </button>
          </div>

          <div class="divide-y divide-slate-100 dark:divide-slate-700/60 max-h-56 overflow-y-auto">
            <!-- 1. Titular -->
            <div class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
              <div class="min-w-0 pr-2">
                <div class="flex items-center gap-1 text-[10px] text-slate-500">
                  <span class="font-bold text-sky-600 dark:text-sky-400">1. TITULAR</span>
                  <span v-if="customerGender" class="font-semibold text-slate-400">({{ customerGender }})</span>
                </div>
                <div class="font-bold text-slate-900 dark:text-white text-xs truncate">{{ form.passenger_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">
                  {{ form.passport_number }} <span v-if="customerBirthDate">• Nac: {{ customerBirthDate }}</span>
                </div>
              </div>
              <button 
                @click="copyToClipboard(`${form.passenger_name} - ${form.passport_number}${customerGender ? ` (${customerGender})` : ''}`, 'pax_1')"
                class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
                title="Copiar titular"
              >
                <Check v-if="copiedField === 'pax_1'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                <Copy v-else class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Acompañantes -->
            <div 
              v-for="(comp, cIdx) in companions" 
              :key="cIdx"
              class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors"
            >
              <div class="min-w-0 pr-2">
                <div class="flex items-center gap-1 text-[10px] text-slate-500">
                  <span class="font-bold text-slate-600 dark:text-slate-400">{{ cIdx + 2 }}. ACOMPAÑANTE</span>
                  <span v-if="comp.gender" class="font-semibold text-slate-400">({{ comp.gender }})</span>
                </div>
                <div class="font-bold text-slate-900 dark:text-white text-xs truncate">{{ comp.name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">
                  {{ comp.doc || 'Doc: Por confirmar' }} <span v-if="comp.birth_date">• Nac: {{ formatFlightDate(comp.birth_date) }}</span>
                </div>
              </div>
              <button 
                @click="copyToClipboard(`${comp.name} - ${comp.doc}${comp.gender ? ` (${comp.gender})` : ''}`, `pax_${cIdx + 2}`)"
                class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
                :title="`Copiar acompañante ${cIdx + 2}`"
              >
                <Check v-if="copiedField === `pax_${cIdx + 2}`" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
                <Copy v-else class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Si faltan acompañantes -->
            <div 
              v-if="companions.length < (form.passengers_count - 1)"
              class="p-2 bg-amber-500/10 text-amber-900 dark:text-amber-200 text-[10px] font-medium flex items-center gap-1.5"
            >
              <Clock class="w-3 h-3 text-amber-600 shrink-0 animate-pulse" />
              <span>Esperando datos de {{ (form.passengers_count - 1) - companions.length }} acompañante(s)...</span>
            </div>
          </div>

          <!-- Contacto compartido en compras grupales -->
          <div class="p-2.5 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
            <div class="min-w-0 pr-2">
              <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Contacto Principal</span>
              <div class="flex items-center gap-1.5 font-mono text-[11px] truncate mt-0.5">
                <span class="font-bold text-slate-800 dark:text-slate-200">+{{ chatStore.activeConversation?.phone }}</span>
                <span v-if="customerEmail" class="text-slate-300 dark:text-slate-700">•</span>
                <span v-if="customerEmail" class="text-slate-500 truncate">{{ customerEmail }}</span>
              </div>
            </div>
            <button 
              @click="copyToClipboard(chatStore.activeConversation?.phone, 'phone')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 transition-colors cursor-pointer"
              title="Copiar teléfono"
            >
              <Check v-if="copiedField === 'phone'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- ========================================================
             BLOQUE 3: OBSERVACIONES Y FACTURACIÓN
             ======================================================== -->
        <!-- Observaciones de Viaje (si existen) -->
        <div v-if="customerObservation" class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-start justify-between gap-2">
          <div class="min-w-0">
            <span class="text-[9px] font-bold text-amber-800 dark:text-amber-400 uppercase tracking-wider block">Observaciones</span>
            <p class="text-xs font-semibold text-amber-950 dark:text-amber-100 mt-0.5 leading-snug">{{ customerObservation }}</p>
          </div>
          <button 
            @click="copyToClipboard(customerObservation, 'observation')"
            class="shrink-0 p-1 rounded text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 transition-colors cursor-pointer"
            title="Copiar observaciones"
          >
            <Check v-if="copiedField === 'observation'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
            <Copy v-else class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Facturación (Razón Social y NIT) -->
        <div v-if="invoiceRazonSocial || invoiceNit || customerInvoice" class="rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-800 shadow-2xs divide-y divide-slate-100 dark:divide-slate-700/60 overflow-hidden">
          <div class="px-3 py-2 bg-slate-50/70 dark:bg-slate-800/50 flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">
              Facturación
            </span>
            <button 
              @click="copyToClipboard(customerInvoice || `${invoiceRazonSocial} - ${invoiceNit}`, 'invoice')"
              class="text-[10px] font-semibold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer flex items-center gap-1"
              title="Copiar datos completos de factura"
            >
              <Check v-if="copiedField === 'invoice'" class="w-3 h-3 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3 h-3" />
              <span>{{ copiedField === 'invoice' ? 'Copiada' : 'Copiar Factura' }}</span>
            </button>
          </div>

          <!-- Razón Social -->
          <div v-if="invoiceRazonSocial" class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
            <div class="min-w-0 pr-2">
              <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Razón Social</span>
              <span class="font-bold text-slate-900 dark:text-white text-xs truncate block mt-0.5">{{ invoiceRazonSocial }}</span>
            </div>
            <button 
              @click="copyToClipboard(invoiceRazonSocial, 'inv_razon')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
              title="Copiar Razón Social"
            >
              <Check v-if="copiedField === 'inv_razon'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- NIT / Documento -->
          <div v-if="invoiceNit" class="p-2.5 flex items-center justify-between group hover:bg-slate-50/70 dark:hover:bg-slate-800 transition-colors">
            <div class="min-w-0 pr-2">
              <div class="flex items-center gap-1.5">
                <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider">NIT / Documento</span>
                <span v-if="invoiceDocType" class="px-1 py-0.2 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[8px] font-bold uppercase">{{ invoiceDocType }}</span>
              </div>
              <span class="font-mono font-black text-slate-900 dark:text-white text-xs block mt-0.5">{{ invoiceNit }}</span>
            </div>
            <button 
              @click="copyToClipboard(invoiceNit, 'inv_nit')"
              class="p-1.5 rounded text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors cursor-pointer"
              title="Copiar NIT"
            >
              <Check v-if="copiedField === 'inv_nit'" class="w-3.5 h-3.5 text-emerald-600 stroke-[3]" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Fallback si es texto libre -->
          <div v-if="!invoiceRazonSocial && !invoiceNit && customerInvoice" class="p-2.5 text-xs text-slate-700 dark:text-slate-300">
            {{ customerInvoice }}
          </div>
        </div>

        <!-- ========================================================
             BOTÓN DE REGISTRAR VENTA DE BOLETO (MINIMALISTA)
             ======================================================== -->
        <div class="pt-2">
          <button
            v-if="leadStatus !== 'comprado' && leadStatus !== 'emitido'"
            @click="handleIssueTicket"
            :disabled="chatStore.issuingTicket"
            class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <Loader2 v-if="chatStore.issuingTicket" class="w-4 h-4 animate-spin" />
            <CheckCircle2 v-else class="w-4 h-4" />
            <span>
              {{ chatStore.issuingTicket 
                ? 'Registrando...' 
                : (form.passengers_count > 1 ? `Confirmar ${form.passengers_count} Ventas` : 'Registrar Venta de Boleto') 
              }}
            </span>
          </button>
        </div>

      </template>

    </div>

    <!-- Toast flotante para notificaciones -->
    <div 
      v-if="toastMessage" 
      class="fixed bottom-5 right-5 z-50 flex items-center gap-2.5 px-4 py-2.5 rounded-xl shadow-xl text-xs font-semibold animate-in fade-in slide-in-from-bottom-2"
      :class="toastType === 'error' ? 'bg-rose-950/90 border border-rose-500/40 text-rose-200' : 'bg-emerald-950/90 border border-emerald-500/40 text-emerald-200'"
    >
      <CheckCircle2 v-if="toastType === 'success'" class="w-4 h-4 text-emerald-400" />
      <X v-else class="w-4 h-4 text-rose-400" />
      <span>{{ toastMessage }}</span>
    </div>

    <!-- Modal de Historial de Solicitudes/Formularios Adaptativo (Modo Claro y Oscuro) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div 
          v-if="showHistoryModal" 
          class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm select-none"
          @click.self="showHistoryModal = false"
        >
          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[88vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200 transition-colors">
            
            <!-- Modal Header -->
            <div class="px-4 py-3.5 sm:px-6 sm:py-4 bg-slate-50 dark:bg-slate-950/90 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0">
              <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-sky-500/20 shrink-0">
                  <History class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <h3 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white truncate">
                      Historial de Solicitudes
                    </h3>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30 whitespace-nowrap shrink-0">
                      {{ leadsList.length }} recibidas
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    Selecciona una solicitud para cargarla en la ficha lateral
                  </p>
                </div>
              </div>
              <button 
                type="button"
                @click="showHistoryModal = false"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer shrink-0 ml-2"
                title="Cerrar modal (Esc)"
              >
                <X class="w-5 h-5" />
              </button>
            </div>

            <!-- Modal Body (Lista de Formularios) -->
            <div class="p-3 sm:p-5 overflow-y-auto space-y-2.5 sm:space-y-3 flex-1 bg-slate-100/70 dark:bg-slate-950/60">
              <div 
                v-for="(ld, idx) in leadsList" 
                :key="ld.id"
                @click="selectLeadFromModal(ld)"
                class="p-3.5 sm:p-4 rounded-xl border transition-all cursor-pointer group"
                :class="[
                  parseInt(currentLead?.id) === parseInt(ld.id)
                    ? 'bg-sky-50/90 dark:bg-sky-950/40 border-sky-400 dark:border-sky-500/60 ring-2 ring-sky-500/20 shadow-xs'
                    : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/80 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 shadow-xs'
                ]"
              >
                <!-- Barra superior de la card: Badges y Fecha/Hora -->
                <div class="flex items-center justify-between gap-2 pb-2.5 mb-2.5 border-b border-slate-200/70 dark:border-slate-800">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span 
                      v-if="idx === 0"
                      class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-sky-500/20 text-sky-800 dark:text-sky-300 border border-sky-500/30 flex items-center gap-1"
                    >
                      ★ ACTUAL
                    </span>
                    <span 
                      v-else
                      class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700"
                    >
                      #{{ leadsList.length - idx }} ANTERIOR
                    </span>

                    <span 
                      class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                      :class="[
                        ld.status === 'comprado' || ld.status === 'emitido' 
                          ? 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 border border-emerald-500/30'
                          : 'bg-amber-500/15 text-amber-800 dark:text-amber-300 border border-amber-500/30'
                      ]"
                    >
                      {{ ld.status || 'nuevo' }}
                    </span>

                    <span v-if="ld.pnr" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
                      PNR: {{ ld.pnr }}
                    </span>
                  </div>

                  <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1 shrink-0 whitespace-nowrap">
                    <Clock class="w-3 h-3 text-slate-400" />
                    <span>{{ formatLeadTimestamp(ld.created_at) }}</span>
                  </div>
                </div>

                <!-- Detalle: Ruta, Fecha y Pasajero -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 text-xs">
                  <!-- Columna 1: Ruta y Vuelo -->
                  <div class="p-2 sm:p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950/50 border border-slate-200/60 dark:border-slate-800/60">
                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Ruta & Vuelo</span>
                    <p class="font-extrabold text-slate-950 dark:text-white flex items-center gap-1.5 mt-0.5">
                      <Plane class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" />
                      <span>{{ ld.origin || 'VVI' }} ➔ {{ ld.destination || 'LPB' }}</span>
                    </p>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 font-mono mt-0.5">
                      {{ ld.flight_number && ld.flight_number !== 'Por definir' ? ld.flight_number : 'Sin vuelo asignado' }}
                    </p>
                  </div>

                  <!-- Columna 2: Fecha de Vuelo -->
                  <div class="p-2 sm:p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950/50 border border-slate-200/60 dark:border-slate-800/60">
                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Fecha del Vuelo</span>
                    <p class="font-extrabold text-slate-950 dark:text-white flex items-center gap-1.5 mt-0.5">
                      <Calendar class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                      <span>{{ formatFlightDate(ld.flight_date) }}</span>
                    </p>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                      {{ ld.passengers_count || 1 }} {{ (ld.passengers_count || 1) === 1 ? 'pasajero' : 'pasajeros' }}
                    </p>
                  </div>

                  <!-- Columna 3: Pasajero / Titular -->
                  <div class="p-2 sm:p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950/50 border border-slate-200/60 dark:border-slate-800/60">
                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Pasajero Titular</span>
                    <p class="font-extrabold text-slate-950 dark:text-white truncate mt-0.5" :title="ld.passenger_name || 'Sin nombre'">
                      {{ ld.passenger_name || 'Sin nombre registrado' }}
                    </p>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 font-mono mt-0.5 truncate">
                      Doc: {{ ld.passport_number || 'No provisto' }}
                    </p>
                  </div>
                </div>

                <!-- Pie de la Card: Tarifa y Botón de Acción -->
                <div class="mt-2.5 pt-2 border-t border-slate-200/70 dark:border-slate-800 flex items-center justify-between">
                  <div class="text-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 block">Tarifa</span>
                    <span v-if="ld.total_price" class="font-extrabold text-emerald-700 dark:text-emerald-400 font-mono">
                      Bs. {{ Number(ld.total_price).toFixed(2) }}
                    </span>
                    <span v-else class="text-slate-500 dark:text-slate-400 text-xs font-medium">Por cotizar</span>
                  </div>

                  <div>
                    <span 
                      v-if="parseInt(currentLead?.id) === parseInt(ld.id)"
                      class="text-xs font-extrabold text-sky-700 dark:text-sky-300 flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-500/15 border border-sky-500/30"
                    >
                      <CheckCircle2 class="w-3.5 h-3.5 stroke-[2.5]" />
                      <span>Cargado en Ficha</span>
                    </span>
                    <button 
                      v-else
                      type="button"
                      class="text-xs font-bold px-3.5 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white transition-all flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95 group-hover:bg-sky-500"
                    >
                      <span>Cargar en Ficha</span>
                      <ArrowRight class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-4 py-3 sm:px-6 sm:py-3.5 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/90 flex items-center justify-between text-xs shrink-0">
              <span class="text-slate-500 dark:text-slate-400 text-[11px] hidden sm:inline">
                Tip: Haz clic sobre cualquier solicitud para cargar sus datos inmediatamente en el panel lateral.
              </span>
              <span class="text-slate-500 dark:text-slate-400 text-[11px] sm:hidden">
                Toca una solicitud para cargarla
              </span>
              <button 
                type="button"
                @click="showHistoryModal = false"
                class="px-4 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold transition-colors cursor-pointer text-xs ml-auto"
              >
                Cerrar
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </aside>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { 
  ClipboardList, 
  Check, 
  FileText, 
  CheckCircle2, 
  ArrowRight,
  ExternalLink,
  Loader2,
  X,
  History,
  Plane,
  Calendar,
  Clock,
  User,
  UserCheck,
  ShieldCheck,
  Mail,
  Phone,
  AlertCircle,
  Sparkles,
  Users,
  Copy
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { useChatStore } from '@/stores/useChatStore';

const props = defineProps({
  isMobile: {
    type: Boolean,
    default: false
  }
});

defineEmits(['close']);

const chatStore = useChatStore();
const currentLead = computed(() => chatStore.activeLead);
const leadsList = computed(() => chatStore.leads || []);
const selectedLeadIndex = computed(() => {
  if (!currentLead.value || !leadsList.value.length) return 0;
  return leadsList.value.findIndex(l => parseInt(l.id) === parseInt(currentLead.value.id));
});

const showHistoryModal = ref(false);

function selectLeadFromModal(lead) {
  chatStore.setActiveLead(lead);
  showHistoryModal.value = false;
  showToast(`Cargada solicitud de ${lead.origin || 'VVI'} a ${lead.destination || 'LPB'}`);
}

function selectLatestLead() {
  if (leadsList.value.length > 0) {
    chatStore.setActiveLead(leadsList.value[0]);
    showToast('Restablecida la solicitud más reciente');
  }
}

function formatLeadTimestamp(ts) {
  if (!ts) return '';
  const d = new Date(ts);
  if (isNaN(d.getTime())) return String(ts);
  return d.toLocaleString('es-BO', { 
    day: '2-digit', 
    month: '2-digit', 
    year: 'numeric',
    hour: '2-digit', 
    minute: '2-digit' 
  });
}

const leadStatus = ref('nuevo');
const copiedField = ref('');

const toastMessage = ref('');
const toastType = ref('success');
let toastTimer = null;

function showToast(msg, type = 'success') {
  toastMessage.value = msg;
  toastType.value = type;
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toastMessage.value = '';
  }, 3500);
}

const customerEmail = computed(() => {
  return currentLead.value?.bot_payload?.passenger_flow?.email 
    || currentLead.value?.bot_payload?.email 
    || chatStore.activeConversation?.session_data?.passenger_flow?.email 
    || chatStore.activeConversation?.session_data?.contact_email 
    || null;
});

const customerBirthDate = computed(() => {
  const bd = currentLead.value?.bot_payload?.passenger_flow?.birth_date 
    || currentLead.value?.bot_payload?.birth_date 
    || chatStore.activeConversation?.session_data?.passenger_flow?.birth_date 
    || chatStore.activeConversation?.session_data?.birth_date 
    || null;
  if (!bd) {
    const details = currentLead.value?.details || '';
    const match = details.match(/(?:Nac|Nacimiento|Fecha de Nacimiento):\s*([0-9\/\-]+)/i);
    if (match) return match[1].trim();
    return null;
  }
  return formatFlightDate(bd);
});

const customerGender = computed(() => {
  const gen = currentLead.value?.bot_payload?.passenger_flow?.gender 
    || currentLead.value?.bot_payload?.gender 
    || chatStore.activeConversation?.session_data?.passenger_flow?.gender 
    || chatStore.activeConversation?.session_data?.gender 
    || null;
  if (!gen) {
    const details = currentLead.value?.details || '';
    const match = details.match(/(?:Género|Genero):\s*([^\,\|\n]+)/i);
    if (match) return match[1].trim();
  }
  return gen;
});

const customerPassport = computed(() => {
  const pass = currentLead.value?.bot_payload?.passenger_flow?.passport_number 
    || currentLead.value?.bot_payload?.passport_number 
    || chatStore.activeConversation?.session_data?.passenger_flow?.passport_number 
    || null;
  if (!pass) {
    const details = currentLead.value?.details || '';
    const match = details.match(/PAS:\s*([A-Za-z0-9\-]+)/i);
    if (match) return match[1].trim();
  }
  return pass;
});

const customerObservation = computed(() => {
  const obs = currentLead.value?.bot_payload?.passenger_flow?.observation 
    || currentLead.value?.bot_payload?.observation 
    || chatStore.activeConversation?.session_data?.passenger_flow?.observation 
    || chatStore.activeConversation?.session_data?.observation 
    || null;
  if (!obs) {
    const details = currentLead.value?.details || '';
    const match = details.match(/(?:Obs|Observación|Observaciones):\s*([^,\|\n]+)/i);
    if (match) return match[1].trim();
  }
  return obs;
});

const customerInvoice = computed(() => {
  const inv = currentLead.value?.bot_payload?.passenger_flow?.invoice_data 
    || currentLead.value?.bot_payload?.invoice_data 
    || chatStore.activeConversation?.session_data?.passenger_flow?.invoice_data 
    || chatStore.activeConversation?.session_data?.invoice_data 
    || null;
  if (!inv) {
    const details = currentLead.value?.details || '';
    const match = details.match(/(?:Factura|Datos de Factura):\s*\[?([^\]\n\r]+)\]?/i);
    if (match) return match[1].trim();
    return null;
  }
  return inv;
});

const invoiceRazonSocial = computed(() => {
  const rz = currentLead.value?.bot_payload?.passenger_flow?.invoice_razon_social 
    || chatStore.activeConversation?.session_data?.passenger_flow?.invoice_razon_social 
    || null;
  if (!rz) {
    const inv = customerInvoice.value || '';
    const match = inv.match(/Razón Social:\s*([^\|,\n]+)/i);
    if (match) return match[1].trim();
  }
  return rz;
});

const invoiceDocType = computed(() => {
  const dt = currentLead.value?.bot_payload?.passenger_flow?.invoice_doc_type 
    || chatStore.activeConversation?.session_data?.passenger_flow?.invoice_doc_type 
    || null;
  if (!dt) {
    const inv = customerInvoice.value || '';
    const match = inv.match(/Tipo:\s*([^\|,\n]+)/i);
    if (match) return match[1].trim();
  }
  return dt;
});

const invoiceNit = computed(() => {
  const nit = currentLead.value?.bot_payload?.passenger_flow?.invoice_nit 
    || chatStore.activeConversation?.session_data?.passenger_flow?.invoice_nit 
    || null;
  if (!nit) {
    const inv = customerInvoice.value || '';
    const match = inv.match(/NIT(?:\/Doc)?:\s*([^\|,\n]+)/i);
    if (match) return match[1].trim();
  }
  return nit;
});

const companions = computed(() => {
  const compList = currentLead.value?.bot_payload?.companions 
    || chatStore.activeConversation?.session_data?.companions;
  if (Array.isArray(compList) && compList.length > 0) {
    return compList;
  }
  const details = currentLead.value?.details || '';
  if (details.includes('Pasajero') || details.includes('Acompañante')) {
    const matches = [...details.matchAll(/(?:Pasajero|Acompañante)\s*\d+:\s*([^\(]+)\s*\((?:Doc:\s*)?([^\)]+)\)/gi)];
    if (matches.length > 0) {
      return matches.map(m => {
        const rawDoc = m[2].trim();
        const genMatch = rawDoc.match(/\((Femenino|Masculino|M|F)\)/i);
        const cleanDoc = rawDoc.replace(/\s*\((Femenino|Masculino|M|F)\)/i, '').trim();
        return { 
          name: m[1].trim(), 
          doc: cleanDoc,
          gender: genMatch ? genMatch[1] : null
        };
      });
    }
  }
  return [];
});

function copyAllPassengers() {
  const parts = [];
  let titularText = `1. ${form.value.passenger_name} - ${form.value.passport_number} (Titular)`;
  if (customerGender.value) titularText += ` [Género: ${customerGender.value}]`;
  if (customerBirthDate.value) titularText += ` [F. Nac: ${customerBirthDate.value}]`;
  if (customerObservation.value) titularText += ` [Obs: ${customerObservation.value}]`;
  parts.push(titularText);
  companions.value.forEach((c, idx) => {
    let compText = `${idx + 2}. ${c.name} - ${c.doc || 'Doc: Por confirmar'}`;
    if (c.gender) compText += ` [Género: ${c.gender}]`;
    if (c.birth_date) compText += ` [F. Nac: ${formatFlightDate(c.birth_date)}]`;
    parts.push(compText);
  });
  if (invoiceRazonSocial.value || invoiceNit.value) {
    parts.push(`Factura: ${invoiceRazonSocial.value || 'N/A'} (NIT: ${invoiceNit.value || 'N/A'})`);
  } else if (customerInvoice.value) {
    parts.push(`Factura: ${customerInvoice.value}`);
  }
  const text = `Nómina TAMep (${form.value.passengers_count} PAX) - Vuelo ${form.value.flight_number} (${form.value.origin} -> ${form.value.destination}):\n` + parts.join('\n');
  copyToClipboard(text, 'all_pax');
  showToast('Nómina completa copiada al portapapeles');
}

const form = ref({
  passenger_name: '',
  passport_number: '',
  origin: 'VVI',
  destination: 'LPB',
  flight_date: '',
  flight_number: 'TM-204',
  passengers_count: 1,
  passenger_category: 'Adulto (+12)',
  total_price: 450.00,
});

watch(
  () => chatStore.activeLead,
  (lead) => {
    if (lead) {
      leadStatus.value = lead.status || 'nuevo';
      const pax = parseInt(
        lead.passengers_count
        || lead.bot_payload?.passengers 
        || lead.bot_payload?.passengers_count 
        || lead.bot_payload?.passenger_flow?.passengers 
        || lead.bot_payload?.passenger_flow?.passengers_count 
        || chatStore.activeConversation?.session_data?.passengers 
        || chatStore.activeConversation?.session_data?.passengers_count 
        || 1
      ) || 1;

      let category = lead.bot_payload?.passenger_category 
        || lead.bot_payload?.passenger_flow?.passenger_category 
        || chatStore.activeConversation?.session_data?.passenger_category 
        || chatStore.activeConversation?.session_data?.passenger_flow?.passenger_category;

      if (!category) {
        const payload = lead.bot_payload || chatStore.activeConversation?.session_data || {};
        const adults = parseInt(payload.adults || 0);
        const children = parseInt(payload.children || 0);
        const babies = parseInt(payload.babies || 0);
        if (adults > 0) {
          const parts = [];
          parts.push(adults === 1 ? '1 Adulto' : `${adults} Adultos`);
          if (children > 0) parts.push(children === 1 ? '1 Menor' : `${children} Menores`);
          if (babies > 0) parts.push(babies === 1 ? '1 Bebé' : `${babies} Bebés`);
          category = parts.join(', ');
        } else {
          category = 'Adulto (+12)';
        }
      }

      form.value = {
        passenger_name: lead.passenger_name || chatStore.activeConversation?.contact_name || '',
        passport_number: lead.passport_number || '',
        origin: lead.origin || 'VVI',
        destination: lead.destination || 'LPB',
        flight_date: lead.flight_date ? String(lead.flight_date).split('T')[0] : new Date().toISOString().split('T')[0],
        flight_number: lead.flight_number && lead.flight_number !== 'Por definir' ? lead.flight_number : (lead.flight_number || 'Pendiente'),
        passengers_count: pax,
        passenger_category: category,
        total_price: (lead.total_price !== null && lead.total_price !== undefined) ? parseFloat(lead.total_price) : 0,
      };
    }
  },
  { immediate: true }
);

function formatFlightDate(dateVal) {
  if (!dateVal) return 'N/A';
  const str = String(dateVal).trim();
  if (/^\d{2}[\/\-]\d{2}[\/\-]\d{4}$/.test(str)) {
    return str.replace(/-/g, '/');
  }
  const clean = str.split('T')[0];
  const match = clean.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/);
  if (match) {
    const year = match[1];
    const month = match[2].padStart(2, '0');
    const day = match[3].padStart(2, '0');
    return `${day}/${month}/${year}`;
  }
  const d = new Date(str);
  if (!isNaN(d.getTime())) {
    const day = String(d.getUTCDate()).padStart(2, '0');
    const month = String(d.getUTCMonth() + 1).padStart(2, '0');
    const year = d.getUTCFullYear();
    return `${day}/${month}/${year}`;
  }
  return str;
}

function initManualLead() {
  leadStatus.value = 'nuevo';
  const session = chatStore.activeConversation?.session_data || {};
  const pax = parseInt(
    session.passengers 
    || session.passengers_count 
    || session.passenger_flow?.passengers 
    || session.passenger_flow?.passengers_count 
    || 1
  ) || 1;

  form.value = {
    passenger_name: session.passenger_name || session.passenger_flow?.full_name || chatStore.activeConversation?.contact_name || '',
    passport_number: session.passport_number || (session.passenger_flow?.doc_number ? `${session.passenger_flow?.doc_type || 'CI'}: ${session.passenger_flow?.doc_number}` : ''),
    origin: session.origin || 'VVI',
    destination: session.destination || 'LPB',
    flight_date: session.flight_date ? String(session.flight_date).split('T')[0] : new Date().toISOString().split('T')[0],
    flight_number: session.selected_flight?.flight_number || 'Pendiente',
    passengers_count: pax,
    passenger_category: session.passenger_category || session.passenger_flow?.passenger_category || 'Adulto (+12)',
    total_price: session.selected_flight?.price ? parseFloat(session.selected_flight.price) : 0,
  };
}

function copyToClipboard(text, fieldKey) {
  if (!text) return;
  navigator.clipboard.writeText(String(text));
  copiedField.value = fieldKey;
  setTimeout(() => {
    if (copiedField.value === fieldKey) {
      copiedField.value = '';
    }
  }, 2000);
}

function toggleStatus(status) {
  leadStatus.value = status;
  if (currentLead.value) {
    currentLead.value.status = status;
  }
}

async function handleIssueTicket() {
  if (!currentLead.value) return;

  const paxCount = parseInt(form.value.passengers_count || 1);
  const paxLabel = paxCount > 1 ? `${paxCount} boletos` : '1 boleto';
  const paxTitle = paxCount > 1 ? `¿Está seguro que vendió los ${paxCount} boletos?` : '¿Está seguro que vendió el boleto?';
  const paxText = paxCount > 1
    ? `Esta operación no se puede deshacer. Se registrará la venta de ${paxCount} boletos (Total: ${form.value.total_price} BOB) en el sistema y se finalizará la atención.`
    : 'Esta operación no se puede deshacer. Se registrará la venta en el sistema y se finalizará la atención.';

  const result = await Swal.fire({
    title: paxTitle,
    text: paxText,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#059669',
    cancelButtonColor: '#64748b',
    confirmButtonText: paxCount > 1 ? `Sí, confirmar ${paxCount} ventas` : 'Sí, confirmar venta',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  const res = await chatStore.issueTicket(form.value);
  if (res && res.success) {
    leadStatus.value = 'emitido';
    await Swal.fire({
      title: paxCount > 1 ? `¡${paxCount} Boletos Vendidos!` : '¡Boleto Vendido!',
      text: `La venta de ${paxLabel} se ha registrado exitosamente en el sistema (PNR: ${res.pnr || 'Emitido'}).`,
      icon: 'success',
      confirmButtonColor: '#059669',
      confirmButtonText: 'Aceptar'
    });
    showToast(`Boleto(s) emitido(s) en sistema (PNR: ${res.pnr}, ${paxLabel}) y atención finalizada.`);
  } else {
    Swal.fire({
      title: 'Error al registrar venta',
      text: res?.message || 'Error al guardar la información en base de datos.',
      icon: 'error',
      confirmButtonColor: '#e11d48',
      confirmButtonText: 'Aceptar'
    });
    showToast(res?.message || 'Error al guardar la información en base de datos.', 'error');
  }
}
</script>
