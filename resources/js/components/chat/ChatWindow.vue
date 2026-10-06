<template>
  <section class="flex-1 h-full flex flex-col bg-slate-50/70 dark:bg-slate-900/40 backdrop-blur-md relative overflow-hidden transition-colors duration-200">
    <!-- Estado vacío si no hay chat seleccionado -->
    <div v-if="!chatStore.activeConversation" class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400 dark:text-slate-500">
      <div class="w-16 h-16 rounded-2xl bg-white dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center mb-4 shadow-xs dark:shadow-inner text-sky-500 dark:text-sky-400">
        <MessageSquare class="w-8 h-8" />
      </div>
      <h3 class="text-base font-semibold text-slate-700 dark:text-slate-300 mb-1">Selecciona una conversación</h3>
      <p class="text-xs max-w-sm text-slate-500 dark:text-slate-400">
        Elige un chat de la lista izquierda para atender al pasajero o revisar los datos capturados por el bot para la emisión del pasaje.
      </p>
    </div>

    <!-- Conversación activa -->
    <template v-else>
      <!-- Header del Chat -->
      <header class="px-3 sm:px-6 py-2.5 sm:py-3.5 border-b border-slate-200 dark:border-slate-800/80 bg-white/95 dark:bg-slate-950/70 flex items-center justify-between relative z-30 shrink-0 backdrop-blur-md transition-colors duration-200">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
          <!-- Botón Volver a la Lista (Visible solo en móviles < lg) -->
          <button 
            @click="chatStore.activeConversation = null"
            class="lg:hidden p-2 -ml-1 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800 transition-colors cursor-pointer shrink-0"
            title="Volver al listado de chats"
          >
            <ArrowLeft class="w-4 h-4" />
          </button>

          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-br from-sky-600 to-indigo-700 flex items-center justify-center font-bold text-white text-xs sm:text-sm shadow-md shadow-sky-900/20 shrink-0">
            {{ (chatStore.activeConversation.contact_name || chatStore.activeConversation.phone).substring(0, 2).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <h3 class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white truncate max-w-[150px] sm:max-w-[260px]">
              {{ chatStore.activeConversation.contact_name || 'Pasajero de WhatsApp' }}
            </h3>
            <div class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5 truncate">
              <span>+{{ chatStore.activeConversation.phone }}</span>
            </div>
          </div>
        </div>

        <!-- Acciones de Cabecera -->
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">

          <!-- Botón Datos Recopilados (Visible en móviles < lg) -->
          <button 
            @click="$emit('open-booking')"
            class="lg:hidden px-2 sm:px-2.5 py-1.5 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 text-xs font-bold flex items-center gap-1 transition-all shadow-xs cursor-pointer"
            title="Ver Datos Recopilados"
          >
            <ClipboardList class="w-4 h-4" />
            <span class="hidden md:inline">Datos</span>
          </button>

          <!-- Botón Tomar Conversación -->
          <button 
            v-if="chatStore.activeConversation.status === 'waiting_agent'"
            @click="chatStore.takeActiveConversation()"
            class="px-2 sm:px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs transition-all shadow-md shadow-sky-600/30 flex items-center gap-1 active:scale-95 cursor-pointer"
          >
            <UserCheck class="w-4 h-4 text-sky-200" />
            <span class="hidden sm:inline">Tomar Chat</span>
            <span class="sm:hidden text-[11px]">Tomar</span>
          </button>

          <!-- Botón Buscar / Navegar por Fecha (En móviles se accede también desde el menú ⋮) -->
          <div class="relative" ref="dateDropdownRef">
            <button 
              @click="toggleDateDropdown"
              :class="[
                'hidden sm:flex px-2.5 sm:px-3 py-1.5 rounded-lg border text-xs transition-all items-center gap-1.5 active:scale-95 cursor-pointer shadow-xs',
                showDateDropdown 
                  ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 border-sky-300 dark:border-sky-700 font-medium' 
                  : 'bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border-slate-200 dark:border-slate-700'
              ]"
              title="Buscar o ir a una fecha en el chat"
            >
              <Calendar class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" />
              <span>Fecha</span>
            </button>

            <!-- Menú Flotante de Selección de Fecha (Adaptable a pantallas móviles) -->
            <div 
              v-if="showDateDropdown" 
              class="absolute right-0 mt-2 w-[calc(100vw-1.5rem)] sm:w-80 max-w-sm rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl z-50 p-3 space-y-3 animate-fade-in text-xs"
            >
              <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200">
                  <Calendar class="w-4 h-4 text-sky-500" />
                  <span>Navegar por fecha</span>
                </div>
                <button 
                  @click="showDateDropdown = false" 
                  class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>

              <!-- Atajos Rápidos -->
              <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Accesos directos</label>
                <div class="grid grid-cols-3 gap-1.5">
                  <button 
                    type="button"
                    @click="jumpToDate(latestChatDateKey)"
                    :disabled="!latestChatDateKey"
                    class="py-1.5 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-sky-950/40 hover:text-sky-600 dark:hover:text-sky-400 text-slate-700 dark:text-slate-300 font-medium transition-colors text-center disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                  >
                    Hoy
                  </button>
                  <button 
                    type="button"
                    @click="jumpToDate(yesterdayChatDateKey)"
                    :disabled="!yesterdayChatDateKey"
                    class="py-1.5 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-sky-950/40 hover:text-sky-600 dark:hover:text-sky-400 text-slate-700 dark:text-slate-300 font-medium transition-colors text-center disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                  >
                    Ayer
                  </button>
                  <button 
                    type="button"
                    @click="jumpToDate(earliestChatDateKey)"
                    :disabled="!earliestChatDateKey"
                    class="py-1.5 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-sky-950/40 hover:text-sky-600 dark:hover:text-sky-400 text-slate-700 dark:text-slate-300 font-medium transition-colors text-center disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                  >
                    Inicio
                  </button>
                </div>
              </div>

              <!-- Selector Desplegable para Historial Extenso (Escala a meses y años sin saturar) -->
              <div v-if="availableChatDates.length > 0" class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                  <span>Días con actividad</span>
                  <span class="text-sky-600 dark:text-sky-400 font-mono">{{ availableChatDates.length }} fechas</span>
                </label>
                <select 
                  v-model="selectedDropdownDate" 
                  @change="onSelectDropdownDate"
                  class="w-full py-1.5 px-2.5 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                >
                  <option value="" disabled>Seleccionar un día...</option>
                  <option v-for="d in availableChatDates" :key="d.key" :value="d.key">
                    {{ d.label }} ({{ d.count }} msgs)
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Botón Transferir a otro Agente (Desactivado temporalmente) -->
          <div v-if="false" class="relative">
            <button 
              @click="toggleTransferDropdown"
              class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs transition-all flex items-center gap-1.5 active:scale-95 cursor-pointer shadow-xs"
              title="Transferir conversación a otro asesor"
            >
              <ArrowLeftRight class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" />
              <span class="hidden md:inline">Transferir</span>
            </button>

            <!-- Menú Flotante de Selección de Agente -->
            <div 
              v-if="showTransferDropdown" 
              class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl z-50 p-2 space-y-1 animate-fade-in"
            >
              <div class="px-2 py-1 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center justify-between">
                <span>Transferir a:</span>
                <button @click="showTransferDropdown = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-0.5 rounded cursor-pointer">
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
              <div v-if="loadingAgents" class="px-2 py-2 text-xs text-slate-500 text-center">
                Cargando agentes...
              </div>
              <div v-else-if="availableAgents.length === 0" class="px-2 py-2 text-xs text-slate-500 text-center">
                No hay otros agentes disponibles
              </div>
              <button
                v-else
                v-for="agent in availableAgents"
                :key="agent.id"
                @click="transferToAgent(agent)"
                class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white flex items-center justify-between group transition-colors cursor-pointer"
              >
                <div>
                  <div class="font-medium text-slate-900 dark:text-white">{{ agent.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ agent.email }}</div>
                </div>
                <ArrowRight class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400 opacity-0 group-hover:opacity-100 transition-opacity" />
              </button>
            </div>
          </div>

          <!-- Botón Devolver al Bot (Desktop) -->
          <button 
            @click="confirmReleaseToBot"
            class="hidden sm:flex px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs transition-all items-center gap-1.5 active:scale-95 cursor-pointer shadow-xs"
            title="Finalizar atención del agente y reactivar el bot"
          >
            <Bot class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" />
            <span class="hidden md:inline">Reactivar Bot</span>
          </button>

          <!-- Menú de 3 Puntos (⋮) exclusivo para Móviles (< sm) -->
          <div class="relative sm:hidden" ref="mobileMenuRef">
            <button 
              @click="toggleMobileMenu"
              :class="[
                'p-1.5 rounded-lg border text-xs transition-all flex items-center justify-center active:scale-95 cursor-pointer shadow-xs',
                showMobileMenu
                  ? 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white border-slate-300 dark:border-slate-600'
                  : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
              ]"
              title="Más opciones"
            >
              <MoreVertical class="w-4 h-4" />
            </button>

            <!-- Desplegable del Menú Móvil -->
            <div 
              v-if="showMobileMenu" 
              class="absolute right-0 mt-2 w-52 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl z-50 p-1.5 space-y-1 text-xs animate-in fade-in"
            >
              <button
                @click="openMobileDateNav"
                class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center gap-2.5 transition-colors cursor-pointer"
              >
                <Calendar class="w-4 h-4 text-sky-500 shrink-0" />
                <span>Navegar por fecha</span>
              </button>

              <button
                @click="triggerReleaseToBotMobile"
                class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center gap-2.5 transition-colors cursor-pointer"
              >
                <Bot class="w-4 h-4 text-amber-500 shrink-0" />
                <span>Reactivar Bot</span>
              </button>

              <button
                @click="openBookingFromMobileMenu"
                class="w-full text-left px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-between transition-colors cursor-pointer"
              >
                <div class="flex items-center gap-2.5">
                  <ClipboardList class="w-4 h-4 text-emerald-500 shrink-0" />
                  <span>Datos Recopilados</span>
                </div>
                <span v-if="chatStore.activeLead" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              </button>
            </div>
          </div>
        </div>
      </header>


      <!-- Banner de Alerta si es Handover (Compacta en móvil) -->
      <div 
        v-if="chatStore.activeConversation.status === 'waiting_agent'"
        class="bg-gradient-to-r from-amber-500/15 via-amber-500/10 to-transparent dark:from-amber-950/40 dark:via-amber-900/30 dark:to-slate-950 px-3 sm:px-6 py-1.5 sm:py-2 border-b border-amber-500/30 flex items-center justify-between text-xs text-amber-800 dark:text-amber-200 transition-colors duration-200 gap-2"
      >
        <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
          <AlertTriangle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-amber-500 dark:text-amber-400 shrink-0" />
          <span class="sm:hidden text-[11px] font-medium truncate">Pasajero solicita atención</span>
          <span class="hidden sm:inline">El pasajero solicita atención. Los datos recopilados están disponibles en el panel lateral.</span>
        </div>
        <button 
          @click="$emit('open-booking')" 
          class="sm:hidden px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-900 dark:text-amber-200 text-[10px] font-bold border border-amber-500/30 shrink-0 cursor-pointer"
        >
          Ver datos
        </button>
      </div>

      <!-- Área Central de Mensajes con Píldora de Fecha Flotante Única -->
      <div class="flex-1 flex flex-col min-h-0 relative">
        <!-- Píldora de Fecha Flotante Única (Aparece al escrolear y desaparece automáticamente al detenerse estilo WhatsApp) -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-2 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition duration-300 ease-in"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 -translate-y-2 scale-95"
        >
          <div 
            v-if="floatingDateLabel && isScrolledUp && isScrolling && !showDateDropdown && !showMobileMenu" 
            class="absolute top-2 left-1/2 -translate-x-1/2 z-10 pointer-events-none"
          >
            <span class="pointer-events-auto px-3.5 py-1 rounded-full bg-white/95 dark:bg-slate-800/95 text-slate-700 dark:text-slate-200 text-[11px] font-semibold uppercase tracking-wider shadow-md border border-slate-200/90 dark:border-slate-700/90 backdrop-blur-md select-none flex items-center gap-1.5">
              <Calendar class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" />
              <span>{{ floatingDateLabel }}</span>
            </span>
          </div>
        </Transition>

        <!-- Historial de Mensajes -->
        <div 
          ref="messagesContainer" 
          @scroll="handleScroll"
          class="flex-1 overflow-y-auto p-3 sm:p-6 space-y-3 sm:space-y-3.5 scroll-smooth relative"
        >
          <div v-if="chatStore.loadingMessages" class="text-center py-8 text-slate-400 dark:text-slate-500 text-xs">
            Cargando mensajes...
          </div>

          <template v-else>
            <template v-for="(msg, index) in chatStore.messages" :key="msg.id">
              <!-- Separador de Fecha en el flujo del chat (Fluye naturalmente, nunca se acumula) -->
              <div 
                v-if="isFirstMessageOfDay(msg, index)" 
                :id="'date-divider-' + getDateKey(msg.created_at)"
                class="flex justify-center my-3.5 select-none relative z-0 transition-all duration-300"
              >
                <span 
                  :class="[
                    'px-3.5 py-1 rounded-lg text-[11px] uppercase tracking-wider shadow-xs border backdrop-blur-md select-none transition-all duration-500',
                    activeHighlightedDate === getDateKey(msg.created_at)
                      ? 'bg-sky-500 text-white border-sky-400 ring-4 ring-sky-500/30 scale-105 font-bold shadow-lg'
                      : 'bg-white/90 dark:bg-slate-800/90 text-slate-600 dark:text-slate-300 font-medium border-slate-200/80 dark:border-slate-700/80'
                  ]"
                >
                  {{ formatDividerDate(msg.created_at) }}
                </span>
              </div>

            <!-- Burbuja del Mensaje -->
            <div 
              :id="'msg-item-' + msg.id"
              :class="[
                'flex flex-col max-w-[90%] sm:max-w-[80%] transition-all animate-slide-up',
                msg.sender === 'agent' ? 'ml-auto items-end' : 'mr-auto items-start'
              ]"
            >
              <!-- Remitente Header -->
              <div class="flex items-center gap-1.5 mb-1 px-1 text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                <span v-if="msg.sender === 'agent'" class="text-sky-600 dark:text-sky-400 flex items-center gap-1 font-semibold">
                  <span>{{ msg.agent?.name || 'Agente' }}</span>
                  <User class="w-3 h-3 text-sky-600 dark:text-sky-400" />
                </span>
                <span v-else-if="msg.sender === 'bot'" class="text-amber-600 dark:text-amber-400 flex items-center gap-1 font-semibold">
                  <Bot class="w-3 h-3 text-amber-500 dark:text-amber-400" />
                  <span>TAMep Bot</span>
                </span>
                <span v-else class="text-slate-700 dark:text-slate-300 font-semibold">
                  {{ chatStore.activeConversation.contact_name || 'Pasajero' }}
                </span>
                <span :title="formatFullDateTimeTooltip(msg.created_at)" class="text-slate-400 dark:text-slate-500 font-mono text-[10px] cursor-help">
                  {{ formatTime(msg.created_at) }}
                </span>
              </div>

            <!-- Burbuja del Mensaje -->
            <div 
              :class="[
                'max-w-[85%] sm:max-w-[70%] break-words rounded-2xl text-xs whitespace-pre-wrap leading-relaxed shadow-xs',
                (msg.media_url && (msg.media_type === 'image' || isImageUrl(msg.media_url)) && !hasMeaningfulText(msg)) ? 'p-1.5' : 'px-4 py-2.5',
                msg.sender === 'agent' 
                  ? 'bg-gradient-to-br from-sky-600 to-blue-700 text-white rounded-tr-none border border-sky-400/20' 
                  : msg.sender === 'bot'
                  ? 'bg-amber-500/10 dark:bg-slate-800/90 text-slate-800 dark:text-slate-200 border border-amber-500/30 dark:border-amber-500/20 rounded-tl-none font-sans'
                  : 'bg-white dark:bg-slate-800/80 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700/60 rounded-tl-none shadow-xs'
              ]"
            >
              <!-- Si es una interacción con botón interactivo de WhatsApp -->
              <div v-if="isInteractiveButtonReply(msg)" class="space-y-1.5 py-0.5">
                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-sky-500/15 border border-sky-500/30 text-sky-700 dark:text-sky-300 text-[10px] font-bold">
                  <MousePointerClick class="w-3 h-3 text-sky-600 dark:text-sky-400" />
                  <span>{{ getInteractiveButtonDetails(msg, chatStore.activeConversation?.session_data).category }}</span>
                </div>
                <div class="font-extrabold text-xs sm:text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                  <span>{{ getInteractiveButtonDetails(msg, chatStore.activeConversation?.session_data).title }}</span>
                </div>
                <div v-if="getInteractiveButtonDetails(msg, chatStore.activeConversation?.session_data).subtitle" class="text-[10px] text-slate-500 dark:text-slate-400 font-medium leading-tight">
                  {{ getInteractiveButtonDetails(msg, chatStore.activeConversation?.session_data).subtitle }}
                </div>
              </div>

              <!-- Texto del Mensaje estándar (oculta placeholders genéricos como '📷 Imagen' para evitar redundancia) -->
              <div v-else-if="!msg.media_url || hasMeaningfulText(msg)" class="whitespace-pre-wrap leading-relaxed">
                {{ msg.message }}
              </div>

              <!-- Botones Interactivos de WhatsApp (Meta Cloud API) -->
              <div v-if="msg.sender === 'bot' && parseInteractiveButtons(msg.message).length" class="mt-3 pt-2 border-t border-amber-500/20">
                <div class="text-[10px] text-sky-600 dark:text-sky-400 font-semibold mb-1.5 flex items-center gap-1">
                  <Sparkles class="w-3 h-3 text-amber-500 dark:text-amber-400 shrink-0" />
                  <span>Botones interactivos en WhatsApp:</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <span 
                    v-for="(btn, bIdx) in parseInteractiveButtons(msg.message)" 
                    :key="bIdx"
                    class="px-2.5 py-1 rounded-lg bg-sky-500/15 border border-sky-400/30 text-sky-800 dark:text-sky-200 text-[11px] font-semibold flex items-center gap-1.5 shadow-xs"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500 dark:bg-sky-400 animate-pulse"></span>
                    {{ btn }}
                  </span>
                </div>
              </div>

              <!-- Si tiene imagen, audio o documento adjunto -->
              <div v-if="msg.media_url" :class="hasMeaningfulText(msg) ? 'mt-2.5 pt-2 border-t border-slate-200 dark:border-white/10' : ''">
                <!-- Imagen con click para modal lightbox -->
                <div 
                  v-if="msg.media_type === 'image' || isImageUrl(msg.media_url)"
                  @click="openImageModal(msg.media_url, msg)"
                  class="relative group rounded-xl overflow-hidden border border-slate-200 dark:border-white/15 bg-black/10 dark:bg-black/40 shadow-inner cursor-pointer select-none transition-all duration-200 hover:border-sky-400/60 hover:shadow-lg hover:shadow-sky-500/10"
                >
                  <img 
                    :src="msg.media_url" 
                    :alt="hasMeaningfulText(msg) ? msg.message : 'Imagen adjunta'"
                    class="w-full max-h-72 object-contain bg-slate-100 dark:bg-slate-950/60 block transition-transform duration-300 group-hover:scale-[1.01]"
                    loading="lazy"
                  />
                  <!-- Overlay de hover con botón de ampliar y descarga -->
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end justify-between p-2.5 pointer-events-none">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-md text-[11px] font-medium text-white shadow border border-white/10">
                      <Maximize2 class="w-3 h-3 text-sky-400" />
                      <span>Ampliar imagen</span>
                    </span>
                    <button 
                      type="button"
                      @click.stop="downloadImage(msg.media_url)"
                      title="Descargar imagen"
                      class="pointer-events-auto p-1.5 rounded-lg bg-slate-900/90 backdrop-blur-md hover:bg-sky-600 text-slate-300 hover:text-white transition-colors border border-white/10 shadow cursor-pointer"
                    >
                      <Download class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Si es nota de voz o audio de WhatsApp -->
                <div 
                  v-else-if="msg.media_type === 'audio' || isAudioUrl(msg.media_url)"
                  class="p-3 rounded-2xl bg-white/95 dark:bg-slate-950/80 border border-emerald-500/30 flex flex-col gap-2 max-w-sm shadow-xs dark:shadow-md"
                >
                  <div class="flex items-center justify-between text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                    <span class="flex items-center gap-1.5">
                      <Mic class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" />
                      <span>Nota de voz de WhatsApp</span>
                    </span>
                    <button 
                      type="button"
                      @click="downloadImage(msg.media_url)"
                      class="text-[10px] text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center gap-1 cursor-pointer"
                    >
                      <Download class="w-3 h-3" />
                      <span>Descargar</span>
                    </button>
                  </div>
                  <audio 
                    controls 
                    class="w-full h-8 rounded-lg opacity-90 contrast-125"
                    :src="msg.media_url"
                    preload="metadata"
                  >
                    Tu navegador no soporta la reproducción directa de este audio.
                  </audio>
                </div>

                <!-- Si es documento o enlace genérico -->
                <div v-else class="flex items-center gap-2 p-2 rounded-xl bg-slate-100 dark:bg-slate-950/60 border border-slate-200 dark:border-white/10">
                  <Paperclip class="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0" />
                  <a 
                    :href="msg.media_url" 
                    target="_blank" 
                    class="underline font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-200 transition-colors text-[11px] truncate flex-1"
                  >
                    Abrir documento adjunto
                  </a>
                  <button 
                    type="button"
                    @click="downloadImage(msg.media_url)"
                    title="Descargar documento"
                    class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white p-1 cursor-pointer"
                  >
                    <Download class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
            </div>
          </template>
        </template>

        <!-- Botón flotante para regresar a mensajes recientes (Hoy) al scrollear hacia arriba -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-3 scale-90"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 translate-y-3 scale-90"
        >
          <button
            v-if="isScrolledUp"
            @click="scrollToBottom"
            class="sticky bottom-4 ml-auto mr-2 z-20 w-9 h-9 rounded-full bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 shadow-md border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-center transition-all cursor-pointer active:scale-95"
            title="Ir al último mensaje"
          >
            <ArrowDown class="w-4 h-4" />
          </button>
        </Transition>
      </div>
    </div>

      <!-- Caja de Texto para Enviar Mensaje & Adjuntar Imagen -->
      <footer class="p-2.5 sm:p-4 bg-white/95 dark:bg-slate-950/80 border-t border-slate-200 dark:border-slate-800/80 relative z-20 shrink-0 transition-colors duration-200">
        <!-- Vista Previa de Archivo Adjunto (Imagen, PDF o Audio) -->
        <div v-if="selectedImage" class="mb-3 px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-900/90 border border-sky-500/40 flex items-center justify-between animate-fade-in shadow-xs dark:shadow-lg">
          <div class="flex items-center gap-3">
            <div v-if="isAudioFile(selectedImage)" class="w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-500 dark:text-emerald-400 shrink-0">
              <Mic class="w-6 h-6" />
            </div>
            <div v-else-if="isPdfFile(selectedImage)" class="w-12 h-12 rounded-xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center text-rose-500 dark:text-rose-400 shrink-0">
              <FileText class="w-6 h-6" />
            </div>
            <img v-else :src="imagePreviewUrl" class="w-12 h-12 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow shrink-0" />
            
            <div class="overflow-hidden">
              <div class="text-xs font-bold flex items-center gap-1.5" :class="isAudioFile(selectedImage) ? 'text-emerald-600 dark:text-emerald-300' : (isPdfFile(selectedImage) ? 'text-rose-600 dark:text-rose-300' : 'text-sky-600 dark:text-sky-300')">
                <component :is="isAudioFile(selectedImage) ? Mic : (isPdfFile(selectedImage) ? FileText : Camera)" class="w-3.5 h-3.5" />
                <span>{{ isAudioFile(selectedImage) ? 'Audio listo para enviar' : (isPdfFile(selectedImage) ? 'Documento PDF listo' : 'Imagen lista') }}</span>
              </div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono truncate max-w-xs">{{ selectedImage.name }}</div>
            </div>
          </div>
          <button 
            type="button" 
            @click="removeSelectedImage" 
            class="px-2.5 py-1.5 rounded-lg text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 hover:bg-slate-200/80 dark:hover:bg-slate-800/80 transition-colors text-xs flex items-center gap-1 cursor-pointer"
            title="Quitar archivo adjunto"
          >
            <X class="w-3.5 h-3.5" />
            <span>Quitar</span>
          </button>
        </div>

        <form @submit.prevent="handleSendMessage" class="flex items-end gap-1.5 sm:gap-2.5">
          <!-- Input oculto de archivos -->
          <input 
            ref="fileInputRef" 
            type="file" 
            accept="image/*,application/pdf,audio/*" 
            class="hidden" 
            @change="handleFileChange" 
          />

          <!-- Botón de Adjuntar + Menú Desplegable de Opciones y Formularios -->
          <div class="relative" ref="attachMenuRef">
            <button
              type="button"
              @click="toggleAttachMenu"
              :class="[
                'p-2.5 sm:p-3 rounded-xl border transition-all cursor-pointer shrink-0 shadow-xs flex items-center justify-center',
                showAttachMenu 
                  ? 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border-sky-400 dark:border-sky-600 scale-95 shadow-sm' 
                  : 'bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:hover:text-sky-300 dark:border-slate-700'
              ]"
              title="Adjuntar archivo o enviar Formulario WhatsApp"
            >
              <Loader2 v-if="sendingFlow" class="w-4 h-4 animate-spin text-sky-500" />
              <Paperclip v-else class="w-4 h-4" />
            </button>

            <!-- Menú Flotante estilo WhatsApp (Despliega hacia arriba) -->
            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 translate-y-2 scale-95"
              enter-to-class="opacity-100 translate-y-0 scale-100"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100 translate-y-0 scale-100"
              leave-to-class="opacity-0 translate-y-2 scale-95"
            >
              <div 
                v-if="showAttachMenu" 
                class="absolute bottom-full mb-2 left-0 w-64 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl z-50 p-2 space-y-1 text-xs animate-in fade-in"
              >
                <div class="px-2.5 py-1 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                  Adjuntar y Formularios
                </div>

                <!-- Opción 1: Adjuntar Archivo -->
                <button
                  type="button"
                  @click="handleAttachFileClick"
                  class="w-full text-left px-2.5 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center gap-3 transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <Paperclip class="w-4 h-4" />
                  </div>
                  <div>
                    <div class="font-semibold text-slate-900 dark:text-white text-xs">Adjuntar archivo</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Fotos, PDF o audio (máx 16MB)</div>
                  </div>
                </button>

                <!-- Opción 2: Formulario de Pasajero -->
                <button
                  type="button"
                  @click="handleAttachFlowClick('passenger')"
                  :disabled="sendingFlow || chatStore.sendingMessage"
                  class="w-full text-left px-2.5 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center gap-3 transition-colors group cursor-pointer disabled:opacity-50"
                >
                  <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <ClipboardList class="w-4 h-4" />
                  </div>
                  <div>
                    <div class="font-semibold text-slate-900 dark:text-white text-xs">Datos de Pasajero</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Formulario oficial de TAMep</div>
                  </div>
                </button>

                <!-- Opción 3: Buscador de Vuelos -->
                <button
                  type="button"
                  @click="handleAttachFlowClick('flight_search')"
                  :disabled="sendingFlow || chatStore.sendingMessage"
                  class="w-full text-left px-2.5 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center gap-3 transition-colors group cursor-pointer disabled:opacity-50"
                >
                  <div class="w-8 h-8 rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <Plane class="w-4 h-4" />
                  </div>
                  <div>
                    <div class="font-semibold text-slate-900 dark:text-white text-xs">Buscar Vuelos</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Buscador interactivo TAMep</div>
                  </div>
                </button>
              </div>
            </Transition>
          </div>

          <div class="flex-1 relative">
            <textarea
              v-model="inputMessage"
              @keydown.enter.exact.prevent="handleSendMessage"
              rows="1"
              placeholder="Escribe un mensaje..."
              class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 sm:py-2.5 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500/30 resize-none transition-all font-medium"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="(!inputMessage.trim() && !selectedImage) || chatStore.sendingMessage"
            class="px-3.5 sm:px-5 py-2.5 sm:py-3 rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-40 disabled:hover:bg-sky-600 text-white font-bold text-xs transition-all shadow-lg shadow-sky-600/20 active:scale-95 flex items-center gap-1.5 shrink-0 cursor-pointer"
          >
            <span v-if="chatStore.sendingMessage" class="hidden sm:inline">Enviando...</span>
            <template v-else>
              <span class="hidden sm:inline">Enviar</span>
              <Send class="w-3.5 h-3.5" />
            </template>
          </button>
        </form>
      </footer>

      <!-- Modal para confirmar envío de Formulario -->
      <div 
        v-if="showFlowModal" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/75 backdrop-blur-sm p-4 animate-in fade-in"
      >
        <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 shadow-2xl space-y-4">
          <div class="flex items-center gap-3">
            <div 
              class="p-2.5 rounded-xl border flex items-center justify-center"
              :class="selectedFlowType === 'flight_search' ? 'bg-indigo-500/10 border-indigo-500/20 text-indigo-600 dark:text-indigo-400' : 'bg-sky-500/10 border-sky-500/20 text-sky-600 dark:text-sky-400'"
            >
              <component :is="selectedFlowType === 'flight_search' ? Plane : ClipboardList" class="w-5 h-5" />
            </div>
            <div>
              <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                {{ selectedFlowType === 'flight_search' ? 'Enviar Buscador de Vuelos' : 'Enviar Formulario de Pasajero' }}
              </h4>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">WhatsApp Formulario Oficial TAMep</p>
            </div>
          </div>

          <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
            {{ selectedFlowType === 'flight_search' 
              ? `Se enviará al WhatsApp del cliente (+${chatStore.activeConversation?.phone}) el buscador interactivo para seleccionar origen, destino, fecha y cantidad de pasajeros.`
              : `Se enviará al WhatsApp del cliente (+${chatStore.activeConversation?.phone}) el formulario oficial adaptado exactamente para ${selectedPaxCount} pasajero(s).`
            }}
          </p>

          <!-- Selector de cantidad de pasajeros (1 a 5) -->
          <div v-if="selectedFlowType !== 'flight_search'" class="space-y-1.5 pt-1">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300">Cantidad de Pasajeros:</span>
              <span class="text-[10px] text-sky-600 dark:text-sky-400 font-bold">{{ selectedPaxCount }} {{ selectedPaxCount === 1 ? 'Pasajero' : 'Pasajeros' }}</span>
            </div>
            <div class="grid grid-cols-5 gap-1.5">
              <button
                v-for="p in [1, 2, 3, 4, 5]"
                :key="p"
                type="button"
                @click="selectedPaxCount = p"
                class="py-1.5 text-xs font-bold rounded-xl border transition-all cursor-pointer flex flex-col items-center justify-center gap-0.5"
                :class="selectedPaxCount === p 
                  ? 'bg-sky-500 text-white border-sky-500 shadow-md shadow-sky-500/20' 
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700'"
              >
                <span>{{ p }}</span>
                <span class="text-[9px] font-normal opacity-80">PAX</span>
              </button>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-2">
            <button
              type="button"
              @click="showFlowModal = false"
              class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 text-xs font-semibold cursor-pointer transition-colors"
            >
              Cancelar
            </button>
            <button
              type="button"
              @click="confirmSendFlow"
              :disabled="sendingFlow"
              class="px-4 py-1.5 rounded-xl text-white text-xs font-bold shadow-lg cursor-pointer transition-all flex items-center gap-1.5"
              :class="selectedFlowType === 'flight_search' ? 'bg-indigo-600 hover:bg-indigo-500 shadow-indigo-600/30' : 'bg-sky-600 hover:bg-sky-500 shadow-sky-600/30'"
            >
              <Loader2 v-if="sendingFlow" class="w-3.5 h-3.5 animate-spin" />
              <span>Enviar Formulario</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Toast flotante de retroalimentación -->
      <div 
        v-if="toastMessage" 
        class="fixed bottom-20 left-1/2 -translate-x-1/2 sm:translate-x-0 sm:left-auto sm:right-6 z-50 flex items-center gap-2 px-4 py-2.5 rounded-xl shadow-xl text-xs font-semibold animate-in fade-in"
        :class="toastType === 'error' ? 'bg-rose-950/90 border border-rose-500/40 text-rose-200' : 'bg-emerald-950/90 border border-emerald-500/40 text-emerald-200'"
      >
        <span>{{ toastMessage }}</span>
      </div>

      <!-- Modal Lightbox para Previsualizar Imagen -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div 
            v-if="imageModalOpen" 
            class="fixed inset-0 z-[100] flex flex-col bg-slate-950/92 backdrop-blur-md select-none"
            @click.self="closeImageModal"
          >
            <!-- Barra Superior del Modal -->
            <div class="px-4 py-3 bg-slate-900/90 border-b border-slate-800 flex items-center justify-between text-white z-10 shrink-0">
              <div class="flex items-center gap-3 min-w-0">
                <div class="p-2 rounded-xl bg-sky-500/10 border border-sky-400/20 text-sky-400 shrink-0">
                  <ImageIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                  <h4 class="text-xs sm:text-sm font-semibold truncate text-slate-100">
                    {{ modalImageCaption || 'Vista previa de imagen' }}
                  </h4>
                  <p v-if="modalImageInfo" class="text-[11px] text-slate-400 truncate">
                    {{ modalImageInfo }}
                  </p>
                </div>
              </div>

              <!-- Botones de Acción -->
              <div class="flex items-center gap-1 sm:gap-2">
                <button 
                  type="button"
                  @click="zoomIn"
                  class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors cursor-pointer"
                  title="Acercar (+)"
                >
                  <ZoomIn class="w-4 h-4" />
                </button>
                <button 
                  type="button"
                  @click="zoomOut"
                  class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors cursor-pointer"
                  title="Alejar (-)"
                >
                  <ZoomOut class="w-4 h-4" />
                </button>
                <button 
                  type="button"
                  @click="rotateImage"
                  class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors cursor-pointer"
                  title="Girar 90°"
                >
                  <RotateCw class="w-4 h-4" />
                </button>
                <button 
                  v-if="zoomLevel !== 1 || rotation !== 0"
                  type="button"
                  @click="resetZoom"
                  class="px-2.5 py-1 rounded-xl bg-slate-800 hover:bg-slate-700 text-sky-400 text-xs font-semibold transition-colors cursor-pointer"
                  title="Restablecer vista"
                >
                  {{ Math.round(zoomLevel * 100) }}%
                </button>
                <button 
                  type="button"
                  @click="downloadImage(modalImageUrl)"
                  class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors cursor-pointer"
                  title="Descargar imagen"
                >
                  <Download class="w-4 h-4" />
                </button>
                <a 
                  :href="modalImageUrl" 
                  target="_blank" 
                  class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors cursor-pointer"
                  title="Abrir original en nueva pestaña"
                >
                  <ExternalLink class="w-4 h-4" />
                </a>
                <div class="h-5 w-px bg-slate-800 mx-1"></div>
                <button 
                  type="button"
                  @click="closeImageModal"
                  class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-colors cursor-pointer border border-rose-500/20"
                  title="Cerrar (Esc)"
                >
                  <X class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Contenedor Central de la Imagen -->
            <div 
              class="flex-1 overflow-auto flex items-center justify-center p-4 sm:p-8 cursor-zoom-out"
              @click.self="closeImageModal"
            >
              <div 
                class="relative max-h-full max-w-full flex items-center justify-center transition-transform duration-200 cursor-default"
                :style="{
                  transform: `scale(${zoomLevel}) rotate(${rotation}deg)`
                }"
              >
                <img 
                  :src="modalImageUrl" 
                  :alt="modalImageCaption || 'Vista previa'"
                  class="max-h-[82vh] max-w-[90vw] object-contain rounded-xl shadow-2xl select-none"
                />
              </div>
            </div>

            <!-- Pie del Modal con Indicador si hay Caption -->
            <div v-if="modalImageCaption" class="px-6 py-2.5 bg-slate-900/90 border-t border-slate-800 text-center text-xs text-slate-300 z-10 shrink-0">
              {{ modalImageCaption }}
            </div>
          </div>
        </Transition>
      </Teleport>
    </template>
  </section>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { 
  MessageSquare, 
  UserCheck, 
  ArrowLeftRight, 
  Bot, 
  User, 
  AlertTriangle, 
  Camera, 
  Send, 
  X, 
  ExternalLink, 
  Paperclip, 
  FileText,
  Mic,
  Download,
  ArrowRight,
  Sparkles,
  ArrowLeft,
  ClipboardList,
  Loader2,
  Plane,
  ZoomIn,
  ZoomOut,
  RotateCw,
  Maximize2,
  MousePointerClick,
  Image as ImageIcon,
  ArrowDown,
  Calendar,
  MoreVertical
} from 'lucide-vue-next';
import { useChatStore } from '@/stores/useChatStore';
import api from '@/api/client';
import { isInteractiveButtonReply, getInteractiveButtonDetails } from '@/services/messageFormatter';

const emit = defineEmits(['open-booking', 'back']);
const chatStore = useChatStore();
const inputMessage = ref('');
const messagesContainer = ref(null);
const sendingFlow = ref(false);
const currentSendingType = ref(null);
const showFlowModal = ref(false);
const selectedFlowType = ref('passenger');
const selectedPaxCount = ref(1);
const isScrolledUp = ref(false);

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

function triggerSendFlow(flowType) {
  if (!chatStore.activeConversation) return;
  selectedFlowType.value = flowType;
  if (flowType !== 'flight_search') {
    const rawPax = Number(
      chatStore.activeConversation?.latest_lead?.passengers_count ||
      chatStore.activeConversation?.session_data?.passengers ||
      chatStore.activeConversation?.session_data?.passengers_count ||
      1
    );
    selectedPaxCount.value = Math.min(Math.max(rawPax, 1), 5);
  }
  showFlowModal.value = true;
}

async function confirmSendFlow() {
  showFlowModal.value = false;
  sendingFlow.value = true;
  const targetType = selectedFlowType.value === 'flight_search' 
    ? 'flight_search' 
    : `passenger_${selectedPaxCount.value}`;
  currentSendingType.value = targetType;
  try {
    const res = await chatStore.sendFlow(targetType);
    if (res.success) {
      showToast(
        selectedFlowType.value === 'flight_search' 
          ? 'Buscador de Vuelos enviado al cliente' 
          : `Formulario (${selectedPaxCount.value} PAX) enviado al cliente`
      );
      scrollToBottom();
    } else {
      showToast(res.message || 'Error al enviar Formulario', 'error');
    }
  } catch (err) {
    showToast('Error al enviar WhatsApp Formulario', 'error');
  } finally {
    sendingFlow.value = false;
    currentSendingType.value = null;
  }
}

// Navegación por fecha
const dateDropdownRef = ref(null);
const showDateDropdown = ref(false);
const selectedDropdownDate = ref('');
const activeHighlightedDate = ref(null);

function toggleDateDropdown() {
  showDateDropdown.value = !showDateDropdown.value;
  if (showDateDropdown.value) {
    showTransferDropdown.value = false;
    showMobileMenu.value = false;
  }
}

// Menú Móvil (3 puntos ⋮)
const mobileMenuRef = ref(null);
const showMobileMenu = ref(false);

function toggleMobileMenu() {
  showMobileMenu.value = !showMobileMenu.value;
  if (showMobileMenu.value) {
    showDateDropdown.value = false;
  }
}

function openMobileDateNav() {
  showMobileMenu.value = false;
  showDateDropdown.value = true;
}

function triggerReleaseToBotMobile() {
  showMobileMenu.value = false;
  confirmReleaseToBot();
}

function openBookingFromMobileMenu() {
  showMobileMenu.value = false;
  emit('open-booking');
}

const showTransferDropdown = ref(false);
const availableAgents = ref([]);
const loadingAgents = ref(false);

async function toggleTransferDropdown() {
  showTransferDropdown.value = !showTransferDropdown.value;
  if (showTransferDropdown.value) {
    showDateDropdown.value = false;
    loadingAgents.value = true;
    try {
      const res = await api.get('/agents');
      if (res.data?.agents) {
        availableAgents.value = res.data.agents.filter(
          a => a.is_active && a.id !== chatStore.activeConversation?.assigned_user_id
        );
      }
    } catch (err) {
      console.error('Error fetching agents:', err);
    } finally {
      loadingAgents.value = false;
    }
  }
}

async function transferToAgent(agent) {
  showTransferDropdown.value = false;
  if (confirm(`¿Confirmas transferir la atención de este chat a ${agent.name}?`)) {
    const success = await chatStore.assignConversation(agent.id);
    if (success) {
      scrollToBottom();
    }
  }
}

const fileInputRef = ref(null);
const selectedImage = ref(null);
const imagePreviewUrl = ref('');

const attachMenuRef = ref(null);
const showAttachMenu = ref(false);

function toggleAttachMenu() {
  showAttachMenu.value = !showAttachMenu.value;
}

function handleAttachFileClick() {
  showAttachMenu.value = false;
  triggerFileInput();
}

function handleAttachFlowClick(flowType) {
  showAttachMenu.value = false;
  triggerSendFlow(flowType);
}

function triggerFileInput() {
  if (fileInputRef.value) {
    fileInputRef.value.click();
  }
}

function handleFileChange(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  selectedImage.value = file;
  imagePreviewUrl.value = URL.createObjectURL(file);
}

function removeSelectedImage() {
  selectedImage.value = null;
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
    imagePreviewUrl.value = '';
  }
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
}

function isImageUrl(url) {
  if (!url) return false;
  return /\.(jpeg|jpg|png|webp|gif)($|\?)/i.test(url);
}

function isAudioUrl(url) {
  if (!url) return false;
  return /\.(ogg|mp3|wav|m4a|aac)($|\?)/i.test(url);
}

function isPdfFile(file) {
  if (!file) return false;
  return file.type === 'application/pdf' || file.name?.toLowerCase().endsWith('.pdf');
}

function isAudioFile(file) {
  if (!file) return false;
  return file.type?.startsWith('audio/') || /\.(ogg|mp3|wav|m4a|aac)$/i.test(file.name);
}

function parseInteractiveButtons(text) {
  if (!text) return [];
  if (text.includes('ITINERARIOS DISPONIBLES')) {
    const matches = [...text.matchAll(/Vuelo\s+(TM-[\w\d]+)[^\n]*?(?:Salida:\s*(\d{2}:\d{2})|\((\d{2}:\d{2}))/gi)];
    if (matches.length > 0) {
      return matches.map(m => `${m[1]} (${m[2] || m[3]})`);
    }
  }
  if (text.includes('¿En qué podemos ayudarte hoy?') || text.includes('¡Bienvenido a TAMep!')) {
    return ['Pasajes y Vuelos', 'Carga', 'Asesor de Ventas'];
  }
  return [];
}



async function handleSendMessage() {
  const text = inputMessage.value.trim();
  const file = selectedImage.value;

  if (!text && !file) return;

  const payload = new FormData();
  if (text) payload.append('message', text);
  if (file) payload.append('image', file);

  inputMessage.value = '';
  removeSelectedImage();

  const success = await chatStore.sendMessage(payload);
  if (success) {
    scrollToBottom();
  }
}

async function confirmReleaseToBot() {
  if (confirm("¿Deseas cerrar la atención humana y reactivar el bot para este número?")) {
    await chatStore.releaseToBot();
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
      isScrolledUp.value = false;
    }
  });
}

watch(() => chatStore.messages.length, (newLen, oldLen) => {
  if (!isScrolledUp.value || !oldLen) {
    scrollToBottom();
  }
});

const isScrolling = ref(false);
let scrollStopTimer = null;

function handleScroll(e) {
  const el = e.target;
  if (!el) return;
  const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
  isScrolledUp.value = distanceFromBottom > 280;
  
  if (isScrolledUp.value) {
    isScrolling.value = true;
    updateFloatingDate();

    if (scrollStopTimer) {
      clearTimeout(scrollStopTimer);
    }
    scrollStopTimer = setTimeout(() => {
      isScrolling.value = false;
    }, 1400);
  } else {
    isScrolling.value = false;
  }
}

// ==========================================
// Formateo y Gestión de Fechas estilo WhatsApp
// ==========================================
function parseMessageDate(timestamp) {
  if (!timestamp) return new Date();
  if (typeof timestamp === 'string' && timestamp.includes(' ') && !timestamp.includes('T')) {
    return new Date(timestamp.replace(' ', 'T'));
  }
  return new Date(timestamp);
}

function getDateKey(timestamp) {
  const d = parseMessageDate(timestamp);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatDividerDate(timestamp) {
  if (!timestamp) return '';
  const date = parseMessageDate(timestamp);
  const now = new Date();
  
  const dateMidnight = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  const todayMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
  const oneDayMs = 24 * 60 * 60 * 1000;
  const diffDays = Math.round((todayMidnight - dateMidnight) / oneDayMs);

  if (diffDays === 0) {
    return 'Hoy';
  } else if (diffDays === 1) {
    return 'Ayer';
  } else if (diffDays > 1 && diffDays < 7) {
    const weekday = date.toLocaleDateString('es-ES', { weekday: 'long' });
    const dayAndMonth = date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
    const capitalized = weekday.charAt(0).toUpperCase() + weekday.slice(1);
    return `${capitalized}, ${dayAndMonth}`;
  } else {
    const options = {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      ...(date.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {})
    };
    const formatted = date.toLocaleDateString('es-ES', options);
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
  }
}

function formatFullDateTimeTooltip(timestamp) {
  if (!timestamp) return '';
  const date = parseMessageDate(timestamp);
  return date.toLocaleString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatTime(timestamp) {
  if (!timestamp) return '';
  const date = parseMessageDate(timestamp);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function isFirstMessageOfDay(msg, index) {
  if (index === 0) return true;
  const prevMsg = chatStore.messages[index - 1];
  return getDateKey(msg.created_at) !== getDateKey(prevMsg.created_at);
}

// Cabecera de fecha flotante única que detecta el día visible al hacer scroll
const floatingDateLabel = ref('');

function updateFloatingDate() {
  if (!messagesContainer.value || !chatStore.messages || chatStore.messages.length === 0) {
    floatingDateLabel.value = '';
    return;
  }

  const container = messagesContainer.value;
  const dividers = container.querySelectorAll('[id^="date-divider-"]');
  if (dividers.length === 0) {
    floatingDateLabel.value = '';
    return;
  }

  let activeDivider = dividers[0];
  const threshold = container.scrollTop + 60;

  for (let i = 0; i < dividers.length; i++) {
    if (dividers[i].offsetTop <= threshold) {
      activeDivider = dividers[i];
    } else {
      break;
    }
  }

  if (activeDivider && activeDivider.id) {
    const key = activeDivider.id.replace('date-divider-', '');
    floatingDateLabel.value = formatDividerDate(key);
  }
}

// Limpieza de estado al cambiar de conversación para evitar saturación
watch(() => chatStore.activeConversation?.id, () => {
  showDateDropdown.value = false;
  showTransferDropdown.value = false;
  showAttachMenu.value = false;
  showMobileMenu.value = false;
  selectedDropdownDate.value = '';
  activeHighlightedDate.value = null;
  floatingDateLabel.value = '';
  isScrolledUp.value = false;
  isScrolling.value = false;
  if (scrollStopTimer) clearTimeout(scrollStopTimer);
});

const availableChatDates = computed(() => {
  if (!chatStore.messages || chatStore.messages.length === 0) return [];
  const map = new Map();
  for (const msg of chatStore.messages) {
    if (!msg.created_at) continue;
    const key = getDateKey(msg.created_at);
    if (!map.has(key)) {
      map.set(key, {
        key,
        label: formatDividerDate(msg.created_at),
        count: 1
      });
    } else {
      map.get(key).count++;
    }
  }
  return Array.from(map.values()).reverse();
});

const earliestChatDateKey = computed(() => {
  if (!chatStore.messages || chatStore.messages.length === 0) return '';
  return getDateKey(chatStore.messages[0].created_at);
});

const latestChatDateKey = computed(() => {
  if (!chatStore.messages || chatStore.messages.length === 0) return '';
  return getDateKey(chatStore.messages[chatStore.messages.length - 1].created_at);
});

const yesterdayChatDateKey = computed(() => {
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  const key = `${yesterday.getFullYear()}-${String(yesterday.getMonth() + 1).padStart(2, '0')}-${String(yesterday.getDate()).padStart(2, '0')}`;
  const found = availableChatDates.value.some(d => d.key === key);
  return found ? key : null;
});

function jumpToDate(dateKey) {
  if (!dateKey) return;
  showDateDropdown.value = false;
  selectedDropdownDate.value = '';

  let dividerEl = document.getElementById(`date-divider-${dateKey}`);

  // Si no hay mensaje exactamente en esa fecha (por ejemplo elegida en calendario), buscar el mensaje más próximo
  if (!dividerEl && chatStore.messages && chatStore.messages.length > 0) {
    const targetTimestamp = new Date(dateKey + 'T00:00:00').getTime();
    let bestDist = Infinity;
    let bestKey = null;

    for (const msg of chatStore.messages) {
      if (!msg.created_at) continue;
      const mKey = getDateKey(msg.created_at);
      const mTime = new Date(mKey + 'T00:00:00').getTime();
      const dist = Math.abs(mTime - targetTimestamp);
      if (dist < bestDist) {
        bestDist = dist;
        bestKey = mKey;
      }
    }

    if (bestKey) {
      dividerEl = document.getElementById(`date-divider-${bestKey}`);
      dateKey = bestKey;
      showToast(`Saltando a fecha más cercana: ${dateKey}`, 'info');
    }
  }

  if (dividerEl) {
    dividerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    activeHighlightedDate.value = dateKey;
    setTimeout(() => {
      if (activeHighlightedDate.value === dateKey) {
        activeHighlightedDate.value = null;
      }
    }, 2800);
  } else {
    showToast('No se encontraron mensajes para esta fecha', 'warning');
  }
}

function onSelectDropdownDate(event) {
  const val = event.target.value;
  if (val) {
    jumpToDate(val);
  }
}

function handleDocumentClick(e) {
  if (dateDropdownRef.value && !dateDropdownRef.value.contains(e.target)) {
    showDateDropdown.value = false;
  }
  if (attachMenuRef.value && !attachMenuRef.value.contains(e.target)) {
    showAttachMenu.value = false;
  }
  if (mobileMenuRef.value && !mobileMenuRef.value.contains(e.target)) {
    showMobileMenu.value = false;
  }
}

// ==========================================
// Modal Lightbox para Previsualización de Imágenes
// ==========================================
const imageModalOpen = ref(false);
const modalImageUrl = ref('');
const modalImageCaption = ref('');
const modalImageInfo = ref('');
const zoomLevel = ref(1);
const rotation = ref(0);

function hasMeaningfulText(msg) {
  if (!msg || !msg.message) return false;
  const trimmed = msg.message.trim();
  const placeholders = [
    '📷 Imagen', 'Imagen', 'image', 'Image', '📷', 
    '📎 Documento', 'Documento', 'document', 
    '🎤 Audio', 'Audio', 'audio'
  ];
  return !placeholders.includes(trimmed);
}

function openImageModal(url, msg = null) {
  if (!url) return;
  modalImageUrl.value = url;
  zoomLevel.value = 1;
  rotation.value = 0;
  
  if (msg) {
    modalImageCaption.value = hasMeaningfulText(msg) ? msg.message : '';
    const senderName = msg.sender === 'agent' 
      ? (msg.agent?.name || 'Agente') 
      : (msg.sender === 'bot' ? 'TAMep Bot' : (chatStore.activeConversation?.contact_name || 'Pasajero'));
    const time = formatTime(msg.created_at);
    modalImageInfo.value = `Enviado por ${senderName}${time ? ' • ' + time : ''}`;
  } else {
    modalImageCaption.value = '';
    modalImageInfo.value = '';
  }

  imageModalOpen.value = true;
  document.body.style.overflow = 'hidden';
}

function closeImageModal() {
  imageModalOpen.value = false;
  modalImageUrl.value = '';
  zoomLevel.value = 1;
  rotation.value = 0;
  document.body.style.overflow = '';
}

function zoomIn() {
  zoomLevel.value = Math.min(Number((zoomLevel.value + 0.25).toFixed(2)), 3);
}

function zoomOut() {
  zoomLevel.value = Math.max(Number((zoomLevel.value - 0.25).toFixed(2)), 0.5);
}

function resetZoom() {
  zoomLevel.value = 1;
  rotation.value = 0;
}

function rotateImage() {
  rotation.value = (rotation.value + 90) % 360;
}

async function downloadImage(url) {
  if (!url) return;
  try {
    const response = await fetch(url);
    const blob = await response.blob();
    const blobUrl = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = blobUrl;
    const parts = url.split('/');
    const name = parts[parts.length - 1].split('?')[0] || 'archivo';
    a.download = name;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);
  } catch (err) {
    const a = document.createElement('a');
    a.href = url;
    a.download = url.split('/').pop() || 'archivo';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }
}

function handleKeyDown(e) {
  if (!imageModalOpen.value) return;
  if (e.key === 'Escape') {
    closeImageModal();
  } else if (e.key === '+' || e.key === '=') {
    zoomIn();
  } else if (e.key === '-' || e.key === '_') {
    zoomOut();
  } else if (e.key === '0') {
    resetZoom();
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
  document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  document.removeEventListener('click', handleDocumentClick);
  document.body.style.overflow = '';
  if (scrollStopTimer) clearTimeout(scrollStopTimer);
});
</script>
