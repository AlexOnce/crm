<template>
  <div class="h-screen w-screen flex flex-col bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 overflow-hidden font-sans transition-colors duration-200">
    
    <!-- Navbar Superior Gerencial -->
    <header class="h-14 bg-white/95 dark:bg-slate-950/90 border-b border-slate-200 dark:border-slate-800/80 px-3 sm:px-6 flex items-center justify-between shrink-0 z-20 backdrop-blur-md transition-colors duration-200 shadow-xs dark:shadow-none">
      <!-- Marca & Modo Gerencial -->
      <div class="flex items-center gap-2.5 sm:gap-3">
        <div class="w-8 h-8 rounded-lg bg-white/10 dark:bg-white/10 border border-slate-200 dark:border-white/20 flex items-center justify-center overflow-hidden shrink-0 shadow-xs ring-1 ring-slate-200/50 dark:ring-white/10">
          <img :src="logoImg" alt="Logo TAMep" class="w-full h-full object-contain p-0.5 rounded-lg" />
        </div>
        <h1 class="font-extrabold text-xs sm:text-sm tracking-wide text-slate-900 dark:text-white">WhatsApp</h1>
      </div>

      <!-- Acciones de Cabecera: Tema + PWA + Notificaciones + Cambiar a Vista Agente + Perfil -->
      <!-- Perfil del Administrador & Salida Rápida -->
      <div class="flex items-center gap-2">
        <!-- Perfil Interactivo del Administrador: Al hacer clic abre perfil, tema, notificaciones y seguridad -->
        <button 
          @click="showProfileModal = true"
          class="flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 border border-slate-200/80 dark:border-slate-800 transition-all cursor-pointer group text-left shadow-2xs"
          title="Ver mi perfil, tema, alertas y seguridad"
        >
          <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-gradient-to-tr from-purple-500/20 to-sky-500/20 text-purple-600 dark:text-purple-400 font-bold flex items-center justify-center text-xs border border-purple-500/30 shrink-0 group-hover:border-purple-500/60 transition-all shadow-xs">
            {{ adminInitials }}
          </div>
          <div class="hidden sm:block text-right pr-0.5">
            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors flex items-center justify-end gap-1">
              <span>{{ authStore.user?.name || 'Administrador' }}</span>
              <ChevronDown class="w-3 h-3 text-slate-400 group-hover:text-sky-500 transition-transform group-hover:translate-y-0.5" />
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ authStore.user?.email }}</div>
          </div>
        </button>

        <button 
          @click="handleLogout"
          class="p-2 rounded-xl bg-white hover:bg-red-50 border border-slate-200 hover:border-red-300 text-slate-500 hover:text-red-600 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400 dark:hover:text-red-400 dark:hover:bg-red-500/10 dark:hover:border-red-500/30 transition-colors text-xs cursor-pointer shadow-xs dark:shadow-none"
          title="Cerrar sesión"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- Barra de Pestañas Principales del Administrador -->
    <nav class="bg-white/95 dark:bg-slate-900/90 border-b border-slate-200 dark:border-slate-800/80 px-3 sm:px-6 flex items-center justify-between shrink-0 gap-2 transition-colors duration-200 shadow-xs dark:shadow-none">
      <div class="flex gap-1.5 sm:gap-4 overflow-x-auto no-scrollbar py-2">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'px-2.5 sm:px-3.5 py-1.5 sm:py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 sm:gap-2 whitespace-nowrap cursor-pointer shrink-0',
            activeTab === tab.id 
              ? 'bg-sky-500/15 dark:bg-sky-500/10 border border-sky-500/30 text-sky-700 dark:text-sky-400 shadow-xs' 
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/50'
          ]"
        >
          <component :is="tab.icon" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
          <span>{{ tab.label }}</span>
          <span v-if="tab.badge !== undefined" class="px-1.5 py-0.2 text-[9px] sm:text-[10px] rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono">
            {{ tab.badge }}
          </span>
        </button>
      </div>

      <button 
        @click="refreshCurrentTab" 
        :disabled="loading"
        class="px-2.5 sm:px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-transparent text-xs flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-xs"
        title="Actualizar datos"
      >
        <RefreshCw class="w-3.5 h-3.5" :class="{'animate-spin': loading}" />
        <span class="hidden sm:inline text-[11px]">Actualizar</span>
      </button>
    </nav>

    <!-- Contenedor Principal con Scroll -->
    <main class="flex-1 overflow-y-auto p-3 sm:p-6 bg-slate-100 dark:bg-gradient-to-b dark:from-slate-950 dark:to-slate-900 transition-colors duration-200">
      <div class="max-w-7xl mx-auto space-y-6">

        <!-- ========================================================
             PESTAÑA 1: RESUMEN EJECUTIVO & KPIS
             ======================================================== -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          <!-- Tarjetas Principales de KPIs -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            
            <!-- KPI: Boletos Emitidos -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800/80 shadow-xs dark:shadow-xl relative overflow-hidden group hover:border-sky-500/40 transition-all">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Boletos Emitidos</span>
                <div class="p-2 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                  <Ticket class="w-5 h-5" />
                </div>
              </div>
              <div class="mt-3">
                <div class="text-2xl font-black text-slate-900 dark:text-white font-mono tracking-tight">{{ kpis.total_tickets }}</div>
                <div class="mt-1 text-[11px] text-sky-600 dark:text-sky-400 flex items-center gap-1 font-medium">
                  <span>{{ kpis.tickets_today }} emitidos en la jornada actual</span>
                </div>
              </div>
            </div>

            <!-- KPI: Leads & Conversión -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800/80 shadow-xs dark:shadow-xl relative overflow-hidden group hover:border-purple-500/40 transition-all">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tasa de Conversión</span>
                <div class="p-2 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                  <TrendingUp class="w-5 h-5" />
                </div>
              </div>
              <div class="mt-3">
                <div class="text-2xl font-black text-slate-900 dark:text-white font-mono tracking-tight">{{ kpis.conversion_rate }}%</div>
                <div class="mt-1 text-[11px] text-purple-700 dark:text-purple-300 font-medium">
                  <span>{{ kpis.total_tickets }} compras de {{ kpis.total_leads }} consultas</span>
                </div>
              </div>
            </div>

            <!-- KPI: Estado de Conversaciones -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800/80 shadow-xs dark:shadow-xl relative overflow-hidden group hover:border-amber-500/40 transition-all">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Conversaciones</span>
                <div class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                  <MessageSquare class="w-5 h-5" />
                </div>
              </div>
              <div class="mt-3">
                <div class="text-2xl font-black text-slate-900 dark:text-white font-mono tracking-tight">{{ conversationsStats.total }}</div>
                <div class="mt-1 text-[11px] text-amber-700 dark:text-amber-300 flex items-center gap-2 font-medium">
                  <span class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ conversationsStats.waiting_agent }} en espera
                  </span>
                  <span>•</span>
                  <span>{{ conversationsStats.human_live }} en vivo</span>
                </div>
              </div>
            </div>

          </div>

          <!-- Dos Columnas: Ranking de Asesores + Últimos Boletos -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Ranking de Desempeño de Asesores -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 shadow-xs dark:shadow-xl">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Award class="w-4 h-4 text-amber-500 dark:text-amber-400" />
                    <span>Rendimiento del Equipo de Asesores</span>
                  </h3>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">Boletos vendidos y conversaciones atendidas por agente</p>
                </div>
                <button @click="activeTab = 'agents'" class="text-xs text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 font-semibold flex items-center gap-1 cursor-pointer">
                  <span>Gestionar Equipo</span>
                  <ArrowRight class="w-3.5 h-3.5" />
                </button>
              </div>

              <div class="space-y-3">
                <div 
                  v-for="agent in agentsList" 
                  :key="agent.id"
                  class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-700 flex items-center justify-center text-xs font-black text-white">
                      {{ agent.name.substring(0, 2).toUpperCase() }}
                    </div>
                    <div>
                      <div class="text-xs font-bold text-slate-900 dark:text-slate-200 flex items-center gap-2">
                        <span>{{ agent.name }}</span>
                        <span v-if="agent.role === 'admin'" class="px-1.5 py-0.2 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 text-[9px] font-bold">
                          Admin
                        </span>
                      </div>
                      <div class="text-[10px] text-slate-500 font-mono">{{ agent.email }}</div>
                    </div>
                  </div>

                  <div class="flex items-center gap-4 text-right">
                    <div>
                      <div class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">{{ agent.tickets_count || 0 }}</div>
                      <div class="text-[10px] text-slate-500">Boletos</div>
                    </div>
                    <div class="pl-3 border-l border-slate-200 dark:border-slate-800">
                      <div class="text-xs font-extrabold text-sky-600 dark:text-sky-400 font-mono">{{ agent.assigned_conversations_count || 0 }}</div>
                      <div class="text-[10px] text-slate-500">Chats</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Últimos Boletos Emitidos -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 shadow-xs dark:shadow-xl">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Zap class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                    <span>Últimas Emisiones en Tiempo Real</span>
                  </h3>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">Pasajes confirmados con PNR oficial</p>
                </div>
                <button @click="activeTab = 'tickets'" class="text-xs text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 font-semibold flex items-center gap-1 cursor-pointer">
                  <span>Ver Todos</span>
                  <ArrowRight class="w-3.5 h-3.5" />
                </button>
              </div>

              <div class="space-y-3">
                <div v-if="recentTickets.length === 0" class="text-center py-8 text-slate-400 dark:text-slate-500 text-xs">
                  No hay boletos emitidos recientemente.
                </div>
                <div 
                  v-for="ticket in recentTickets" 
                  :key="ticket.id"
                  class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all"
                >
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="px-2 py-0.5 rounded bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30 text-[10px] font-mono font-bold">
                        {{ ticket.booking_reference }}
                      </span>
                      <span class="text-xs font-bold text-slate-900 dark:text-white">{{ ticket.passenger_name }}</span>
                      <span v-if="ticket.passengers_count && ticket.passengers_count > 1" class="px-1.5 py-0.2 rounded-md bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 text-[10px] font-bold font-mono">
                        {{ ticket.passengers_count }} PAX
                      </span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1">
                      <span>Ruta:</span>
                      <span class="text-slate-700 dark:text-slate-300 font-medium flex items-center gap-1">{{ ticket.origin }} <ArrowRight class="w-2.5 h-2.5 text-sky-500 dark:text-sky-400" /> {{ ticket.destination }}</span>
                      <span>(Vuelo {{ ticket.flight_number }})</span>
                    </div>
                  </div>

                  <div class="text-right">
                    <div class="text-xs font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ formatCurrency(ticket.total_price) }}</div>
                    <a 
                      v-if="ticket.ticket_pdf_path" 
                      :href="ticket.ticket_pdf_path" 
                      target="_blank"
                      class="text-[10px] text-sky-600 dark:text-sky-400 hover:underline flex items-center justify-end gap-1 mt-0.5"
                    >
                      <span>Ver Billete</span>
                      <ExternalLink class="w-3 h-3" />
                    </a>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- ========================================================
             PESTAÑA 2: GESTIÓN DE PASAJEROS Y AUDITORÍA DE BOLETOS
             ======================================================== -->
        <div v-if="activeTab === 'tickets'" class="space-y-4">
          <!-- Encabezado con Botón de Alta Rápida de Pasajero -->
          <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs dark:shadow-none">
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Users class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                <span>Registro y Gestión de Pasajeros</span>
              </h3>
              <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Supervisa todos los pasajeros recopilados por WhatsApp, asigna códigos PNR o registra manualmente nuevas solicitudes.
              </p>
            </div>
            
            <button 
              @click="openNewPassengerModal"
              class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-sky-500/20 flex items-center justify-center gap-2 cursor-pointer shrink-0"
            >
              <UserPlus class="w-4 h-4" />
              <span>+ Registrar Pasajero</span>
            </button>
          </div>

          <!-- Barra de Filtros por Estado y Búsqueda -->
          <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs dark:shadow-none">
            <!-- Pestañas de Estado (Filtros rápidos) -->
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
              <button 
                @click="setTicketStatusFilter('all')"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                  ticketStatusFilter === 'all' 
                    ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-950 shadow-xs' 
                    : 'bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                ]"
              >
                <span>Todos</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono" :class="ticketStatusFilter === 'all' ? 'bg-white/20 dark:bg-black/20 text-white dark:text-slate-950' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'">
                  {{ ticketCounts.total }}
                </span>
              </button>

              <button 
                @click="setTicketStatusFilter('nuevo')"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                  ticketStatusFilter === 'nuevo' 
                    ? 'bg-amber-500 text-white shadow-xs shadow-amber-500/30' 
                    : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 hover:bg-amber-500/20 border border-amber-500/20'
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Pendientes / Nuevos</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-amber-500/20 text-amber-900 dark:text-amber-200">
                  {{ ticketCounts.nuevo }}
                </span>
              </button>

              <button 
                @click="setTicketStatusFilter('contactado')"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                  ticketStatusFilter === 'contactado' 
                    ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/30' 
                    : 'bg-sky-500/10 text-sky-700 dark:text-sky-400 hover:bg-sky-500/20 border border-sky-500/20'
                ]"
              >
                <span>En Proceso</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-sky-500/20 text-sky-900 dark:text-sky-200">
                  {{ ticketCounts.contactado }}
                </span>
              </button>

              <button 
                @click="setTicketStatusFilter('emitido')"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
                  ticketStatusFilter === 'emitido' 
                    ? 'bg-emerald-600 text-white shadow-xs shadow-emerald-600/30' 
                    : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20'
                ]"
              >
                <span>Emitidos</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-emerald-500/20 text-emerald-900 dark:text-emerald-200">
                  {{ ticketCounts.emitido }}
                </span>
              </button>
            </div>

            <!-- Caja de Búsqueda -->
            <div class="relative w-full lg:max-w-xs">
              <input 
                v-model="ticketSearch"
                @input="debounceFetchTickets"
                type="text" 
                placeholder="Buscar por PNR, Pasajero, CI, Vuelo..."
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 transition-all font-medium"
              />
              <Search class="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <!-- Tabla de Pasajeros y Boletos -->
          <div class="rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs dark:shadow-xl">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950/80 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase text-slate-500 dark:text-slate-400 font-bold tracking-wider">
                  <tr>
                    <th class="px-4 py-3.5">Estado</th>
                    <th class="px-4 py-3.5">PNR / Reserva</th>
                    <th class="px-4 py-3.5">Pasajero & Contacto</th>
                    <th class="px-4 py-3.5">Ruta & Vuelo</th>
                    <th class="px-4 py-3.5">Fecha & Tarifa</th>
                    <th class="px-4 py-3.5">Asesor Asignado</th>
                    <th class="px-4 py-3.5 text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                  <tr v-if="ticketsLoading" class="text-center">
                    <td colspan="7" class="py-12 text-slate-500">
                      <div class="flex items-center justify-center gap-2">
                        <Loader2 class="w-4 h-4 animate-spin text-sky-500" />
                        <span>Cargando registros de pasajeros...</span>
                      </div>
                    </td>
                  </tr>
                  <tr v-else-if="ticketsList.length === 0" class="text-center">
                    <td colspan="7" class="py-12 text-slate-500">
                      <div class="space-y-1">
                        <p class="font-semibold text-slate-600 dark:text-slate-400">No se encontraron pasajeros registrados</p>
                        <p class="text-[11px] text-slate-400">Prueba cambiando el filtro de estado o agregando un nuevo pasajero con el botón superior.</p>
                      </div>
                    </td>
                  </tr>
                  <tr 
                    v-for="t in ticketsList" 
                    :key="t.id"
                    class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                  >
                    <!-- Estado -->
                    <td class="px-4 py-3.5 whitespace-nowrap">
                      <span 
                        v-if="t.status === 'nuevo' && !t.conversation?.assigned_user_id && t.conversation?.status !== 'human_live'" 
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 inline-flex items-center gap-1"
                      >
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>Pendiente</span>
                      </span>
                      <span 
                        v-else-if="t.status === 'contactado' || t.status === 'en_proceso' || t.conversation?.assigned_user_id || t.conversation?.status === 'human_live'" 
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30 inline-flex items-center gap-1"
                      >
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                        <span>En Gestión</span>
                      </span>
                      <span 
                        v-else-if="t.status === 'en_emision'" 
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 inline-flex items-center gap-1"
                      >
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        <span>En Emisión</span>
                      </span>
                      <span 
                        v-else-if="t.status === 'emitido'" 
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 inline-flex items-center gap-1"
                      >
                        <CheckCircle2 class="w-3 h-3 text-emerald-500" />
                        <span>Emitido</span>
                      </span>
                      <span 
                        v-else 
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-500/30"
                      >
                        {{ t.status }}
                      </span>
                    </td>

                    <!-- PNR -->
                    <td class="px-4 py-3.5 font-mono whitespace-nowrap">
                      <span v-if="t.booking_reference" class="px-2 py-0.5 rounded bg-sky-500/10 border border-sky-500/30 font-bold text-sky-600 dark:text-sky-400">
                        {{ t.booking_reference }}
                      </span>
                      <span v-else class="text-slate-400 dark:text-slate-600 text-[10px] italic">
                        Sin PNR
                      </span>
                    </td>

                    <!-- Pasajero & Contacto -->
                    <td class="px-4 py-3.5">
                      <div class="font-bold text-slate-900 dark:text-slate-200 flex items-center gap-1.5">
                        <span>{{ t.passenger_name }}</span>
                        <span v-if="t.passengers_count && t.passengers_count > 1" class="px-1.5 py-0.2 rounded-md bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 text-[10px] font-bold font-mono">
                          {{ t.passengers_count }} PAX
                        </span>
                      </div>
                      <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500">
                        <span class="font-mono">Doc: {{ t.passport_number || 'S/D' }}</span>
                        <span class="text-slate-300 dark:text-slate-700">•</span>
                        <a 
                          v-if="t.phone" 
                          :href="'https://wa.me/' + t.phone.replace(/\\D/g, '')" 
                          target="_blank" 
                          class="text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-0.5 font-mono"
                          title="Contactar en WhatsApp"
                        >
                          <Phone class="w-2.5 h-2.5" />
                          <span>+{{ t.phone }}</span>
                        </a>
                      </div>
                    </td>

                    <!-- Ruta & Vuelo -->
                    <td class="px-4 py-3.5 whitespace-nowrap">
                      <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                        <span>{{ t.origin }}</span>
                        <ArrowRight class="w-3 h-3 text-sky-500 dark:text-sky-400" />
                        <span>{{ t.destination }}</span>
                      </div>
                      <div class="text-[10px] text-slate-500 font-mono">
                        {{ t.flight_number ? 'Vuelo ' + t.flight_number : 'Vuelo por asignar' }}
                      </div>
                    </td>

                    <!-- Fecha Vuelo & Tarifa -->
                    <td class="px-4 py-3.5 whitespace-nowrap">
                      <div class="text-slate-700 dark:text-slate-300 font-mono font-medium">
                        {{ formatFlightDate(t.flight_date) }}
                      </div>
                      <div class="text-[11px] font-bold font-mono" :class="t.total_price ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                        {{ t.total_price ? formatCurrency(t.total_price) : 'Por cotizar' }}
                      </div>
                    </td>

                    <!-- Asesor -->
                    <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">
                      <div class="font-medium text-slate-700 dark:text-slate-300">
                        {{ t.conversation?.assigned_user?.name || 'Sistema / Bot' }}
                      </div>
                      <div class="text-[10px] text-slate-400 font-mono">
                        {{ t.created_at ? formatTime(t.created_at) : '' }}
                      </div>
                    </td>

                    <!-- Acciones -->
                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                      <div class="flex items-center justify-end gap-1.5">
                        <!-- Botón Ver Ficha del Pasajero -->
                        <button 
                          @click="openPassengerDetails(t)"
                          class="px-2.5 py-1 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-700 dark:text-sky-300 border border-sky-500/30 text-[11px] font-semibold transition-all inline-flex items-center gap-1 cursor-pointer"
                          title="Ver y editar ficha del pasajero"
                        >
                          <Eye class="w-3 h-3" />
                          <span>Ficha</span>
                        </button>

                        <!-- Botón Abrir Chat -->
                        <router-link 
                          to="/"
                          class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-[11px] font-semibold transition-all inline-flex items-center gap-1 cursor-pointer"
                          title="Ir al chat de atención"
                        >
                          <MessageSquare class="w-3 h-3 text-emerald-500" />
                          <span class="hidden sm:inline">Chat</span>
                        </router-link>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ========================================================
             PESTAÑA 3: GESTIÓN DE ASESORES Y EQUIPO
             ======================================================== -->
        <div v-if="activeTab === 'agents'" class="space-y-6">
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Columna Izquierda: Formulario de Alta de Nuevo Agente -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-4">
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                  <UserPlus class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                  <span>Registrar Nuevo Miembro</span>
                </h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Crea accesos para vendedores o administradores</p>
              </div>

              <form @submit.prevent="handleCreateAgent" class="space-y-3">
                <div>
                  <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Nombre Completo</label>
                  <input 
                    v-model="newAgent.name"
                    required
                    type="text" 
                    placeholder="Ej. Sofía Vargas"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Correo Electrónico</label>
                  <input 
                    v-model="newAgent.email"
                    required
                    type="email" 
                    placeholder="sofia@tamep.bo"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Contraseña Inicial</label>
                  <input 
                    v-model="newAgent.password"
                    required
                    type="password" 
                    placeholder="Mínimo 6 caracteres"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
                  />
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Rol en el Sistema</label>
                  <select 
                    v-model="newAgent.role"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 cursor-pointer"
                  >
                    <option value="agent">Agente de Ventas (Solo Chats y Emisión)</option>
                    <option value="admin">Administrador (Acceso Total y Gerencia)</option>
                  </select>
                </div>

                <div v-if="formFeedback.message" :class="[
                  'p-2.5 rounded-xl text-xs border',
                  formFeedback.type === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-300'
                ]">
                  {{ formFeedback.message }}
                </div>

                <button 
                  type="submit" 
                  :disabled="creatingAgent"
                  class="w-full py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-50 text-white font-bold text-xs transition-all shadow-md shadow-sky-600/30 cursor-pointer"
                >
                  {{ creatingAgent ? 'Registrando...' : 'Crear Usuario' }}
                </button>
              </form>
            </div>

            <!-- Columna Derecha: Directorio del Equipo Completo -->
            <div class="lg:col-span-2 p-6 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Users class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                    <span>Directorio de Personal Registrado</span>
                  </h3>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">Control de acceso y actualización de contraseñas</p>
                </div>
                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-700 dark:text-slate-300 font-mono font-bold">
                  {{ agentsList.length }} miembros
                </span>
              </div>

              <div class="space-y-3">
                <div 
                  v-for="agent in agentsList" 
                  :key="agent.id"
                  class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-indigo-700 flex items-center justify-center font-bold text-white text-xs shadow-md">
                      {{ agent.name.substring(0, 2).toUpperCase() }}
                    </div>
                    <div>
                      <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>{{ agent.name }}</span>
                        <span :class="[
                          'px-2 py-0.2 rounded-full text-[9px] font-bold border',
                          agent.role === 'admin' ? 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300' : 'bg-sky-500/10 border-sky-500/30 text-sky-700 dark:text-sky-300'
                        ]">
                          {{ agent.role === 'admin' ? 'Super Admin' : 'Asesor de Ventas' }}
                        </span>
                        <span :class="[
                          'px-1.5 py-0.2 rounded text-[9px] font-bold',
                          agent.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                        ]">
                          ● {{ agent.is_active ? 'Activo' : 'Suspendido' }}
                        </span>
                      </div>
                      <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ agent.email }}</div>
                    </div>
                  </div>

                  <!-- Botones de Acción sobre el Agente -->
                  <div class="flex items-center gap-2">
                    <button 
                      @click="requestToggleStatus(agent)"
                      :disabled="agent.id === authStore.user?.id"
                      :class="[
                        'px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed',
                        agent.is_active 
                          ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-300 border-amber-500/30' 
                          : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 border-emerald-500/30'
                      ]"
                      :title="agent.id === authStore.user?.id ? 'No puedes suspender tu propia cuenta' : (agent.is_active ? 'Suspender acceso temporalmente' : 'Reactivar cuenta')"
                    >
                      {{ agent.is_active ? 'Suspender' : 'Activar' }}
                    </button>

                    <button 
                      @click="openPasswordModal(agent)"
                      class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs transition-colors flex items-center gap-1.5 cursor-pointer font-medium shadow-xs"
                      title="Cambiar contraseña de acceso"
                    >
                      <KeyRound class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                      <span>Contraseña</span>
                    </button>

                    <button 
                      @click="requestDeleteAgent(agent)"
                      :disabled="agent.id === authStore.user?.id"
                      class="px-2.5 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-600 hover:text-white text-red-600 dark:text-red-400 border border-red-500/30 text-xs transition-colors flex items-center gap-1.5 cursor-pointer font-medium shadow-xs disabled:opacity-40 disabled:cursor-not-allowed"
                      :title="agent.id === authStore.user?.id ? 'No puedes eliminar tu propia cuenta' : 'Eliminar permanentemente a este agente'"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                      <span>Eliminar</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- ========================================================
             PESTAÑA 4: MONITOREO DE CONVERSACIONES EN VIVO
             ======================================================== -->
        <div v-if="activeTab === 'monitor'" class="space-y-4">
          <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-xs dark:shadow-none">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Eye class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                <span>Supervisión de Conversaciones en Tiempo Real</span>
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Observa las conversaciones asignadas al equipo y el estado de la cola</p>
            </div>
            <div class="flex gap-2">
              <button 
                @click="monitorStatusFilter = 'all'; fetchMonitorConversations()"
                :class="['px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-colors', monitorStatusFilter === 'all' ? 'bg-sky-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700']"
              >
                Todas
              </button>
              <button 
                @click="monitorStatusFilter = 'waiting_agent'; fetchMonitorConversations()"
                :class="['px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-colors', monitorStatusFilter === 'waiting_agent' ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700']"
              >
                En Espera
              </button>
              <button 
                @click="monitorStatusFilter = 'human_live'; fetchMonitorConversations()"
                :class="['px-3 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-colors', monitorStatusFilter === 'human_live' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700']"
              >
                En Atención
              </button>
            </div>
          </div>

          <div class="rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs dark:shadow-xl">
            <div class="divide-y divide-slate-200 dark:divide-slate-800/60">
              <div v-if="monitorList.length === 0" class="text-center py-10 text-slate-500 text-xs">
                No hay conversaciones activas en este filtro.
              </div>
              <div 
                v-for="conv in monitorList" 
                :key="conv.id"
                class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
              >
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ (conv.contact_name || conv.phone).substring(0, 2).toUpperCase() }}
                  </div>
                  <div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                      <span>{{ conv.contact_name || 'Pasajero de WhatsApp' }}</span>
                      <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">+{{ conv.phone }}</span>
                      <span :class="[
                        'px-2 py-0.2 rounded-full text-[9px] font-bold border',
                        conv.status === 'waiting_agent' ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border-amber-500/40 animate-pulse' : 'bg-sky-500/20 text-sky-700 dark:text-sky-300 border-sky-500/40'
                      ]">
                        {{ conv.status === 'waiting_agent' ? 'En Cola (Handover)' : 'Atención en Vivo' }}
                      </span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 max-w-lg truncate">
                      Último mensaje: <span class="text-slate-700 dark:text-slate-300">{{ conv.latest_message?.message || 'Sin mensajes recientes' }}</span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                      Asignado: <span class="text-sky-600 dark:text-sky-400 font-bold">{{ conv.assigned_user?.name || 'Sin Asignar (En Cola)' }}</span>
                    </div>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ formatTime(conv.last_interaction_at) }}</div>
                  </div>

                  <router-link 
                    to="/"
                    class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold border border-slate-200 dark:border-slate-700 transition-colors flex items-center gap-1 cursor-pointer shadow-xs"
                  >
                    <span>Abrir Chat</span>
                    <ExternalLink class="w-3 h-3" />
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ========================================================
             PESTAÑA 5: ESTADO DE LA CUENTA DE WHATSAPP
             ======================================================== -->
        <div v-if="activeTab === 'meta'" class="space-y-6">
          <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-6">
            
            <!-- Encabezado de la Sección -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
              <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20">
                  <Smartphone class="w-6 h-6" />
                </div>
                <div>
                  <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Estado de la Cuenta de WhatsApp</span>
                    <span 
                      class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border flex items-center gap-1.5"
                      :class="metaInfo.phone?.error ? 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'"
                    >
                      <span class="w-2 h-2 rounded-full" :class="metaInfo.phone?.error ? 'bg-red-500' : 'bg-emerald-500 animate-pulse'"></span>
                      {{ metaInfo.phone?.error ? 'Requiere Atención' : 'Cuenta Operativa' }}
                    </span>
                  </h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Monitoreo en tiempo real de la reputación, calidad y salud de la línea oficial
                  </p>
                </div>
              </div>

              <!-- Botón Actualizar Estado en Vivo -->
              <button 
                type="button"
                @click="refreshMetaStatus"
                :disabled="loadingMetaStatus"
                class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold border border-slate-200 dark:border-slate-700 transition-colors flex items-center gap-2 cursor-pointer self-start sm:self-auto disabled:opacity-50 shadow-xs"
                title="Consultar estado con Meta en vivo"
              >
                <RefreshCw class="w-3.5 h-3.5" :class="{'animate-spin': loadingMetaStatus}" />
                <span>{{ loadingMetaStatus ? 'Verificando...' : 'Comprobar Estado' }}</span>
              </button>
            </div>

            <!-- Tarjetas Principales de Estado de Cuenta -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
              
              <!-- 1. Tarjeta Calidad de la Cuenta (Quality Rating) -->
              <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100/60 dark:from-slate-950 dark:to-slate-900 border border-slate-200 dark:border-slate-800/80 flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Calidad de la Línea</span>
                  <div 
                    class="w-8 h-8 rounded-xl flex items-center justify-center"
                    :class="[
                      (metaInfo.phone?.quality_rating === 'GREEN' || !metaInfo.phone?.quality_rating) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
                      metaInfo.phone?.quality_rating === 'YELLOW' ? 'bg-amber-500/10 text-amber-500' : 'bg-red-500/10 text-red-500'
                    ]"
                  >
                    <ShieldCheck class="w-4 h-4" />
                  </div>
                </div>

                <div>
                  <div class="flex items-center gap-2.5">
                    <span 
                      class="w-3.5 h-3.5 rounded-full shrink-0 shadow-sm"
                      :class="[
                        (metaInfo.phone?.quality_rating === 'GREEN' || !metaInfo.phone?.quality_rating) ? 'bg-emerald-500 shadow-emerald-500/50' :
                        metaInfo.phone?.quality_rating === 'YELLOW' ? 'bg-amber-500 shadow-amber-500/50' : 'bg-red-500 shadow-red-500/50'
                      ]"
                    ></span>
                    <span class="text-xl font-extrabold text-slate-900 dark:text-white">
                      {{ metaInfo.phone?.quality_rating === 'YELLOW' ? 'Media (Precaución)' : metaInfo.phone?.quality_rating === 'RED' ? 'Baja (Riesgo)' : 'Excelente (Alta)' }}
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                    <template v-if="metaInfo.phone?.quality_rating === 'YELLOW'">
                      Meta ha detectado reportes leves recientes. Mantenga las respuestas pertinentes.
                    </template>
                    <template v-else-if="metaInfo.phone?.quality_rating === 'RED'">
                      La cuenta tiene reportes elevados. Se recomienda moderar los mensajes no solicitados.
                    </template>
                    <template v-else>
                      La línea no presenta bloqueos de pasajeros ni advertencias. Opera con la máxima reputación permitida por Meta.
                    </template>
                  </p>
                </div>

                <div class="pt-2 border-t border-slate-200/70 dark:border-slate-800 text-[10px] text-slate-400 dark:text-slate-500 font-medium flex items-center justify-between">
                  <span>Evaluación oficial Meta:</span>
                  <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase">
                    {{ metaInfo.phone?.quality_rating || 'GREEN (Óptimo)' }}
                  </span>
                </div>
              </div>

              <!-- 2. Tarjeta Identidad Oficial Verificada -->
              <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100/60 dark:from-slate-950 dark:to-slate-900 border border-slate-200 dark:border-slate-800/80 flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Identidad Oficial</span>
                  <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                    <CheckCircle2 class="w-4 h-4" />
                  </div>
                </div>

                <div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Nombre Comercial Verificado:</div>
                  <div class="text-sm font-bold text-slate-900 dark:text-white leading-tight">
                    {{ metaInfo.phone?.verified_name || 'TAMep - Transporte Aéreo Militar Empresa Pública' }}
                  </div>
                  <div class="text-xs font-mono text-slate-700 dark:text-slate-300 mt-2 font-semibold flex items-center gap-1.5">
                    <Phone class="w-3.5 h-3.5 text-emerald-500" />
                    <span>{{ metaInfo.phone?.display_phone_number || '+1 555-677-4453' }}</span>
                  </div>
                </div>

                <div class="pt-2 border-t border-slate-200/70 dark:border-slate-800 text-[10px] text-slate-400 dark:text-slate-500 font-medium flex items-center justify-between">
                  <span>Verificación Meta:</span>
                  <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                    <Check class="w-3 h-3" />
                    Empresa Oficial Aprobada
                  </span>
                </div>
              </div>

              <!-- 3. Tarjeta Estado de Servicios y Conectividad -->
              <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100/60 dark:from-slate-950 dark:to-slate-900 border border-slate-200 dark:border-slate-800/80 flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Salud de Servicios</span>
                  <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <Radio class="w-4 h-4" />
                  </div>
                </div>

                <div class="space-y-2.5">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 dark:text-slate-400">Recepción de Mensajes:</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                      Operativo
                    </span>
                  </div>
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 dark:text-slate-400">Envío de Mensajes y Boletos:</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                      Operativo
                    </span>
                  </div>
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 dark:text-slate-400">Asistente Virtual (Bot):</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                      Activo 24/7
                    </span>
                  </div>
                </div>

                <div class="pt-2 border-t border-slate-200/70 dark:border-slate-800 text-[10px] text-slate-400 dark:text-slate-500 font-medium flex items-center justify-between">
                  <span>Plataforma:</span>
                  <span class="font-bold text-slate-700 dark:text-slate-300">WhatsApp Cloud Oficial</span>
                </div>
              </div>

            </div>

            <!-- Resumen Informativo de Operación -->
            <div class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20 flex items-start gap-3 text-xs">
              <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                <CheckCircle2 class="w-4 h-4" />
              </div>
              <div>
                <h4 class="font-bold text-slate-900 dark:text-white">Canal de Atención Habilitado y Conectado</h4>
                <p class="text-slate-600 dark:text-slate-300 text-[11px] mt-0.5 leading-relaxed">
                  El canal de WhatsApp de TAMep está completamente operativo para cotizar vuelos, responder dudas y emitir pasajes tanto por los asesores humanos como a través del chatbot automático.
                </p>
              </div>
            </div>

          </div>
        </div>

        <!-- ========================================================
             PESTAÑA 5: GESTIÓN DE ITINERARIO DE VUELOS
             ======================================================== -->
        <div v-if="activeTab === 'itinerary'" class="space-y-6">
          <!-- Cabecera de la sección -->
          <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-600 dark:text-sky-400">
                  <CalendarDays class="w-5 h-5" />
                </div>
                <div>
                  <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Itinerario Oficial de Vuelos</span>
                    <span v-if="itineraryData.has_image" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400">
                      Imagen Activa
                    </span>
                    <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400">
                      Solo Enlace Web
                    </span>
                  </h2>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Sube la imagen y el enlace web que el bot de WhatsApp envía a los pasajeros cuando consultan el itinerario.
                  </p>
                </div>
              </div>
            </div>

            <div v-if="itineraryData.updated_at" class="text-left md:text-right shrink-0">
              <div class="text-[11px] text-slate-400 font-medium">Última actualización</div>
              <div class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ itineraryData.updated_at }}</div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400">Por: {{ itineraryData.updated_by || 'Administrador' }}</div>
            </div>
          </div>

          <!-- Cuadrícula 2 Columnas: Formulario de Subida + Vista Previa en WhatsApp -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Columna Izquierda: Formulario de Edición (7 cols) -->
            <div class="lg:col-span-7 space-y-6">
              <form @submit.prevent="saveItinerary" class="p-6 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-5">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                  <Upload class="w-4 h-4 text-sky-500" />
                  <span>Actualizar Imagen y Enlaces</span>
                </h3>

                <!-- Subir Imagen del Itinerario -->
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                    Imagen del Itinerario (JPG, PNG, WebP - Máx 10MB)
                  </label>
                  
                  <div class="space-y-3">
                    <label class="block border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-sky-500 dark:hover:border-sky-400 rounded-2xl p-5 text-center cursor-pointer transition-colors bg-slate-50/50 dark:bg-slate-950/40 hover:bg-sky-50/30 dark:hover:bg-sky-950/20 group">
                      <input 
                        type="file" 
                        accept="image/jpeg,image/png,image/webp" 
                        class="hidden" 
                        @change="handleImageSelect" 
                      />
                      <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                          <Upload class="w-5 h-5" />
                        </div>
                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                          {{ itineraryForm.imageFile ? itineraryForm.imageFile.name : 'Haz clic para elegir imagen o arrastra el archivo aquí' }}
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                          Recomendado: Tabla de itinerarios legible en celular (alta resolución)
                        </div>
                      </div>
                    </label>

                    <div v-if="itineraryForm.imageFile" class="flex items-center justify-between p-2.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-xs">
                      <span class="text-sky-700 dark:text-sky-300 font-medium truncate max-w-xs">📎 {{ itineraryForm.imageFile.name }} ({{ (itineraryForm.imageFile.size / 1024 / 1024).toFixed(2) }} MB)</span>
                      <button type="button" @click="removeSelectedImage" class="text-red-500 hover:text-red-700 text-xs font-semibold cursor-pointer">Descartar</button>
                    </div>
                  </div>
                </div>

                <!-- Nota adicional / Pie del Mensaje -->
                <div class="space-y-1.5">
                  <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <Info class="w-3.5 h-3.5 text-sky-500" />
                    <span>Nota Informativa Adicional (Opcional)</span>
                  </label>
                  <textarea 
                    v-model="itineraryForm.caption" 
                    rows="2"
                    placeholder="Ej: Temporada Alta - Tarifas promocionales vigentes hasta fin de mes."
                    class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all resize-none"
                  ></textarea>
                </div>

                <!-- Botón Guardar -->
                <div class="pt-2 flex items-center justify-end gap-3">
                  <button 
                    type="submit" 
                    :disabled="savingItinerary"
                    class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-sky-600/20 active:scale-95 transition-all cursor-pointer disabled:opacity-50"
                  >
                    <Loader2 v-if="savingItinerary" class="w-4 h-4 animate-spin" />
                    <Upload v-else class="w-4 h-4" />
                    <span>{{ savingItinerary ? 'Guardando Itinerario...' : 'Publicar Nuevo Itinerario' }}</span>
                  </button>
                </div>
              </form>
            </div>

            <!-- Columna Derecha: Simulación WhatsApp en Vivo (5 cols) -->
            <div class="lg:col-span-5 space-y-4">
              <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-3">
                <div class="flex items-center justify-between">
                  <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Smartphone class="w-4 h-4 text-emerald-500" />
                    <span>Vista Previa en WhatsApp</span>
                  </div>
                  <span class="text-[10px] text-slate-500 font-mono">Lo que ve el pasajero</span>
                </div>

                <!-- Burbuja de WhatsApp Estilo Real -->
                <div class="rounded-2xl bg-[#efeae2] dark:bg-[#0b141a] p-3.5 border border-slate-200 dark:border-slate-800 space-y-2.5">
                  
                  <!-- Mensaje Bot con Imagen y Botones -->
                  <div class="bg-white dark:bg-[#1f2c34] rounded-2xl overflow-hidden shadow-xs border border-slate-200/50 dark:border-white/5 max-w-sm">
                    <!-- Imagen preview -->
                    <div v-if="itineraryForm.imagePreview" class="relative group bg-slate-900 overflow-hidden max-h-56 flex items-center justify-center">
                      <img 
                        :src="itineraryForm.imagePreview" 
                        alt="Vista previa del itinerario" 
                        class="w-full object-cover cursor-pointer hover:opacity-90 transition-opacity" 
                      />
                      <div class="absolute bottom-2 right-2 px-2 py-0.5 rounded bg-black/60 text-white text-[9px] font-mono">
                        {{ itineraryForm.imageFile ? 'Nueva imagen seleccionada' : 'Imagen actual' }}
                      </div>
                    </div>
                    <div v-else class="h-36 bg-slate-100 dark:bg-slate-800 flex flex-col items-center justify-center text-slate-400 gap-1.5 p-4 text-center">
                      <CalendarDays class="w-8 h-8 text-slate-400" />
                      <span class="text-xs font-medium">Sin imagen cargada</span>
                      <span class="text-[10px]">El bot enviará el enlace web oficial</span>
                    </div>

                    <!-- Texto del Mensaje -->
                    <div class="p-3 text-[11px] text-slate-800 dark:text-slate-200 space-y-1.5 leading-relaxed font-sans">
                      <div class="font-bold text-slate-900 dark:text-white">📅 ITINERARIO OFICIAL DE VUELOS TAMEP</div>
                      <p class="text-slate-600 dark:text-slate-300">
                        Aquí puedes revisar las frecuencias semanales, horarios de salida y destinos programados.
                      </p>
                      <div v-if="itineraryForm.caption" class="text-sky-700 dark:text-sky-400 italic">
                        💡 {{ itineraryForm.caption }}
                      </div>
                    </div>

                    <!-- Botones Interactivos Simulados -->
                    <div class="border-t border-slate-100 dark:border-slate-700/60 divide-y divide-slate-100 dark:divide-slate-700/60 bg-slate-50/50 dark:bg-slate-900/50 text-center font-semibold text-[11px] text-sky-600 dark:text-sky-400">
                      <div class="py-2 hover:bg-slate-100 dark:hover:bg-slate-800/80 cursor-pointer flex items-center justify-center gap-1.5">
                        <span>👉 Buscar Vuelos</span>
                      </div>
                      <div class="py-2 hover:bg-slate-100 dark:hover:bg-slate-800/80 cursor-pointer flex items-center justify-center gap-1.5">
                        <span>👤 Contactar Agente</span>
                      </div>
                      <div class="py-2 hover:bg-slate-100 dark:hover:bg-slate-800/80 cursor-pointer flex items-center justify-center gap-1.5">
                        <span>🏠 Menú Principal</span>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- ========================================================
             PESTAÑA 7: GESTIÓN DE MENSAJES Y PLANTILLAS DEL BOT DE WHATSAPP
             ======================================================== -->
        <div v-if="activeTab === 'bot_messages'" class="space-y-6">
          
          <!-- Encabezado de la Sección y Resumen -->
          <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-sky-500/20">
                  <Bot class="w-5 h-5" />
                </div>
                <div>
                  <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>Mensajes y Respuestas del Bot de WhatsApp</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                      Área Comercial
                    </span>
                  </h2>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Edita saludos, políticas de equipaje, oficinas, teléfonos y respuestas frecuentes sin necesidad de programar ni solicitar cambios a informática.
                  </p>
                </div>
              </div>
            </div>

            <!-- Métricas rápidas de los mensajes -->
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
              <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-center">
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold uppercase">Total</div>
                <div class="text-sm font-bold text-slate-900 dark:text-white font-mono">{{ botMessagesStats.total }}</div>
              </div>
              <div class="px-3 py-1.5 rounded-xl bg-sky-500/10 border border-sky-500/20 text-center">
                <div class="text-[10px] text-sky-600 dark:text-sky-400 font-semibold uppercase">Personalizados</div>
                <div class="text-sm font-bold text-sky-700 dark:text-sky-300 font-mono">{{ botMessagesStats.custom }}</div>
              </div>
              <div class="px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-center">
                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold uppercase">Originales</div>
                <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300 font-mono">{{ botMessagesStats.defaultCount }}</div>
              </div>
            </div>
          </div>

          <!-- Barra de Filtros y Búsqueda -->
          <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <!-- Buscador -->
              <div class="relative flex-1 max-w-md">
                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input 
                  v-model="botMessageSearch" 
                  type="text" 
                  placeholder="Buscar mensaje, título o palabra clave..." 
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-8 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
                />
                <button 
                  v-if="botMessageSearch" 
                  @click="botMessageSearch = ''" 
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>

              <!-- Contador de resultados -->
              <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                Mostrando <strong class="text-slate-800 dark:text-slate-200">{{ filteredBotMessages.length }}</strong> de {{ botMessages.length }} mensajes
              </div>
            </div>

            <!-- Filtros por Categoría -->
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-1">
              <button 
                @click="selectedBotCategory = 'all'" 
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition cursor-pointer',
                  selectedBotCategory === 'all' 
                    ? 'bg-sky-600 text-white shadow-xs' 
                    : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'
                ]"
              >
                Todas las Categorías
              </button>
              <button 
                v-for="cat in botCategories" 
                :key="cat" 
                @click="selectedBotCategory = cat" 
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition cursor-pointer',
                  selectedBotCategory === cat 
                    ? 'bg-sky-600 text-white shadow-xs' 
                    : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'
                ]"
              >
                {{ cat }}
              </button>
            </div>
          </div>

          <!-- Spinner de Carga -->
          <div v-if="loadingBotMessages" class="p-12 text-center">
            <Loader2 class="w-8 h-8 animate-spin mx-auto text-sky-500" />
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 font-medium">Cargando textos del bot...</p>
          </div>

          <!-- Lista de Mensajes Configurables -->
          <div v-else-if="filteredBotMessages.length > 0" class="space-y-5">
            <div 
              v-for="msg in filteredBotMessages" 
              :key="msg.id" 
              class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl space-y-4 transition-all hover:border-slate-300 dark:hover:border-slate-700"
            >
              <!-- Cabecera de la Tarjeta del Mensaje -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                <div class="space-y-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-500/20">
                      {{ msg.category }}
                    </span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                      {{ msg.title }}
                    </h3>
                    <code class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                      {{ msg.key }}
                    </code>
                  </div>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ msg.description }}
                  </p>
                </div>

                <!-- Estado del mensaje -->
                <div class="flex items-center gap-2 shrink-0">
                  <span 
                    v-if="editingBotMessages[msg.id] !== msg.message" 
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 flex items-center gap-1 animate-pulse"
                  >
                    <AlertCircle class="w-3 h-3" />
                    Cambios sin guardar
                  </span>
                  <span 
                    v-else-if="msg.message !== msg.default_message" 
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/20"
                  >
                    Personalizado
                  </span>
                  <span 
                    v-else 
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700/60"
                  >
                    Predeterminado
                  </span>
                  <span v-if="msg.updated_by" class="text-[10px] text-slate-400 dark:text-slate-500 hidden sm:inline">
                    Por: {{ msg.updated_by }}
                  </span>
                </div>
              </div>

              <!-- Variables Dinámicas Disponibles (si aplica) -->
              <div v-if="msg.variables && msg.variables.length > 0" class="p-2.5 rounded-xl bg-sky-50/60 dark:bg-sky-950/20 border border-sky-100 dark:border-sky-900/30 flex items-center gap-2 flex-wrap text-xs">
                <span class="text-[11px] font-bold text-sky-800 dark:text-sky-300 flex items-center gap-1">
                  <Sparkles class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                  Variables dinámicas:
                </span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400">
                  (Haz clic para insertar en el texto):
                </span>
                <button 
                  v-for="v in msg.variables" 
                  :key="v" 
                  type="button" 
                  @click="insertVariable(msg.id, v)" 
                  class="px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 border border-sky-300 dark:border-sky-700 text-sky-700 dark:text-sky-300 text-[11px] font-mono font-bold hover:bg-sky-100 dark:hover:bg-sky-800/40 transition cursor-pointer"
                  :title="`Insertar {${v}}`"
                >
                  +&#123;{{ v }}&#125;
                </button>
              </div>

              <!-- Barra de Atajos de Formato WhatsApp & Emojis -->
              <div class="flex items-center justify-between gap-2 flex-wrap text-xs bg-slate-50/80 dark:bg-slate-950/40 p-2 rounded-xl border border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-1">Formato:</span>
                  <button 
                    type="button" 
                    @click="insertFormat(msg.id, '*')" 
                    class="px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-sky-400 text-slate-700 dark:text-slate-300 font-bold text-[11px] cursor-pointer"
                    title="Negrita: *texto*"
                  >
                    *B* Negrita
                  </button>
                  <button 
                    type="button" 
                    @click="insertFormat(msg.id, '_')" 
                    class="px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-sky-400 text-slate-700 dark:text-slate-300 italic text-[11px] cursor-pointer"
                    title="Cursiva: _texto_"
                  >
                    _I_ Cursiva
                  </button>
                  <button 
                    type="button" 
                    @click="insertFormat(msg.id, '~')" 
                    class="px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-sky-400 text-slate-700 dark:text-slate-300 line-through text-[11px] cursor-pointer"
                    title="Tachado: ~texto~"
                  >
                    ~S~ Tachado
                  </button>
                </div>

                <div class="flex items-center gap-1 flex-wrap">
                  <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-1">Emojis:</span>
                  <button 
                    v-for="emoji in ['✈️', '📦', '🏢', '📍', '📞', '🕐', '🧳', '💳', '👶', '🐾', '❌', '✅']" 
                    :key="emoji" 
                    type="button" 
                    @click="insertEmoji(msg.id, emoji)" 
                    class="w-6 h-6 flex items-center justify-center rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:scale-110 text-xs transition cursor-pointer"
                  >
                    {{ emoji }}
                  </button>
                </div>
              </div>

              <!-- Editor y Vista Previa Lado a Lado -->
              <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
                
                <!-- Columna Izquierda: Textarea Editor -->
                <div class="lg:col-span-7 space-y-1.5">
                  <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-700 dark:text-slate-300">Editor de Texto para WhatsApp</span>
                    <span class="font-mono text-[11px]">
                      {{ (editingBotMessages[msg.id] || '').length }} caracteres
                    </span>
                  </div>
                  <textarea 
                    v-model="editingBotMessages[msg.id]" 
                    rows="10" 
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-mono leading-relaxed resize-y"
                    placeholder="Escribe el contenido del mensaje..."
                  ></textarea>
                </div>

                <!-- Columna Derecha: Vista Previa Realista de WhatsApp -->
                <div class="lg:col-span-5 space-y-1.5">
                  <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                      <Smartphone class="w-3.5 h-3.5 text-emerald-500" />
                      Vista Previa WhatsApp
                    </span>
                    <span class="text-[10px] text-slate-400">Como lo ve el cliente</span>
                  </div>

                  <!-- Marco de Pantalla WhatsApp -->
                  <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-[#efeae2] dark:bg-[#0b141a] shadow-inner">
                    <!-- Cabecera de WhatsApp del chat -->
                    <div class="bg-[#075e54] dark:bg-[#202c33] px-3 py-2 flex items-center gap-2 text-white">
                      <div class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-xs">
                        ✈️
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-bold truncate leading-none">TAMep Línea Aérea</div>
                        <div class="text-[9px] text-emerald-200 dark:text-emerald-400 leading-none mt-0.5">en línea (Bot)</div>
                      </div>
                    </div>

                    <!-- Cuerpo del chat con fondo simulado -->
                    <div class="p-3 max-h-[300px] overflow-y-auto space-y-2">
                      <!-- Burbuja de mensaje entrante del Bot -->
                      <div class="bg-white dark:bg-[#202c33] text-slate-900 dark:text-slate-100 p-2.5 rounded-lg rounded-tl-none shadow-xs text-xs relative max-w-[95%] leading-relaxed">
                        <div 
                          class="whitespace-pre-wrap break-words text-[11px] select-text"
                          v-html="formatWhatsAppPreview(editingBotMessages[msg.id])"
                        ></div>
                        <div class="text-right text-[9px] text-slate-400 dark:text-slate-500 mt-1 flex items-center justify-end gap-1 font-mono">
                          <span>08:15</span>
                          <span class="text-sky-500 font-bold">✓✓</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- Barra de Acciones / Guardar / Restablecer -->
              <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex-wrap">
                <!-- Botones Izquierda: Restablecer / Copiar -->
                <div class="flex items-center gap-2">
                  <button 
                    type="button" 
                    @click="restoreBotMessage(msg)" 
                    :disabled="restoringBotMessageId === msg.id"
                    class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-red-50 hover:border-red-300 dark:hover:bg-red-950/20 dark:hover:border-red-800/50 text-slate-600 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer disabled:opacity-50"
                    title="Restablecer este texto al valor original de fábrica"
                  >
                    <RotateCcw class="w-3.5 h-3.5" :class="{'animate-spin': restoringBotMessageId === msg.id}" />
                    <span class="hidden sm:inline">Restablecer de Fábrica</span>
                    <span class="sm:hidden">Restablecer</span>
                  </button>

                  <button 
                    type="button" 
                    @click="copyMessageText(editingBotMessages[msg.id] || msg.message)" 
                    class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs flex items-center gap-1 transition cursor-pointer"
                    title="Copiar texto al portapapeles"
                  >
                    <Copy class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Copiar</span>
                  </button>
                </div>

                <!-- Botones Derecha: Descartar / Guardar Cambios -->
                <div class="flex items-center gap-2">
                  <button 
                    v-if="editingBotMessages[msg.id] !== msg.message" 
                    type="button" 
                    @click="discardBotMessageChanges(msg)" 
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                  >
                    Descartar Cambios
                  </button>

                  <button 
                    type="button" 
                    @click="saveBotMessage(msg)" 
                    :disabled="savingBotMessageId === msg.id" 
                    class="px-4 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-sky-600/20 transition cursor-pointer disabled:opacity-50"
                  >
                    <Loader2 v-if="savingBotMessageId === msg.id" class="w-3.5 h-3.5 animate-spin" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ savingBotMessageId === msg.id ? 'Guardando...' : 'Guardar Mensaje' }}</span>
                  </button>
                </div>
              </div>

            </div>
          </div>

          <!-- Estado Vacío / Sin Resultados -->
          <div v-else class="p-12 text-center rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800">
            <Bot class="w-10 h-10 text-slate-400 mx-auto mb-2 opacity-50" />
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">No se encontraron mensajes</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              No hay plantillas que coincidan con "{{ botMessageSearch }}" en la categoría seleccionada.
            </p>
            <button 
              @click="botMessageSearch = ''; selectedBotCategory = 'all'" 
              class="mt-3 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
            >
              Limpiar Filtros
            </button>
          </div>

        </div>

        <!-- ========================================================
             PESTAÑA: AVISOS Y DIFUSIÓN SMS MASIVA (GOIP - ENTEL)
             ======================================================== -->
        <div v-if="activeTab === 'broadcasts'" class="space-y-6">
          
          <!-- Encabezado Limpio -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-slate-200 dark:border-slate-800">
            <div>
              <h2 class="text-base font-bold text-slate-900 dark:text-white">
                Avisos de Vuelo por SMS
              </h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Envío masivo a pasajeros ante demoras o cancelaciones
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <!-- Indicador de Envío y Actualización Automática en Vivo -->
              <div 
                v-if="isAutoBroadcasting" 
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold animate-pulse"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <Loader2 class="w-3.5 h-3.5 animate-spin" />
                <span>Enviando automáticamente... (En vivo)</span>
              </div>
              <div 
                v-else 
                class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs font-medium"
              >
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
                <span>Envíos al día</span>
              </div>

              <!-- Botón Forzar Despacho Manual de Contingencia -->
              <button 
                type="button" 
                @click="triggerProcessQueue" 
                :disabled="processingQueue"
                class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer"
                title="Fuerza un ciclo de despacho manual inmediato"
              >
                <Loader2 v-if="processingQueue" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ processingQueue ? 'Despachando...' : 'Forzar Despacho' }}</span>
              </button>

              <button 
                type="button" 
                @click="fetchBroadcastCampaigns" 
                :disabled="broadcastLoading"
                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 transition cursor-pointer"
              >
                Actualizar
              </button>
            </div>
          </div>


          <!-- Formulario de Configuración y Emisión -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Columna Izquierda: Filtros de Pasajeros y Datos del Vuelo (7 cols) -->
            <div class="lg:col-span-7 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
              <!-- Encabezado de Sección -->
              <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                  1. Buscar Vuelo Afectado
                </h3>
                 </div>

              <!-- Bloque Principal de Búsqueda de Vuelo -->
              <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                  <div class="sm:col-span-3">
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Origen
                    </label>
                    <select 
                      v-model="broadcastForm.origin"
                      @change="onFlightDateOrRouteChange"
                      class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                    >
                      <option value="">(Todos los orígenes)</option>
                      <option v-for="apt in broadcastAirports" :key="apt.code" :value="apt.code">
                        {{ apt.city }} ({{ apt.code }})
                      </option>
                    </select>
                  </div>

                  <div class="sm:col-span-3">
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Destino
                    </label>
                    <select 
                      v-model="broadcastForm.destination"
                      @change="onFlightDateOrRouteChange"
                      class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                    >
                      <option value="">(Todos los destinos)</option>
                      <option v-for="apt in broadcastAirports" :key="apt.code" :value="apt.code">
                        {{ apt.city }} ({{ apt.code }})
                      </option>
                    </select>
                  </div>

                  <div class="sm:col-span-3">
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                      Fecha del Vuelo *
                    </label>
                    <input 
                      v-model="broadcastForm.flight_date"
                      type="date"
                      @change="onFlightDateOrRouteChange"
                      class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                    />
                  </div>

                  <div class="sm:col-span-3">
                    <button 
                      type="button" 
                      @click="verifyFlightWithTransclick"
                      :disabled="verifyingFlight || !broadcastForm.flight_date"
                      class="w-full py-2 px-3 rounded-xl bg-sky-600 hover:bg-sky-500 disabled:opacity-50 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer"
                    >
                      <Loader2 v-if="verifyingFlight" class="w-3.5 h-3.5 animate-spin" />
                      <span>{{ verifyingFlight ? 'Buscando...' : 'Buscar Vuelos' }}</span>
                    </button>
                  </div>
                </div>

                <!-- Lista de Vuelos Disponibles para Seleccionar -->
                <div v-if="availableFlightsList.length" class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-sky-500/30 space-y-2">
                  <div class="flex items-center justify-between text-xs pb-1 border-b border-slate-100 dark:border-slate-800">
                    <span class="font-bold text-slate-900 dark:text-white">
                      Vuelos disponibles en Transclick ({{ availableFlightsList.length }}):
                    </span>
                    <span class="text-[10px] text-slate-400">Selecciona el vuelo afectado</span>
                  </div>

                  <div class="space-y-1.5 max-h-48 overflow-y-auto">
                    <div 
                      v-for="fl in availableFlightsList" 
                      :key="fl.flight_number + fl.departure_time"
                      class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 hover:border-sky-500/50 hover:bg-sky-50/40 dark:hover:bg-sky-950/20 flex items-center justify-between flex-wrap gap-2 transition"
                    >
                      <div class="flex items-center gap-2 text-xs font-mono">
                        <span class="px-2 py-0.5 rounded font-bold bg-sky-600 text-white text-[11px]">
                          {{ fl.flight_number }}
                        </span>
                        <span class="font-bold text-slate-900 dark:text-white">
                          {{ fl.origin_city || fl.origin }} → {{ fl.destination_city || fl.destination }}
                        </span>
                        <span class="text-slate-500 text-[11px]">
                          Salida: {{ fl.departure_time }} (Llegada: {{ fl.arrival_time || 'N/A' }})
                        </span>
                      </div>

                      <button 
                        type="button" 
                        @click="selectAvailableFlight(fl)"
                        :class="isFlightSelected(fl) ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-3 py-1 rounded-md text-xs font-bold transition cursor-pointer"
                      >
                        {{ isFlightSelected(fl) ? '✓ Seleccionado' : 'Seleccionar' }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Carga de Manifiesto Oficial de Pasajeros (PDF) -->
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">


                  <input 
                    type="file" 
                    ref="manifestFileInput" 
                    accept=".pdf" 
                    class="hidden" 
                    @change="handleManifestFileUpload" 
                  />

                  <!-- Estado Normal: Botón Prominente con Drag & Drop -->
                  <div 
                    v-if="!importedManifest"
                    @dragover.prevent="isDraggingManifest = true"
                    @dragleave.prevent="isDraggingManifest = false"
                    @drop.prevent="handleFileDrop"
                    @click="$refs.manifestFileInput.click()"
                    :class="[
                      isDraggingManifest 
                        ? 'border-sky-500 bg-sky-50 dark:bg-sky-950/40 ring-2 ring-sky-500/50' 
                        : 'border-slate-300 dark:border-slate-700 hover:border-sky-500 dark:hover:border-sky-400 bg-gradient-to-r from-sky-50/60 via-slate-50 to-indigo-50/40 dark:from-slate-900/60 dark:via-slate-900/40 dark:to-indigo-950/20 hover:bg-sky-50/70',
                      manifestLoading ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'
                    ]"
                    class="p-3.5 rounded-xl border-2 border-dashed transition flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xs"
                  >
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-sky-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <Loader2 v-if="manifestLoading" class="w-4 h-4 animate-spin" />
                        <Upload v-else class="w-4 h-4" />
                      </div>
                      <div>
                        <div class="text-xs font-bold text-slate-900 dark:text-white">
                          {{ manifestLoading ? 'Procesando archivo PDF y extrayendo celulares...' : 'Cargar Manifiesto de Pasajeros en PDF' }}
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                          Selecciona el archivo o arrastra el PDF de vuelo aquí para cargar los pasajeros
                        </div>
                      </div>
                    </div>

                    <button 
                      type="button" 
                      :disabled="manifestLoading"
                      class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-sm transition shrink-0 pointer-events-none"
                    >
                      <span>Seleccionar PDF</span>
                    </button>
                  </div>

                  <!-- Estado Activo: Manifiesto Cargado con Detalles Claros -->
                  <div 
                    v-else
                    class="p-3.5 rounded-xl border border-emerald-500/40 bg-emerald-50/60 dark:bg-emerald-950/30 space-y-2.5"
                  >
                    <div class="flex items-center justify-between flex-wrap gap-2">
                      <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0 font-bold text-xs">
                          ✓
                        </div>
                        <div>
                          <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>Manifiesto PDF Cargado con Éxito</span>
                            <span v-if="importedManifest.flight_number" class="px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-mono text-[10px] font-bold">
                              VUELO {{ importedManifest.flight_number }}
                            </span>
                          </div>
                          <div class="text-[11px] text-slate-600 dark:text-slate-400 font-mono">
                            {{ importedManifest.total_passengers }} Pasajeros totales • <strong class="text-emerald-600 dark:text-emerald-400 font-bold">{{ importedManifest.valid_phones_count }} teléfonos bolivianos</strong> listos para SMS
                          </div>
                        </div>
                      </div>

                      <div class="flex items-center gap-2">
                        <button 
                          type="button" 
                          @click="showManifestTable = !showManifestTable"
                          class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition cursor-pointer"
                        >
                          {{ showManifestTable ? 'Ocultar pasajeros' : 'Ver pasajeros' }}
                        </button>
                        <button 
                          type="button" 
                          @click="$refs.manifestFileInput.click()"
                          :disabled="manifestLoading"
                          class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition cursor-pointer"
                        >
                          Reemplazar
                        </button>
                        <button 
                          type="button" 
                          @click="clearImportedManifest"
                          class="px-2.5 py-1.5 rounded-lg bg-red-600 hover:bg-red-500 text-white text-xs font-bold transition cursor-pointer shadow-xs"
                        >
                          ✕ Quitar PDF
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Ficha Informativa y Modificación del Vuelo Detectado (Transclick o PDF) -->
              <div v-if="flightDetectedInfo" class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <div class="flex items-center gap-2 font-mono text-xs">
                    <span class="px-2 py-0.5 rounded font-bold bg-emerald-600 text-white text-[11px]">
                      VUELO {{ flightDetectedInfo.flight_number }}
                    </span>
                    <span class="font-bold text-slate-900 dark:text-white">
                      {{ flightDetectedInfo.origin_city || flightDetectedInfo.origin }} → {{ flightDetectedInfo.destination_city || flightDetectedInfo.destination }}
                    </span>
                    <span class="text-slate-500 text-[11px]">
                      ({{ flightDetectedInfo.departure_date || flightDetectedInfo.departure_date_raw }} - {{ flightDetectedInfo.departure_time }})
                    </span>
                  </div>

                  <div class="flex items-center gap-2">
                    <span v-if="importedManifest" class="font-bold text-slate-700 dark:text-slate-300 text-xs">
                      {{ importedManifest.total_passengers }} Pasajeros PDF ({{ importedManifest.valid_phones_count }} Celulares)
                    </span>
                    <span v-else-if="broadcastPreviewData" class="font-bold text-slate-700 dark:text-slate-300 text-xs">
                      {{ broadcastPreviewData.total_recipients }} Pasajeros CRM
                    </span>
                    
                    <!-- Botón para Modificar Datos Generales del Vuelo -->
                    <button 
                      type="button" 
                      @click="showEditFlightModal = !showEditFlightModal"
                      class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-sky-600 flex items-center gap-1 cursor-pointer transition shadow-2xs"
                      title="Modificar número de vuelo, horario o ruta"
                    >
                      <Edit3 class="w-3 h-3 text-sky-600" />
                      <span>{{ showEditFlightModal ? 'Ocultar edición vuelo' : 'Modificar Vuelo' }}</span>
                    </button>

                    <button 
                      type="button" 
                      @click="clearFlightDetected"
                      class="px-1.5 py-1 text-xs text-slate-400 hover:text-red-500 transition cursor-pointer"
                      title="Limpiar datos de vuelo"
                    >
                      ✕
                    </button>
                  </div>
                </div>

                <!-- Panel de Edición de Datos de Vuelo del Manifiesto -->
                <div v-if="showEditFlightModal" class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-emerald-500/40 space-y-2">
                  <div class="text-[11px] font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                    Modificar Parámetros Oficiales del Vuelo
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                    <div>
                      <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">N° Vuelo</label>
                      <input 
                        v-model="broadcastForm.flight_number" 
                        @input="updateFlightDetectedInfo"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-md px-2 py-1 text-xs font-mono font-bold text-slate-900 dark:text-white uppercase focus:border-sky-500 focus:outline-none"
                      />
                    </div>
                    <div>
                      <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">Origen</label>
                      <select 
                        v-model="broadcastForm.origin" 
                        @change="updateFlightDetectedInfo"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-md px-1.5 py-1 text-xs font-bold text-slate-900 dark:text-white focus:border-sky-500 focus:outline-none"
                      >
                        <option v-for="apt in broadcastAirports" :key="apt.code" :value="apt.code">{{ apt.city }} ({{ apt.code }})</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">Destino</label>
                      <select 
                        v-model="broadcastForm.destination" 
                        @change="updateFlightDetectedInfo"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-md px-1.5 py-1 text-xs font-bold text-slate-900 dark:text-white focus:border-sky-500 focus:outline-none"
                      >
                        <option v-for="apt in broadcastAirports" :key="apt.code" :value="apt.code">{{ apt.city }} ({{ apt.code }})</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">Fecha Vuelo</label>
                      <input 
                        v-model="broadcastForm.flight_date" 
                        type="date"
                        @change="updateFlightDetectedInfo"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-md px-2 py-1 text-xs font-mono text-slate-900 dark:text-white focus:border-sky-500 focus:outline-none"
                      />
                    </div>
                    <div>
                      <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">Hora Salida</label>
                      <input 
                        v-model="broadcastForm.flight_time" 
                        placeholder="15:30"
                        @input="updateFlightDetectedInfo"
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-md px-2 py-1 text-xs font-mono font-bold text-slate-900 dark:text-white focus:border-sky-500 focus:outline-none"
                      />
                    </div>
                  </div>
                </div>

                <!-- Tabla Interactiva y Editable de Pasajeros del Manifiesto -->
                <div v-if="showManifestTable && (broadcastForm.manifest_passengers?.length || importedManifest?.passengers?.length)" class="space-y-2">
                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="relative flex-1 max-w-xs">
                      <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
                      <input 
                        v-model="manifestSearchQuery"
                        type="text"
                        placeholder="Buscar en manifiesto..."
                        class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg pl-8 pr-2.5 py-1 text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                      />
                    </div>

                    <div class="flex items-center gap-2">
                      <span class="text-[11px] text-slate-500 font-mono">
                        {{ filteredManifestPassengers.length }} de {{ broadcastForm.manifest_passengers.length }} pasajeros
                      </span>
                      <button 
                        type="button" 
                        @click="addManifestPassenger"
                        class="px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs flex items-center gap-1 transition cursor-pointer shadow-xs"
                      >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Agregar Pasajero</span>
                      </button>
                    </div>
                  </div>

                  <div class="max-h-60 overflow-y-auto overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-[11px]">
                    <table class="w-full text-left">
                      <thead class="bg-slate-50 dark:bg-slate-800 text-slate-500 font-semibold sticky top-0 z-10 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                          <th class="p-1.5 w-10 text-center">N°</th>
                          <th class="p-1.5 w-20">PNR</th>
                          <th class="p-1.5">Pasajero</th>
                          <th class="p-1.5 w-36">Celular (GoIP)</th>
                          <th class="p-1.5 w-24 text-right">Acciones</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                        <tr 
                          v-for="(pax, idx) in filteredManifestPassengers" 
                          :key="pax.num || idx" 
                          class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                        >
                          <td class="p-1.5 text-center text-slate-400 text-[10px]">{{ idx + 1 }}</td>
                          
                          <!-- PNR -->
                          <td class="p-1.5 font-bold text-sky-600 dark:text-sky-400">
                            <input 
                              v-if="editingManifestIndex === idx" 
                              v-model="pax.pnr" 
                              class="w-16 bg-white dark:bg-slate-800 border border-sky-400 rounded px-1.5 py-0.5 text-xs font-mono font-bold uppercase focus:outline-none" 
                            />
                            <span v-else>{{ pax.pnr || 'N/A' }}</span>
                          </td>

                          <!-- Pasajero -->
                          <td class="p-1.5 font-sans font-semibold text-slate-900 dark:text-white">
                            <input 
                              v-if="editingManifestIndex === idx" 
                              v-model="pax.passenger_name" 
                              placeholder="Nombre y Apellido"
                              class="w-full bg-white dark:bg-slate-800 border border-sky-400 rounded px-1.5 py-0.5 text-xs font-sans font-semibold uppercase focus:outline-none" 
                            />
                            <span v-else>{{ pax.passenger_name }}</span>
                          </td>

                          <!-- Celular (GoIP) -->
                          <td class="p-1.5 font-mono">
                            <div v-if="editingManifestIndex === idx" class="flex items-center gap-1">
                              <input 
                                v-model="pax.phone" 
                                maxlength="12" 
                                placeholder="Ej: 74844348"
                                class="w-28 bg-white dark:bg-slate-800 border border-sky-400 rounded px-1.5 py-0.5 text-xs font-mono font-bold focus:outline-none" 
                              />
                            </div>
                            <div v-else class="flex items-center gap-1">
                              <span v-if="(pax.phone || '').replace(/\D/g, '').length >= 8" class="text-emerald-600 dark:text-emerald-400 font-bold">
                                {{ pax.phone }}
                              </span>
                              <span v-else class="text-amber-500 text-[10px] font-sans font-semibold flex items-center gap-0.5" title="Sin celular válido">
                                <AlertTriangle class="w-3 h-3" /> Sin celular
                              </span>
                            </div>
                          </td>

                          <!-- Acciones -->
                          <td class="p-1.5 text-right whitespace-nowrap">
                            <div v-if="editingManifestIndex === idx" class="flex items-center justify-end gap-1">
                              <button 
                                type="button" 
                                @click="saveManifestPassenger(idx)" 
                                class="px-2 py-0.5 rounded bg-emerald-600 text-white font-bold text-[10px] cursor-pointer hover:bg-emerald-500 shadow-2xs" 
                                title="Guardar cambios"
                              >
                                ✓ Guardar
                              </button>
                              <button 
                                type="button" 
                                @click="cancelEditManifestPassenger()" 
                                class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[10px] cursor-pointer" 
                                title="Cancelar"
                              >
                                ✕
                              </button>
                            </div>
                            <div v-else class="flex items-center justify-end gap-1">
                              <button 
                                type="button" 
                                @click="startEditManifestPassenger(idx)" 
                                class="p-1 text-slate-400 hover:text-sky-600 transition cursor-pointer" 
                                title="Editar pasajero"
                              >
                                <Edit3 class="w-3.5 h-3.5" />
                              </button>
                              <button 
                                type="button" 
                                @click="deleteManifestPassenger(idx)" 
                                class="p-1 text-slate-400 hover:text-rose-500 transition cursor-pointer" 
                                title="Eliminar del manifiesto"
                              >
                                <Trash2 class="w-3.5 h-3.5" />
                              </button>
                            </div>
                          </td>
                        </tr>
                        <tr v-if="filteredManifestPassengers.length === 0">
                          <td colspan="5" class="p-4 text-center text-slate-400 font-sans">
                            No se encontraron pasajeros en el manifiesto con ese filtro.
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- PASO 2: ¿QUÉ PASÓ CON EL VUELO? (DEMORA O CANCELACIÓN) -->
              <div class="space-y-3 pt-1">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    2. Situación Operativa del Vuelo
                  </label>
                  <span class="text-[11px] text-slate-400">Selecciona el motivo del aviso</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  <!-- Botón Demora / Reprogramación -->
                  <button 
                    type="button" 
                    @click="selectFlightSituation('flight_delay')"
                    :class="broadcastForm.template_type === 'flight_delay' ? 'bg-amber-500 text-white font-bold border-amber-600' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-700'"
                    class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                  >
                    <div class="font-bold text-xs">Vuelo Demorado</div>
                    <div class="text-[10px] opacity-80">Reprogramar nueva hora</div>
                  </button>

                  <!-- Botón Cancelado por Mantenimiento -->
                  <button 
                    type="button" 
                    @click="selectFlightSituation('flight_cancellation')"
                    :class="broadcastForm.template_type === 'flight_cancellation' ? 'bg-red-600 text-white font-bold border-red-700' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-700'"
                    class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                  >
                    <div class="font-bold text-xs">Cancelado (Mant.)</div>
                    <div class="text-[10px] opacity-80">Por mantenimiento</div>
                  </button>

                  <!-- Botón Cancelado por EPTAM -->
                  <button 
                    type="button" 
                    @click="selectFlightSituation('eptam_cancellation')"
                    :class="broadcastForm.template_type === 'eptam_cancellation' ? 'bg-red-600 text-white font-bold border-red-700' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-700'"
                    class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                  >
                    <div class="font-bold text-xs">Cancelado (EPTAM)</div>
                    <div class="text-[10px] opacity-80">Disposición oficial</div>
                  </button>
                </div>

                <!-- Campo de Hora Reprogramada (SI ES DEMORA) -->
                <div v-if="broadcastForm.template_type === 'flight_delay'" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-1">
                  <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-amber-900 dark:text-amber-200">
                      Nueva Hora Reprogramada de Salida:
                    </label>
                    <span class="text-[10px] text-amber-700 dark:text-amber-300">
                      Modificación manual del horario
                    </span>
                  </div>
                  <input 
                    v-model="broadcastForm.flight_time"
                    type="text"
                    placeholder="Ej: 12:00, 19:00 o 15:30"
                    class="w-full bg-white dark:bg-slate-900 border border-amber-500/40 rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:border-amber-500"
                  />
                </div>
              </div>

              <!-- Registro Manual de Pasajeros (Apellido + Celular) -->
              <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-2.5">
                <div class="flex items-center justify-between">
                  <button 
                    type="button" 
                    @click="showManualPhones = !showManualPhones"
                    class="text-xs font-bold text-sky-600 dark:text-sky-400 hover:text-sky-500 cursor-pointer flex items-center gap-1.5 transition"
                  >
                    <Plus v-if="!showManualPhones" class="w-3.5 h-3.5" />
                    <ChevronDown v-else class="w-3.5 h-3.5" />
                    <span>{{ showManualPhones ? 'Ocultar registro manual de pasajeros' : 'Registrar pasajeros adicionales manualmente (Apellido y Celular)' }}</span>
                    <span v-if="parsedManualRecipients.length" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300 font-mono font-bold">
                      {{ parsedManualRecipients.length }}
                    </span>
                  </button>


                </div>

                <div v-if="showManualPhones" class="p-3.5 rounded-xl bg-slate-50/80 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                  <!-- Formulario Rápido de 1 Clic para Agregar Pasajero -->
                  <div>
                    <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                      Agregar Pasajero Manual:
                    </label>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                      <div class="relative flex-1">
                        <input 
                          v-model="manualPassengerInput.surname"
                          type="text"
                          placeholder="Apellido del pasajero (ej: PEREZ, MAMANI)"
                          @keydown.enter.prevent="addManualPassengerRow"
                          class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 uppercase placeholder:normal-case font-sans"
                        />
                      </div>
                      <div class="relative sm:w-28">
                        <input 
                          v-model="manualPassengerInput.pnr"
                          type="text"
                          maxlength="8"
                          placeholder="PNR (optativo)"
                          @keydown.enter.prevent="addManualPassengerRow"
                          class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-mono uppercase"
                          title="Código de reserva PNR (optativo si es manual)"
                        />
                      </div>
                      <div class="relative sm:w-40">
                        <input 
                          v-model="manualPassengerInput.phone"
                          type="text"
                          maxlength="8"
                          placeholder="Celular (ej: 74844348)"
                          @keydown.enter.prevent="addManualPassengerRow"
                          class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-mono"
                        />
                      </div>
                      <button 
                        type="button"
                        @click="addManualPassengerRow"
                        class="px-3.5 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs flex items-center justify-center gap-1 transition cursor-pointer shadow-xs whitespace-nowrap"
                      >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Agregar</span>
                      </button>
                    </div>
                  </div>

                  <!-- Lista de Pasajeros Manuales Agregados -->
                  <div v-if="parsedManualRecipients.length" class="space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                      <span>Pasajeros manuales ingresados ({{ parsedManualRecipients.length }}):</span>
                      <button 
                        type="button" 
                        @click="broadcastForm.manual_phones = ''; if (broadcastPreviewData) fetchBroadcastPreview();"
                        class="text-[10px] text-rose-500 hover:underline cursor-pointer"
                      >
                        Limpiar todos
                      </button>
                    </div>

                    <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto p-1 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                      <span 
                        v-for="(pax, idx) in parsedManualRecipients" 
                        :key="idx"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-sans"
                      >
                        <strong class="text-sky-700 dark:text-sky-300 font-bold uppercase">{{ pax.surname }}</strong>
                        <span v-if="pax.pnr" class="px-1.5 py-0.2 rounded font-mono text-[10px] bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300 font-bold border border-sky-200 dark:border-sky-800" title="PNR">
                          {{ pax.pnr }}
                        </span>
                        <span class="text-slate-400">·</span>
                        <span class="font-mono text-slate-600 dark:text-slate-300">{{ pax.phone }}</span>
                        <button 
                          type="button" 
                          @click="removeManualPassengerAt(idx)" 
                          class="ml-0.5 text-slate-400 hover:text-rose-500 transition cursor-pointer"
                          title="Eliminar pasajero"
                        >
                          <X class="w-3 h-3" />
                        </button>
                      </span>
                    </div>
                  </div>

                  <!-- Opción Desplegable para Pegar Lotes de Texto -->
                  <div class="pt-1 border-t border-slate-200/60 dark:border-slate-800/60">
                    <button 
                      type="button"
                      @click="showBulkManualInput = !showBulkManualInput"
                      class="text-[11px] text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 cursor-pointer flex items-center gap-1"
                    >
                      <span>{{ showBulkManualInput ? 'Ocultar editor de texto por lote' : 'O pegar varios en lote (formato texto libre)' }}</span>
                      <ChevronDown class="w-3 h-3 transform transition-transform" :class="showBulkManualInput ? 'rotate-180' : ''" />
                    </button>

                    <div v-if="showBulkManualInput" class="mt-2 space-y-1">
                      <textarea 
                        v-model="broadcastForm.manual_phones"
                        rows="3"
                        placeholder="74844348 - PEREZ&#10;71234567 - MAMANI&#10;76543210 (o solo número)"
                        class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-mono"
                      ></textarea>
                      <p class="text-[10px] text-slate-400">
                        Puedes pegar líneas en formato: <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded">74844348 - PEREZ</code> o <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded">PEREZ, 74844348</code> o solo el número.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Botón de Previsualizar Destinatarios -->
              <div class="pt-2 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
                <span class="text-[11px] text-slate-500">Consulta los pasajeros que recibirán el SMS antes del envío.</span>
                <button 
                  type="button" 
                  @click="fetchBroadcastPreview" 
                  :disabled="broadcastPreviewLoading"
                  class="px-4 py-2 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-300 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/60 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer disabled:opacity-50"
                >
                  <Loader2 v-if="broadcastPreviewLoading" class="w-3.5 h-3.5 animate-spin" />
                  <span>{{ broadcastPreviewLoading ? 'Calculando...' : 'Previsualizar Destinatarios' }}</span>
                </button>
              </div>

              <!-- Resultados de la Previsualización -->
              <div v-if="broadcastPreviewData" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                      <span>{{ broadcastPreviewData.total_recipients }} Celular(es) Destinatario(s)</span>
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        Listos para envío
                      </span>
                    </div>
                    <div class="text-[11px] text-slate-500">
                      Tiempo estimado: ~{{ Math.ceil((broadcastPreviewData.total_recipients * (broadcastForm.goip_line === 'both' ? 7.5 : 15)) / 60) }} min 
                      <span class="text-emerald-600 dark:text-emerald-400 font-semibold" v-if="broadcastForm.goip_line === 'both'">(2X más rápido: ~7.5s por SMS intercalando)</span>
                      <span class="text-slate-400" v-else>(a 15s por SMS en un solo chip)</span>
                    </div>
                  </div>

                  <div class="text-right">
                    <div class="text-xs font-bold" :class="smsCostClass">
                      {{ broadcastPreviewData.sms_metrics?.total_sms_estimated || broadcastPreviewData.total_recipients }} SMS totales a consumir
                    </div>
                    <div class="text-[10px] text-slate-400">
                      {{ broadcastPreviewData.sms_metrics?.sms_per_recipient }} SMS por cada pasajero
                    </div>
                  </div>
                </div>

                <!-- Buscador rápido y contador -->
                <div class="flex items-center justify-between gap-2 pt-1">
                  <div class="relative flex-1 max-w-sm">
                    <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input 
                      v-model="previewSearchQuery"
                      type="text"
                      placeholder="Buscar por pasajero, teléfono o PNR..."
                      class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 placeholder:text-slate-400"
                    />
                  </div>
                  <span class="text-[11px] text-slate-500 font-mono">
                    Mostrando {{ filteredPreviewRecipients.length }} de {{ broadcastPreviewData.total_recipients }}
                  </span>
                </div>

                <!-- Tabla Completa de Destinatarios con Scroll Vertical Fluido -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden shadow-xs">
                  <div class="max-h-72 sm:max-h-80 overflow-y-auto overflow-x-auto">
                    <table class="w-full text-[11px] text-left">
                      <thead class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold uppercase sticky top-0 z-10 border-b border-slate-200 dark:border-slate-700 shadow-2xs">
                        <tr>
                          <th class="p-2.5 text-center w-10">#</th>
                          <th class="p-2.5">Pasajero</th>
                          <th class="p-2.5">Teléfono</th>
                          <th class="p-2.5">Línea SIM de Salida</th>
                          <th class="p-2.5">Vuelo</th>
                          <th class="p-2.5">Ruta</th>
                          <th class="p-2.5">PNR</th>
                          <th class="p-2.5 text-center w-16">Acción</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300 font-mono">
                        <tr 
                          v-for="(pax, idx) in filteredPreviewRecipients" 
                          :key="pax.phone + (pax.booking_reference || idx)" 
                          class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                        >
                          <td class="p-2.5 text-center text-slate-400 font-sans text-[10px]">{{ idx + 1 }}</td>
                          <td class="p-2.5 font-sans font-semibold text-slate-900 dark:text-white">
                            {{ pax.passenger_name }}
                          </td>
                          <td class="p-2.5 font-bold text-emerald-600 dark:text-emerald-400">
                            {{ pax.phone }}
                          </td>
                          <td class="p-2.5">
                            <span 
                              class="px-2 py-0.5 rounded text-[10px] font-bold font-sans inline-flex items-center gap-1.5"
                              :class="pax.assigned_line === 2 || (!pax.assigned_line && idx % 2 === 1) ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20' : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20'"
                            >
                              <span class="w-1.5 h-1.5 rounded-full" :class="pax.assigned_line === 2 || (!pax.assigned_line && idx % 2 === 1) ? 'bg-purple-500' : 'bg-indigo-500'"></span>
                              {{ (pax.assigned_line === 2 || (!pax.assigned_line && idx % 2 === 1)) ? lineAliases.line2 : lineAliases.line1 }}
                            </span>
                          </td>
                          <td class="p-2.5">{{ pax.flight_number || 'N/A' }}</td>
                          <td class="p-2.5 text-[10px] text-slate-500">
                            {{ pax.origin && pax.destination ? `${pax.origin}→${pax.destination}` : 'N/A' }}
                          </td>
                          <td class="p-2.5">
                            <span v-if="pax.booking_reference" class="font-bold text-sky-600 dark:text-sky-400">{{ pax.booking_reference }}</span>
                            <span v-else class="text-slate-400 font-mono text-[11px] italic">- (Manual)</span>
                          </td>
                          <td class="p-2.5 text-center">
                            <button 
                              type="button" 
                              @click="excludeRecipientFromPreview(pax)"
                              class="p-1 rounded text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                              title="Excluir este pasajero del envío"
                            >
                              <X class="w-3.5 h-3.5" />
                            </button>
                          </td>
                        </tr>
                        <tr v-if="filteredPreviewRecipients.length === 0">
                          <td colspan="8" class="p-6 text-center text-slate-400 font-sans">
                            No se encontraron pasajeros que coincidan con la búsqueda.
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Opciones de Despacho y Anti-Baneo -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800">
                  <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-700 dark:text-slate-300">
                    <input 
                      type="checkbox" 
                      v-model="broadcastForm.dispatch_immediate" 
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                    />
                    <span class="font-semibold">Auto-despachar inmediatamente</span>
                    <span class="text-[10px] text-slate-400 font-normal">(sin esperar cola manual)</span>
                  </label>

                  <div class="flex flex-col sm:flex-row items-center gap-2">
                    <div 
                      v-if="smsSegmentCount > 1" 
                      class="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500 text-amber-800 dark:text-amber-200 text-[11px] font-bold flex items-center gap-1.5 animate-pulse"
                      title="Excede los 160 caracteres y consumirá más SMS por pasajero"
                    >
                      <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                      <span>Excede 160 caracs. (Cobrará {{ smsSegmentCount }} SMS / pasajero)</span>
                    </div>

                    <!-- Botón de Disparo Principal -->
                    <button 
                      type="button" 
                      @click="submitBroadcastCampaign" 
                      :disabled="broadcastSubmitting || broadcastPreviewData.total_recipients === 0"
                      class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-white font-bold text-xs flex items-center justify-center gap-2 transition cursor-pointer disabled:opacity-50"
                      :class="smsSegmentCount > 1 ? 'bg-amber-600 hover:bg-amber-500 shadow-sm' : 'bg-emerald-600 hover:bg-emerald-500'"
                    >
                      <Loader2 v-if="broadcastSubmitting" class="w-4 h-4 animate-spin" />
                      <span>Iniciar Envío Masivo a {{ broadcastPreviewData.total_recipients }} Pasajero(s)</span>
                    </button>
                  </div>
                </div>

                <!-- Tarjeta de Cumplimiento Normativo Entel y Dual-SIM GoIP -->
                <div class="p-2.5 rounded-xl bg-sky-50/60 dark:bg-sky-950/30 border border-sky-100 dark:border-sky-900/50 flex items-start gap-2.5 text-[11px] text-sky-800 dark:text-sky-300">
                  <ShieldCheck class="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" />
                  <div class="space-y-0.5 flex-1">
                    <div class="font-bold flex items-center gap-1.5 flex-wrap">
                      <span v-if="broadcastForm.goip_line === 'both'">Intercalado Dual-SIM Activo:</span>
                      <span v-else>Salida Exclusiva por:</span>

                      <span v-if="broadcastForm.goip_line === 'both' || broadcastForm.goip_line === '1'" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                        {{ lineAliases.line1 }}
                      </span>
                      <span v-if="broadcastForm.goip_line === 'both'" class="text-slate-400 text-[10px]">⇄</span>
                      <span v-if="broadcastForm.goip_line === 'both' || broadcastForm.goip_line === '2'" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                        {{ lineAliases.line2 }}
                      </span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 text-[11px] leading-relaxed">
                      <span v-if="broadcastForm.goip_line === 'both'">
                        Los envíos se alternan automáticamente 50/50 entre ambas líneas a ~7.5s por despacho (2X más rápido en total), manteniendo intervalos reales de 15 a 20s de reposo en cada chip para máxima protección contra baneos de Entel / ATT.
                      </span>
                      <span v-else>
                        Los mensajes se transmitirán únicamente por {{ broadcastForm.goip_line === '1' ? lineAliases.line1 : lineAliases.line2 }} con pausas dinámicas de 12 a 22s y enfriamiento GSM anti-baneo.
                      </span>
                    </p>
                  </div>
                </div>
              </div>

            </div>

            <!-- Columna Derecha: Redactor de SMS y Vista Previa (5 cols) -->
            <div class="lg:col-span-5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
              
              <div class="pb-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                  3. Línea Móvil (Chip SIM) y Mensaje
                </h3>
                
                <!-- Badge Destacado de Contador y Segmentos de SMS -->
                <div class="flex items-center gap-1.5">
                  <span 
                    v-if="smsSegmentCount === 1"
                    class="px-2.5 py-1 rounded-full text-[11px] font-bold font-mono bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/25 inline-flex items-center gap-1"
                    title="Longitud óptima: Se cobrará exactamente 1 SMS por pasajero"
                  >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    <span>{{ smsCharCount }}/160 caracs. (1 SMS)</span>
                  </span>
                  <span 
                    v-else-if="smsSegmentCount === 2"
                    class="px-2.5 py-1 rounded-full text-[11px] font-extrabold font-mono bg-amber-500/20 text-amber-700 dark:text-amber-300 border-2 border-amber-500 animate-pulse inline-flex items-center gap-1 shadow-xs"
                    title="¡Advertencia! Excede 160 caracteres y se cobrarán 2 SMS por pasajero"
                  >
                    <AlertTriangle class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                    <span>{{ smsCharCount }} caracs. (⚠️ 2 SMS/pax)</span>
                  </span>
                  <span 
                    v-else
                    class="px-2.5 py-1 rounded-full text-[11px] font-black font-mono bg-rose-500/20 text-rose-700 dark:text-rose-300 border-2 border-rose-500 animate-bounce inline-flex items-center gap-1 shadow-sm"
                    title="¡Atención crítica! Mensaje muy largo, se cobrarán 3 o más SMS por pasajero"
                  >
                    <AlertCircle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                    <span>{{ smsCharCount }} caracs. (🚨 {{ smsSegmentCount }} SMS/pax)</span>
                  </span>
                </div>
              </div>

              <!-- Selector de Línea de Salida (Chips SIM Bautizados) -->
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between mb-0.5">
                  <div class="flex items-center gap-1.5">
                    <Smartphone class="w-3.5 h-3.5 text-sky-500" />
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                      Línea de Salida (Chip SIM):
                    </label>
                  </div>
                  <button
                    type="button"
                    @click="openLineNamingModal"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/40 transition cursor-pointer"
                    title="Personalizar los nombres o números de las líneas SIM"
                  >
                    <Edit3 class="w-3 h-3" />
                    <span>Bautizar / Nombrar Líneas</span>
                  </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 text-center">
                  <button
                    type="button"
                    @click="broadcastForm.goip_line = 'both'; onPortChange();"
                    class="px-2 py-2 rounded-lg text-xs font-bold border transition cursor-pointer flex flex-col items-center justify-center gap-0.5"
                    :class="broadcastForm.goip_line === 'both' 
                      ? 'bg-sky-500/15 border-sky-500 text-sky-700 dark:text-sky-300 font-extrabold shadow-2xs' 
                      : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-sky-300'"
                  >
                    <span class="truncate max-w-full">Ambas Líneas</span>
                    <span class="text-[9px] font-normal opacity-75">50/50 Balanceado</span>
                  </button>

                  <button
                    type="button"
                    @click="broadcastForm.goip_line = '1'; onPortChange();"
                    class="px-2 py-2 rounded-lg text-xs font-bold border transition cursor-pointer flex flex-col items-center justify-center gap-0.5"
                    :class="broadcastForm.goip_line === '1' 
                      ? 'bg-indigo-500/15 border-indigo-500 text-indigo-700 dark:text-indigo-300 font-extrabold shadow-2xs' 
                      : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-indigo-300'"
                    :title="lineAliases.line1"
                  >
                    <div class="flex items-center gap-1 max-w-full">
                      <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                      <span class="truncate text-[11px]">{{ lineAliases.line1 }}</span>
                    </div>
                    <span class="text-[9px] font-normal opacity-75">Solo Chip 1</span>
                  </button>

                  <button
                    type="button"
                    @click="broadcastForm.goip_line = '2'; onPortChange();"
                    class="px-2 py-2 rounded-lg text-xs font-bold border transition cursor-pointer flex flex-col items-center justify-center gap-0.5"
                    :class="broadcastForm.goip_line === '2' 
                      ? 'bg-purple-500/15 border-purple-500 text-purple-700 dark:text-purple-300 font-extrabold shadow-2xs' 
                      : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-purple-300'"
                    :title="lineAliases.line2"
                  >
                    <div class="flex items-center gap-1 max-w-full">
                      <span class="w-1.5 h-1.5 rounded-full bg-purple-500 shrink-0"></span>
                      <span class="truncate text-[11px]">{{ lineAliases.line2 }}</span>
                    </div>
                    <span class="text-[9px] font-normal opacity-75">Solo Chip 2</span>
                  </button>
                </div>

              </div>

              <!-- Textarea del SMS con Barra de Progreso y Alerta Visual Prominente -->
              <div class="space-y-1.5">
                <div class="flex items-center justify-between text-[11px]">
                  <label class="font-semibold text-slate-600 dark:text-slate-400">
                    Texto del mensaje:
                  </label>
                  <span class="text-[10px] font-mono text-slate-400">
                    {{ smsCharCount <= 160 ? `${160 - smsCharCount} caracs. restantes para 1 SMS` : `Excedido por ${smsCharCount - 160} caracs.` }}
                  </span>
                </div>

                <!-- Barra de Progreso Visual de 160 caracteres -->
                <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                  <div 
                    class="h-full transition-all duration-200"
                    :class="{
                      'bg-emerald-500': smsCharCount <= 160,
                      'bg-amber-500': smsCharCount > 160 && smsCharCount <= 306,
                      'bg-rose-500': smsCharCount > 306
                    }"
                    :style="{ width: `${Math.min(100, (smsCharCount / 160) * 100)}%` }"
                  ></div>
                </div>

                <div class="relative">
                  <!-- Editor Enriquecido con Variables Atómicas e Indivisibles -->
                  <div 
                    ref="smsEditorRef"
                    contenteditable="true"
                    @input="onEditorInput"
                    @paste="onEditorPaste"
                    data-placeholder="Escribe el mensaje SMS para los pasajeros..."
                    class="w-full min-h-[140px] max-h-72 overflow-y-auto rounded-xl p-3 text-xs text-slate-900 dark:text-white font-mono leading-relaxed transition-all focus:outline-none whitespace-pre-wrap break-words cursor-text empty:before:content-[attr(data-placeholder)] empty:before:text-slate-400 empty:before:pointer-events-none"
                    :class="{
                      'bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-sky-500 focus:ring-1 focus:ring-sky-500': smsSegmentCount === 1,
                      'bg-amber-50/20 dark:bg-amber-950/20 border-2 border-amber-500 dark:border-amber-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 shadow-[0_0_12px_rgba(245,158,11,0.2)]': smsSegmentCount === 2,
                      'bg-rose-50/20 dark:bg-rose-950/20 border-2 border-rose-500 dark:border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 shadow-[0_0_15px_rgba(244,63,94,0.3)]': smsSegmentCount >= 3
                    }"
                  ></div>
                </div>

                <!-- BANNER DE ALERTA DE ALTO IMPACTO (Visible si excede 1 SMS) -->
                <div 
                  v-if="smsSegmentCount > 1" 
                  class="p-4 rounded-xl border-2 transition-all space-y-3 shadow-xs"
                  :class="smsSegmentCount === 2 
                    ? 'bg-amber-500/10 border-amber-500 text-amber-950 dark:text-amber-100' 
                    : 'bg-rose-500/10 border-rose-500 text-rose-950 dark:text-rose-100'"
                >
                  <div class="flex items-start gap-3">
                    <div 
                      class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-black text-white text-base shadow-sm"
                      :class="smsSegmentCount === 2 ? 'bg-amber-500' : 'bg-rose-500 animate-pulse'"
                    >
                      !
                    </div>
                    <div class="space-y-1 flex-1">
                      <div class="flex items-center justify-between gap-2 flex-wrap">
                        <h4 class="font-black text-xs uppercase tracking-wide flex items-center gap-1.5 text-amber-800 dark:text-amber-300">
                          <span>¡ALERTA: HAS EXCEDIDO LOS 160 CARACTERES!</span>
                        </h4>
                      </div>
                      <p class="text-xs leading-relaxed">
                        Escribiste <strong>{{ smsCharCount }} caracteres</strong> (excediste el límite por <strong>{{ smsCharCount - 160 }} caracteres</strong>).
                        Según esta cantidad de caracteres, las empresas telefónicas te cobrarán 
                        <strong class="underline decoration-2 font-bold">{{ smsSegmentCount }} MENSAJES SMS</strong> por cada pasajero.
                      </p>
                    </div>
                  </div>

                  <!-- Escala de cobro según cantidad de caracteres -->
                  <div class="p-3 rounded-xl bg-white/90 dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                    <div class="font-bold text-[10px] uppercase tracking-wider text-slate-500">
                      Escala de cobro telefónico según caracteres:
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-center text-[11px]">
                      <div class="p-2 rounded-lg border bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800">
                        <div class="text-slate-400 font-mono text-[10px]">1 a 160 caracs.</div>
                        <div class="font-bold text-slate-700 dark:text-slate-300">Cuesta 1 SMS</div>
                      </div>
                      <div 
                        class="p-2 rounded-lg border transition-all"
                        :class="smsSegmentCount === 2 
                          ? 'border-amber-500 bg-amber-500/15 font-black text-amber-700 dark:text-amber-300 ring-2 ring-amber-500/30' 
                          : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500'"
                      >
                        <div class="font-mono text-[10px]">161 a 306 caracs.</div>
                        <div class="font-bold">Cuesta 2 SMS {{ smsSegmentCount === 2 ? '👈 (Tu caso)' : '' }}</div>
                      </div>
                      <div 
                        class="p-2 rounded-lg border transition-all"
                        :class="smsSegmentCount >= 3 
                          ? 'border-rose-500 bg-rose-500/15 font-black text-rose-700 dark:text-rose-300 ring-2 ring-rose-500/30' 
                          : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500'"
                      >
                        <div class="font-mono text-[10px]">307+ caracs.</div>
                        <div class="font-bold">Cuesta 3+ SMS {{ smsSegmentCount >= 3 ? '👈 (Tu caso)' : '' }}</div>
                      </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] font-mono flex-wrap gap-2">
                      <span>Costo por pasajero: <strong>{{ smsSegmentCount }} SMS</strong></span>
                      <span v-if="broadcastPreviewData?.total_recipients > 0" class="text-rose-600 dark:text-rose-400 font-bold">
                        Total de saldo a gastar: {{ broadcastPreviewData.total_recipients * smsSegmentCount }} SMS (en lugar de {{ broadcastPreviewData.total_recipients }})
                      </span>
                    </div>
                  </div>
                </div>

              </div>

              <!-- Alertas de Normativas ATT / Entel en Vivo -->
              <div v-if="!messageCompliance.hasIdentity" class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-[11px] text-amber-800 dark:text-amber-300 flex items-start gap-2">
                <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                <div>
                  <strong>Recomendación ATT / Entel:</strong> Inicie con <code class="font-mono bg-white dark:bg-slate-900 px-1 py-0.5 rounded">TAMep Informa:</code> o <code class="font-mono bg-white dark:bg-slate-900 px-1 py-0.5 rounded">EPTAM:</code>. Los envíos anónimos activan filtros anti-spam de la operadora.
                </div>
              </div>

              <div v-if="messageCompliance.hasShortener" class="p-2.5 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 text-[11px] text-red-800 dark:text-red-300 flex items-start gap-2">
                <AlertCircle class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                <div>
                  <strong>Alerta de Bloqueo Inmediato:</strong> No use enlaces acortados (bit.ly, tinyurl). Entel suspende automáticamente líneas que difunden enlaces de acortador.
                </div>
              </div>

              <!-- Botones para Insertar Etiquetas Dinámicas -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <span class="block text-[11px] font-semibold text-slate-500">Insertar variables:</span>
                  <span class="text-[10px] text-slate-400">Clic para insertar en el cursor</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <button 
                    v-for="tag in availableSmsTags"
                    :key="tag"
                    type="button"
                    @click="insertSmsTag(tag)"
                    class="px-2.5 py-1 rounded-lg text-xs font-mono font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer hover:border-slate-300 dark:hover:border-slate-600 active:scale-95 shadow-2xs"
                    :title="'Insertar ' + tag + ' en la posición del cursor'"
                  >
                    +{{ tag }}
                  </button>
                </div>
              </div>

              <!-- Vista Previa Limpia del Texto (Sin simulador falso de teléfono) -->
              <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                <div class="flex items-center justify-between text-[11px] text-slate-500">
                  <span class="font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Vista Previa del Mensaje</span>
                  <span class="font-mono text-[10px]">Texto recibido por el pasajero</span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 font-mono whitespace-pre-wrap break-words leading-relaxed">
                  {{ previewSimulatedMessage || 'Escribe tu mensaje en la plantilla para previsualizar...' }}
                </div>
              </div>

            </div>

          </div>

          <!-- Tabla de Historial de Campañas SMS -->
          <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 flex-wrap gap-2">
              <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                  Historial de Envíos Masivos
                </h3>
                <p class="text-[11px] text-slate-500">Registro de auditoría y reportes de entrega celular</p>
              </div>

              <div class="flex items-center gap-2">
                <button 
                  type="button" 
                  @click="triggerProcessQueue" 
                  :disabled="processingQueue"
                  class="px-3 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-xs text-white font-semibold flex items-center gap-1.5 transition cursor-pointer"
                >
                  <Loader2 v-if="processingQueue" class="w-3.5 h-3.5 animate-spin" />
                  <span>{{ processingQueue ? 'Despachando...' : 'Procesar Cola SMS' }}</span>
                </button>

                <button 
                  type="button" 
                  @click="fetchBroadcastCampaigns" 
                  :disabled="broadcastLoading"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs text-slate-700 dark:text-slate-300 font-semibold transition cursor-pointer"
                >
                  <span>{{ broadcastLoading ? 'Cargando...' : 'Actualizar' }}</span>
                </button>
              </div>
            </div>

            <!-- Barra de Filtros por Vuelo y Día -->
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
              <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1.5">
                  <label class="font-bold text-slate-700 dark:text-slate-300">N° Vuelo:</label>
                  <input 
                    v-model="broadcastFilters.flight_number"
                    type="text"
                    placeholder="Ej: 708"
                    @keyup.enter="fetchBroadcastCampaigns"
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1 text-xs font-mono font-bold uppercase w-24 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                  />
                </div>

                <div class="flex items-center gap-1.5">
                  <label class="font-bold text-slate-700 dark:text-slate-300">Fecha:</label>
                  <input 
                    v-model="broadcastFilters.date"
                    type="date"
                    @change="fetchBroadcastCampaigns"
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                  />
                </div>

                <button 
                  type="button" 
                  @click="fetchBroadcastCampaigns"
                  :disabled="broadcastLoading"
                  class="px-3 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs transition cursor-pointer disabled:opacity-50"
                >
                  Filtrar
                </button>

                <button 
                  v-if="broadcastFilters.flight_number || broadcastFilters.date"
                  type="button" 
                  @click="clearBroadcastFilters"
                  class="px-2.5 py-1 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition cursor-pointer"
                >
                  Limpiar
                </button>
              </div>

              <div v-if="broadcastFilters.flight_number || broadcastFilters.date" class="text-[11px] text-slate-500 font-mono">
                Filtro activo: {{ broadcastFilters.flight_number ? 'Vuelo ' + broadcastFilters.flight_number : '' }} {{ broadcastFilters.date ? (broadcastFilters.flight_number ? '· ' : '') + broadcastFilters.date : '' }}
              </div>
            </div>

            <!-- Tabla de Campañas -->
            <div v-if="broadcastCampaigns.length" class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
              <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold uppercase text-[11px]">
                  <tr>
                    <th class="p-3">ID / Fecha</th>
                    <th class="p-3">Motivo / Título</th>
                    <th class="p-3">Destinatarios</th>
                    <th class="p-3">Progreso de Entrega</th>
                    <th class="p-3">Estado</th>
                    <th class="p-3 text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                  <tr v-for="c in broadcastCampaigns" :key="c.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                    <td class="p-3 font-mono">
                      <div class="font-bold text-slate-900 dark:text-white">#{{ c.id }}</div>
                      <div class="text-[10px] text-slate-400">{{ formatBroadcastDate(c.created_at) }}</div>
                    </td>
                    <td class="p-3">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-slate-900 dark:text-white">{{ c.title }}</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                          SMS GoIP
                        </span>

                        <!-- Badge Línea SIM / Puerto GoIP -->
                        <span v-if="c.filters?.goip_line === '1'" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20" :title="'Línea 1: ' + lineAliases.line1">
                          {{ lineAliases.line1 }}
                        </span>
                        <span v-else-if="c.filters?.goip_line === '2'" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20" :title="'Línea 2: ' + lineAliases.line2">
                          {{ lineAliases.line2 }}
                        </span>
                        <span v-else class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20" title="Intercalado entre ambas líneas">
                          Ambas Líneas (50/50)
                        </span>
                      </div>
                      <div class="text-[11px] text-slate-500 truncate max-w-xs mt-0.5">{{ c.message }}</div>
                    </td>
                    <td class="p-3 font-mono">
                      <span class="font-bold text-slate-900 dark:text-white">{{ c.total_recipients }}</span> pasajeros
                    </td>
                    <td class="p-3 min-w-[160px]">
                      <div class="flex items-center justify-between text-[11px] mb-1 font-mono">
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ c.sent_count }} enviados</span>
                        <span v-if="c.failed_count > 0" class="text-red-500 font-bold">{{ c.failed_count }} fallidos</span>
                      </div>
                      <div class="w-full bg-slate-200 dark:bg-slate-800 h-2 rounded-full overflow-hidden flex">
                        <div 
                          class="bg-emerald-500 h-full transition-all duration-300" 
                          :style="{ width: c.total_recipients > 0 ? ((c.sent_count / c.total_recipients) * 100) + '%' : '0%' }"
                        ></div>
                        <div 
                          class="bg-red-500 h-full transition-all duration-300" 
                          :style="{ width: c.total_recipients > 0 ? ((c.failed_count / c.total_recipients) * 100) + '%' : '0%' }"
                        ></div>
                      </div>
                    </td>
                    <td class="p-3">
                      <span 
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                        :class="[
                          c.status === 'completed' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' :
                          c.status === 'processing' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20' :
                          c.status === 'cancelled' ? 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20' :
                          'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                        ]"
                      >
                        {{ c.status === 'completed' ? 'Completado' : (c.status === 'processing' ? 'En cola GoIP' : (c.status === 'cancelled' ? 'Cancelado' : 'Pendiente')) }}
                      </span>
                    </td>
                    <td class="p-3 text-right">
                      <div class="flex items-center justify-end gap-1.5">
                        <button 
                          v-if="['processing', 'pending'].includes(c.status)"
                          type="button" 
                          @click="cancelBroadcastCampaign(c)"
                          class="px-2 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400 text-xs font-semibold transition cursor-pointer"
                          title="Detiene el envío de mensajes pendientes"
                        >
                          Cancelar
                        </button>
                        <button 
                          type="button" 
                          @click="openCampaignDetails(c)"
                          class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer"
                        >
                          Ver detalle
                        </button>
                        <button 
                          v-if="c.failed_count > 0 && c.status !== 'cancelled'"
                          type="button" 
                          @click="retryFailedCampaign(c)"
                          class="px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 text-xs font-semibold transition cursor-pointer"
                        >
                          Reintentar
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Controles de Paginación del Historial -->
            <div 
              v-if="broadcastCampaigns.length" 
              class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200 dark:border-slate-800 text-xs"
            >
              <div class="text-slate-500 dark:text-slate-400 text-[11px]">
                Mostrando <strong class="font-bold text-slate-800 dark:text-slate-200">{{ broadcastPagination.from }}</strong> a 
                <strong class="font-bold text-slate-800 dark:text-slate-200">{{ broadcastPagination.to }}</strong> de 
                <strong class="font-bold text-slate-800 dark:text-slate-200">{{ broadcastPagination.total }}</strong> campañas registradas
              </div>

              <div v-if="broadcastPagination.last_page > 1" class="flex items-center gap-1.5">
                <button
                  type="button"
                  :disabled="broadcastPagination.current_page <= 1 || broadcastLoading"
                  @click="changeBroadcastPage(broadcastPagination.current_page - 1)"
                  class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer flex items-center gap-1 font-medium"
                >
                  <ChevronLeft class="w-3.5 h-3.5" />
                  <span>Anterior</span>
                </button>

                <div class="flex items-center gap-1 px-1">
                  <span class="text-[11px] font-mono text-slate-500">
                    Página <strong class="text-slate-900 dark:text-white">{{ broadcastPagination.current_page }}</strong> de <strong class="text-slate-900 dark:text-white">{{ broadcastPagination.last_page }}</strong>
                  </span>
                </div>

                <button
                  type="button"
                  :disabled="broadcastPagination.current_page >= broadcastPagination.last_page || broadcastLoading"
                  @click="changeBroadcastPage(broadcastPagination.current_page + 1)"
                  class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer flex items-center gap-1 font-medium"
                >
                  <span>Siguiente</span>
                  <ChevronRight class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Estado Vacío -->
            <div v-else class="p-6 text-center text-slate-500 dark:text-slate-400 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 text-xs">
              Aún no se han emitido campañas masivas por SMS. Configura una arriba para comenzar.
            </div>
          </div>

        </div>

        <!-- ========================================================
             PESTAÑA: AVISOS CALL CENTER (ISSABEL PBX)
             ======================================================== -->
        <div v-if="activeTab === 'callcenter'" class="space-y-6">
          <CallCenterBroadcastPanel />
        </div>

      </div>
    </main>

    <!-- ========================================================
         MODAL CUSTOM: CAMBIAR CONTRASEÑA DE AGENTE
         ======================================================== -->
    <Transition enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div 
        v-if="passwordModal.isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm"
        @click.self="closePasswordModal"
      >
        <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-6 space-y-5 animate-slide-up">
          <!-- Cabecera del Modal -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-600 dark:text-sky-400">
                <KeyRound class="w-5 h-5" />
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Actualizar Contraseña</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ passwordModal.agent?.name }}</p>
              </div>
            </div>
            <button 
              @click="closePasswordModal" 
              class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Formulario -->
          <form @submit.prevent="submitChangePassword" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                Nueva Contraseña
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                  <Lock class="w-4 h-4" />
                </div>
                <input 
                  v-model="passwordModal.newPassword"
                  :type="passwordModal.showPassword ? 'text' : 'password'"
                  required
                  placeholder="Mínimo 6 caracteres"
                  minlength="6"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-10 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-sky-500 font-mono"
                  autofocus
                />
                <button 
                  type="button" 
                  tabindex="-1" 
                  @click="passwordModal.showPassword = !passwordModal.showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-sky-500 cursor-pointer"
                >
                  <EyeOff v-if="passwordModal.showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                El usuario podrá iniciar sesión de inmediato con esta nueva contraseña.
              </p>
            </div>

            <!-- Error dentro del modal -->
            <div v-if="passwordModal.error" class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-600 dark:text-red-300 flex items-center gap-2">
              <AlertCircle class="w-4 h-4 shrink-0 text-red-500 dark:text-red-400" />
              <span>{{ passwordModal.error }}</span>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end gap-2.5 pt-2">
              <button 
                type="button" 
                @click="closePasswordModal" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer"
              >
                Cancelar
              </button>
              <button 
                type="submit" 
                :disabled="passwordModal.loading"
                class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <Loader2 v-if="passwordModal.loading" class="w-4 h-4 animate-spin" />
                <span>{{ passwordModal.loading ? 'Guardando...' : 'Guardar Contraseña' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ========================================================
         MODAL CUSTOM: CONFIRMAR SUSPENSIÓN / ACTIVACIÓN DE AGENTE
         ======================================================== -->
    <Transition enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div 
        v-if="statusConfirmModal.isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm"
        @click.self="statusConfirmModal.isOpen = false"
      >
        <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-6 space-y-4 animate-slide-up">
          <div class="flex items-start gap-3">
            <div 
              class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
              :class="statusConfirmModal.agent?.is_active ? 'bg-red-500/10 border border-red-500/20 text-red-500 dark:text-red-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 dark:text-emerald-400'"
            >
              <AlertTriangle v-if="statusConfirmModal.agent?.is_active" class="w-5 h-5" />
              <CheckCircle2 v-else class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                {{ statusConfirmModal.agent?.is_active ? '¿Suspender acceso al agente?' : '¿Reactivar cuenta del agente?' }}
              </h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                <template v-if="statusConfirmModal.agent?.is_active">
                  El agente <strong class="text-slate-900 dark:text-white">{{ statusConfirmModal.agent?.name }}</strong> no podrá acceder al CRM ni responder chats hasta ser reactivado.
                </template>
                <template v-else>
                  El agente <strong class="text-slate-900 dark:text-white">{{ statusConfirmModal.agent?.name }}</strong> recuperará el acceso inmediato a la plataforma.
                </template>
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-200 dark:border-slate-800">
            <button 
              type="button" 
              @click="statusConfirmModal.isOpen = false"
              class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer"
            >
              Cancelar
            </button>
            <button 
              type="button"
              :disabled="statusConfirmModal.loading"
              @click="confirmToggleStatus"
              :class="[
                'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50',
                statusConfirmModal.agent?.is_active 
                  ? 'bg-red-600 hover:bg-red-500 text-white' 
                  : 'bg-emerald-600 hover:bg-emerald-500 text-white'
              ]"
            >
              <Loader2 v-if="statusConfirmModal.loading" class="w-4 h-4 animate-spin" />
              <span>
                {{ statusConfirmModal.agent?.is_active ? 'Sí, Suspender' : 'Sí, Activar' }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ========================================================
         MODAL CUSTOM: CONFIRMAR ELIMINACIÓN PERMANENTE DE AGENTE
         ======================================================== -->
    <Transition enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div 
        v-if="deleteConfirmModal.isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm"
        @click.self="deleteConfirmModal.isOpen = false"
      >
        <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-6 space-y-4 animate-slide-up">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400">
              <AlertTriangle class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                ¿Eliminar agente permanentemente?
              </h3>
              <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                ¿Estás seguro de que deseas eliminar la cuenta de <strong class="text-slate-900 dark:text-white">{{ deleteConfirmModal.agent?.name }}</strong> (<span class="font-mono text-[11px]">{{ deleteConfirmModal.agent?.email }}</span>)?
              </p>
              <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-2 bg-amber-500/10 p-2.5 rounded-xl border border-amber-500/20">
                ⚠️ Las conversaciones asignadas a este agente quedarán desvinculadas y disponibles en cola para que otro asesor las atienda. Esta acción no se puede deshacer.
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-200 dark:border-slate-800">
            <button 
              type="button" 
              @click="deleteConfirmModal.isOpen = false"
              :disabled="deleteConfirmModal.loading"
              class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer"
            >
              Cancelar
            </button>
            <button 
              type="button"
              :disabled="deleteConfirmModal.loading"
              @click="confirmDeleteAgent"
              class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 bg-red-600 hover:bg-red-500 text-white shadow-md shadow-red-600/30"
            >
              <Loader2 v-if="deleteConfirmModal.loading" class="w-4 h-4 animate-spin" />
              <Trash2 v-else class="w-3.5 h-3.5" />
              <span>
                {{ deleteConfirmModal.loading ? 'Eliminando...' : 'Sí, Eliminar' }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ========================================================
         MODAL: REGISTRAR NUEVO PASAJERO / BOLETO
         ======================================================== -->
    <Transition enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div 
        v-if="newPassengerModal.isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm overflow-y-auto"
        @click.self="closeNewPassengerModal"
      >
        <div class="w-full max-w-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-5 sm:p-6 space-y-4 my-8 max-h-[90vh] overflow-y-auto animate-slide-up">
          <!-- Encabezado Modal -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                <UserPlus class="w-5 h-5" />
              </div>
              <div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Registrar Pasajero / Boleto</h3>
                <p class="text-[11px] text-slate-500">Ingresa la información para registrar la reserva y emitir boleto</p>
              </div>
            </div>
            <button 
              type="button" 
              @click="closeNewPassengerModal"
              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Formulario -->
          <form @submit.prevent="submitNewPassenger" class="space-y-4 text-xs">
            <!-- Datos del Pasajero -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="sm:col-span-2">
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Nombre Completo del Pasajero *</label>
                <input 
                  v-model="newPassengerModal.form.passenger_name" 
                  type="text" 
                  required 
                  placeholder="Ej. Juan Carlos Pérez Morales"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                />
              </div>

              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">CI / Pasaporte *</label>
                <input 
                  v-model="newPassengerModal.form.passport_number" 
                  type="text" 
                  required 
                  placeholder="Ej. 8493021 SC"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-sky-500"
                />
              </div>

              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Teléfono WhatsApp *</label>
                <input 
                  v-model="newPassengerModal.form.phone" 
                  type="text" 
                  required 
                  placeholder="Ej. 59178901234"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-sky-500"
                />
              </div>

              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Cantidad de Pasajeros (PAX)</label>
                <input 
                  v-model.number="newPassengerModal.form.passengers_count" 
                  type="number" 
                  min="1" 
                  max="20"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-sky-500"
                />
              </div>

              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Estado Inicial</label>
                <select 
                  v-model="newPassengerModal.form.status"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                >
                  <option value="nuevo">Pendiente / Nuevo</option>
                  <option value="contactado">En Gestión / Contactado</option>
                  <option value="en_emision">En Emisión</option>
                  <option value="emitido">Boleto Emitido</option>
                </select>
              </div>
            </div>

            <!-- Detalles del Vuelo -->
            <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
              <h4 class="font-bold text-slate-800 dark:text-slate-200 mb-2.5 flex items-center gap-1.5">
                <Plane class="w-3.5 h-3.5 text-sky-500" />
                <span>Itinerario y Vuelo</span>
              </h4>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Origen *</label>
                  <select 
                    v-model="newPassengerModal.form.origin" 
                    required 
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-medium"
                  >
                    <option v-for="apt in airportOptions" :key="apt.code" :value="apt.code">{{ apt.name }}</option>
                  </select>
                </div>

                <div>
                  <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Destino *</label>
                  <select 
                    v-model="newPassengerModal.form.destination" 
                    required 
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-medium"
                  >
                    <option v-for="apt in airportOptions" :key="apt.code" :value="apt.code">{{ apt.name }}</option>
                  </select>
                </div>

                <div>
                  <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Fecha de Vuelo *</label>
                  <input 
                    v-model="newPassengerModal.form.flight_date" 
                    type="date" 
                    required 
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-mono"
                  />
                </div>

                <div>
                  <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Número de Vuelo</label>
                  <input 
                    v-model="newPassengerModal.form.flight_number" 
                    type="text" 
                    placeholder="Ej. OB-302 o TAMEP-101"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 font-mono"
                  />
                </div>

                <div>
                  <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Código PNR / Localizador</label>
                  <input 
                    v-model="newPassengerModal.form.booking_reference" 
                    type="text" 
                    placeholder="Ej. TAM-78921"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono uppercase focus:outline-none focus:border-sky-500"
                  />
                </div>

                <div>
                  <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Tarifa Total (BOB)</label>
                  <input 
                    v-model.number="newPassengerModal.form.total_price" 
                    type="number" 
                    step="0.01" 
                    placeholder="0.00"
                    class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-sky-500"
                  />
                </div>
              </div>

              <div class="mt-3">
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Observaciones / Detalles</label>
                <textarea 
                  v-model="newPassengerModal.form.details" 
                  rows="2" 
                  placeholder="Equipaje especial, preferencias de asiento, notas del pasajero..."
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 resize-none"
                ></textarea>
              </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200 dark:border-slate-800">
              <button 
                type="button" 
                @click="closeNewPassengerModal" 
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition cursor-pointer"
              >
                Cancelar
              </button>
              <button 
                type="submit" 
                :disabled="newPassengerModal.loading"
                class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-md shadow-sky-500/20"
              >
                <Loader2 v-if="newPassengerModal.loading" class="w-4 h-4 animate-spin" />
                <span>{{ newPassengerModal.loading ? 'Guardando...' : 'Registrar Pasajero' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ========================================================
         MODAL: DETALLES Y GESTIÓN DE FICHA DEL PASAJERO
         ======================================================== -->
    <Transition enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div 
        v-if="passengerDetailsModal.isOpen && passengerDetailsModal.lead" 
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm overflow-y-auto"
        @click.self="closePassengerDetails"
      >
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-5 sm:p-6 space-y-4 my-8 max-h-[90vh] overflow-y-auto animate-slide-up">
          <!-- Encabezado Modal -->
          <div class="flex items-start justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                  {{ passengerDetailsModal.lead.passenger_name }}
                </h3>
                <span 
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                  :class="[
                    passengerDetailsModal.lead.status === 'emitido' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30' :
                    passengerDetailsModal.lead.status === 'contactado' ? 'bg-sky-500/10 text-sky-600 border-sky-500/30' :
                    passengerDetailsModal.lead.status === 'en_emision' ? 'bg-indigo-500/10 text-indigo-600 border-indigo-500/30' :
                    'bg-amber-500/10 text-amber-600 border-amber-500/30'
                  ]"
                >
                  {{ passengerDetailsModal.lead.status }}
                </span>
              </div>
              <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                Registrado: {{ formatTime(passengerDetailsModal.lead.created_at) }}
              </p>
            </div>
            <button 
              type="button" 
              @click="closePassengerDetails"
              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Cuadrícula de Información Resumida -->
          <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 dark:bg-slate-950 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">CI / Documento</span>
              <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ passengerDetailsModal.lead.passport_number || 'No especificado' }}</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">WhatsApp</span>
              <a 
                :href="'https://wa.me/' + String(passengerDetailsModal.lead.phone).replace(/\D/g, '')" 
                target="_blank"
                class="font-bold text-emerald-600 dark:text-emerald-400 hover:underline font-mono flex items-center gap-1"
              >
                <Phone class="w-3 h-3" />
                <span>+{{ passengerDetailsModal.lead.phone }}</span>
              </a>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Ruta & Vuelo</span>
              <span class="font-bold text-slate-800 dark:text-slate-200">
                {{ passengerDetailsModal.lead.origin }} → {{ passengerDetailsModal.lead.destination }}
                <span class="text-sky-600 dark:text-sky-400 font-mono ml-1">({{ passengerDetailsModal.lead.flight_number || 'S/N' }})</span>
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Fecha de Vuelo</span>
              <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ formatFlightDate(passengerDetailsModal.lead.flight_date) }}</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Pasajeros (PAX)</span>
              <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ passengerDetailsModal.lead.passengers_count || 1 }} Persona(s)</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Tarifa Registrada</span>
              <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">
                {{ passengerDetailsModal.lead.total_price ? formatCurrency(passengerDetailsModal.lead.total_price) : 'Por cotizar' }}
              </span>
            </div>
          </div>

          <!-- Datos Recopilados por el Bot / Chatbot Engine -->
          <div v-if="passengerDetailsModal.lead.details || passengerDetailsModal.lead.bot_payload" class="space-y-1.5 text-xs">
            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
              <Info class="w-3.5 h-3.5 text-sky-500" />
              <span>Información adicional recopilada:</span>
            </span>
            <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px] text-slate-600 dark:text-slate-400 space-y-1">
              <p v-if="passengerDetailsModal.lead.details">{{ passengerDetailsModal.lead.details }}</p>
              <!-- Desglose de bot_payload si existe -->
              <div v-if="passengerDetailsModal.lead.bot_payload" class="pt-1.5 mt-1.5 border-t border-slate-200 dark:border-slate-800/60 font-mono text-[10px] space-y-0.5">
                <div v-if="passengerDetailsModal.lead.bot_payload.passenger_category">
                  <span class="text-slate-500">Categoría PAX:</span> <span class="text-slate-800 dark:text-slate-200 font-bold">{{ passengerDetailsModal.lead.bot_payload.passenger_category }}</span>
                </div>
                <div v-if="passengerDetailsModal.lead.bot_payload.contact_email">
                  <span class="text-slate-500">Email:</span> <span class="text-slate-800 dark:text-slate-200 font-bold">{{ passengerDetailsModal.lead.bot_payload.contact_email }}</span>
                </div>
                <div v-if="passengerDetailsModal.lead.bot_payload.flight_hour">
                  <span class="text-slate-500">Horario vuelo:</span> <span class="text-slate-800 dark:text-slate-200 font-bold">{{ passengerDetailsModal.lead.bot_payload.flight_hour }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Formulario: Actualizar Estado y Asignar PNR -->
          <form @submit.prevent="submitUpdatePassengerStatus" class="p-3.5 bg-sky-500/5 rounded-xl border border-sky-500/20 space-y-3 text-xs">
            <div class="font-bold text-sky-700 dark:text-sky-300 flex items-center gap-1.5">
              <ShieldCheck class="w-4 h-4" />
              <span>Actualizar Estado de la Reserva</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Estado de Emisión</label>
                <select 
                  v-model="passengerDetailsModal.status"
                  class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:border-sky-500"
                >
                  <option value="nuevo">Pendiente / Nuevo</option>
                  <option value="contactado">En Gestión (Contactado)</option>
                  <option value="en_emision">En Emisión</option>
                  <option value="emitido">Emitido</option>
                  <option value="cancelado">Cancelado</option>
                </select>
              </div>

              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Código PNR / Reserva</label>
                <input 
                  v-model="passengerDetailsModal.booking_reference" 
                  type="text" 
                  placeholder="Ej. TAMEP-8921"
                  class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono uppercase focus:outline-none focus:border-sky-500"
                />
              </div>
            </div>

            <div class="flex items-center justify-between pt-1">
              <router-link 
                to="/"
                class="text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1 font-semibold text-[11px]"
              >
                <MessageSquare class="w-3.5 h-3.5" />
                <span>Abrir chat en CRM</span>
              </router-link>

              <button 
                type="submit" 
                :disabled="passengerDetailsModal.loading"
                class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <Loader2 v-if="passengerDetailsModal.loading" class="w-4 h-4 animate-spin" />
                <span>{{ passengerDetailsModal.loading ? 'Guardando...' : 'Actualizar Ficha' }}</span>
              </button>
            </div>
          </form>

          <!-- Botón Cerrar -->
          <div class="flex items-center justify-end pt-3 border-t border-slate-200 dark:border-slate-800">
            <button 
              type="button" 
              @click="closePassengerDetails"
              class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition cursor-pointer text-xs"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ========================================================
         MODAL: DETALLE DE PASAJEROS EN CAMPAÑA SMS GOIP
         ======================================================== -->
    <Transition enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div 
        v-if="broadcastDetailModal.isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm overflow-y-auto"
        @click.self="broadcastDetailModal.isOpen = false"
      >
        <div class="w-full max-w-5xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-5 sm:p-6 space-y-4 my-8 max-h-[90vh] overflow-y-auto animate-slide-up">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Reporte de Entrega: {{ broadcastDetailModal.campaign?.title }}
              </h3>
              <p class="text-[11px] text-slate-500">
                {{ broadcastDetailModal.campaign?.sent_count }} de {{ broadcastDetailModal.campaign?.total_recipients }} entregados por GoIP (Entel)
              </p>
            </div>
            <button 
              type="button" 
              @click="broadcastDetailModal.isOpen = false"
              class="px-2 py-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition cursor-pointer text-xs"
            >
              ✕
            </button>
          </div>

          <!-- Mensaje Real Recibido por los Pasajeros (Ejemplo) -->
          <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <span class="text-[10px] text-slate-500 uppercase font-sans font-bold tracking-wider">
                Mensaje Recibido por los Pasajeros (Ejemplo):
              </span>
              <span v-if="broadcastDetailModal.recipients?.[0]?.passenger_name" class="text-[11px] text-slate-400 font-sans">
                Para: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ broadcastDetailModal.recipients[0].passenger_name }}</span>
              </span>
            </div>
            <div class="text-xs text-slate-800 dark:text-slate-200 font-sans leading-relaxed whitespace-pre-wrap bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200/70 dark:border-slate-800 shadow-sm">
              {{ broadcastDetailModal.recipients?.[0]?.message || broadcastDetailModal.campaign?.message }}
            </div>

            <!-- Plantilla técnica colapsable (opcional) -->
            <details v-if="broadcastDetailModal.campaign?.message" class="pt-1">
              <summary class="text-[11px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer select-none">
                Ver plantilla técnica con variables
              </summary>
              <div class="mt-1.5 p-2 rounded-lg bg-slate-100 dark:bg-slate-900/80 font-mono text-[11px] text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800 whitespace-pre-wrap">
                {{ broadcastDetailModal.campaign?.message }}
              </div>
            </details>
          </div>

          <!-- Lista de Destinatarios de la Campaña -->
          <div v-if="broadcastDetailModal.loading" class="p-8 text-center">
            <Loader2 class="w-6 h-6 animate-spin text-sky-500 mx-auto" />
            <p class="text-xs text-slate-400 mt-2">Cargando reporte de pasajeros...</p>
          </div>
          <div v-else class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table class="w-full text-xs text-left">
              <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold uppercase text-[11px]">
                <tr>
                  <th class="p-2.5">Pasajero</th>
                  <th class="p-2.5">PNR / Reserva</th>
                  <th class="p-2.5">Teléfono</th>
                  <th class="p-2.5">Vuelo</th>
                  <th class="p-2.5">Línea SIM</th>
                  <th class="p-2.5">Estado</th>
                  <th class="p-2.5">Mensaje Enviado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                <tr v-for="r in broadcastDetailModal.recipients" :key="r.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                  <td class="p-2.5 font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                    {{ r.passenger_name || 'Pasajero' }}
                  </td>
                  <td class="p-2.5 whitespace-nowrap">
                    <span 
                      v-if="r.booking_reference || r.lead?.booking_reference"
                      class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 inline-block"
                      title="Código de Reserva / PNR"
                    >
                      {{ r.booking_reference || r.lead?.booking_reference }}
                    </span>
                    <span 
                      v-else 
                      class="text-slate-400 font-mono text-[11px] italic"
                      title="Ingreso manual sin código de reserva"
                    >
                      - (Manual)
                    </span>
                  </td>
                  <td class="p-2.5 font-mono whitespace-nowrap">{{ r.phone }}</td>
                  <td class="p-2.5 font-mono whitespace-nowrap">{{ r.flight_number || '-' }}</td>
                  <td class="p-2.5 whitespace-nowrap text-[11px]">
                    <span 
                      v-if="r.channel_used === 'sms_l1' || (r.gateway_message_id && r.gateway_message_id.startsWith('L1:'))" 
                      class="px-1.5 py-0.5 rounded text-[10px] font-bold font-sans bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 inline-flex items-center gap-1"
                      :title="'Enviado a través de ' + lineAliases.line1 + ' (Puerto 1)'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                      {{ lineAliases.line1 }}
                    </span>
                    <span 
                      v-else-if="r.channel_used === 'sms_l2' || (r.gateway_message_id && r.gateway_message_id.startsWith('L2:'))" 
                      class="px-1.5 py-0.5 rounded text-[10px] font-bold font-sans bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 inline-flex items-center gap-1"
                      :title="'Enviado a través de ' + lineAliases.line2 + ' (Puerto 2)'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                      {{ lineAliases.line2 }}
                    </span>
                    <span v-else class="text-slate-400">-</span>
                  </td>
                  <td class="p-2.5 whitespace-nowrap">
                    <span 
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase font-sans"
                      :class="[
                        r.status === 'sent' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
                        r.status === 'failed' ? 'bg-red-500/10 text-red-600 dark:text-red-400' :
                        'bg-sky-500/10 text-sky-600 dark:text-sky-400'
                      ]"
                    >
                      {{ r.status === 'sent' ? 'Entregado' : (r.status === 'failed' ? 'Fallido' : 'En cola GoIP') }}
                    </span>
                    <div v-if="r.error_message" class="text-red-500 text-[10px] mt-0.5 max-w-[150px] truncate" :title="r.error_message">
                      {{ r.error_message }}
                    </div>
                  </td>
                  <td class="p-2.5 min-w-[260px] max-w-[380px]">
                    <div 
                      class="text-[11px] leading-relaxed text-slate-700 dark:text-slate-300 font-sans line-clamp-2 hover:line-clamp-none transition-all cursor-pointer"
                      :title="r.message || broadcastDetailModal.campaign?.message"
                    >
                      {{ r.message || broadcastDetailModal.campaign?.message }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginación de Pasajeros en Modal de Detalle -->
          <div 
            v-if="broadcastDetailModal.recipients.length && broadcastDetailModal.pagination && broadcastDetailModal.pagination.last_page > 1" 
            class="flex items-center justify-between pt-2 text-xs"
          >
            <div class="text-[11px] text-slate-500">
              Mostrando {{ broadcastDetailModal.pagination.from }} - {{ broadcastDetailModal.pagination.to }} de {{ broadcastDetailModal.pagination.total }} pasajeros
            </div>
            <div class="flex items-center gap-1">
              <button
                type="button"
                :disabled="broadcastDetailModal.pagination.current_page <= 1 || broadcastDetailModal.loading"
                @click="changeCampaignDetailsPage(broadcastDetailModal.pagination.current_page - 1)"
                class="px-2 py-1 rounded border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 disabled:opacity-40 text-xs cursor-pointer flex items-center gap-0.5"
              >
                <ChevronLeft class="w-3 h-3" />
                <span>Ant</span>
              </button>
              <span class="text-[11px] font-mono px-1 text-slate-500">
                {{ broadcastDetailModal.pagination.current_page }} / {{ broadcastDetailModal.pagination.last_page }}
              </span>
              <button
                type="button"
                :disabled="broadcastDetailModal.pagination.current_page >= broadcastDetailModal.pagination.last_page || broadcastDetailModal.loading"
                @click="changeCampaignDetailsPage(broadcastDetailModal.pagination.current_page + 1)"
                class="px-2 py-1 rounded border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 disabled:opacity-40 text-xs cursor-pointer flex items-center gap-0.5"
              >
                <span>Sig</span>
                <ChevronRight class="w-3 h-3" />
              </button>
            </div>
          </div>

          <!-- Botón de Cerrar, Cancelar y Reintentar -->
          <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <button 
                v-if="['processing', 'pending'].includes(broadcastDetailModal.campaign?.status)"
                type="button" 
                @click="cancelBroadcastCampaign(broadcastDetailModal.campaign)"
                class="px-3.5 py-1.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold text-xs transition cursor-pointer flex items-center gap-1.5"
              >
                <span>Detener y Cancelar Envío</span>
              </button>

              <button 
                v-if="broadcastDetailModal.campaign?.failed_count > 0 && broadcastDetailModal.campaign?.status !== 'cancelled'"
                type="button" 
                @click="retryFailedCampaign(broadcastDetailModal.campaign)"
                class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs transition cursor-pointer"
              >
                Reintentar {{ broadcastDetailModal.campaign.failed_count }} Fallidos
              </button>
            </div>

            <button 
              type="button" 
              @click="broadcastDetailModal.isOpen = false"
              class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition cursor-pointer"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modal Bautizar Líneas / Chips SIM GoIP -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="showLineNamingModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        @click.self="showLineNamingModal = false"
      >
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Cabecera -->
          <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-950/50">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                <Smartphone class="w-4 h-4" />
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Bautizar Líneas Telefónicas (SIM)</h3>
                <p class="text-[11px] text-slate-500">Asigna nombres y números reales para evitar confusiones con "puertos"</p>
              </div>
            </div>
            <button 
              type="button" 
              @click="showLineNamingModal = false" 
              class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Cuerpo -->
          <form @submit.prevent="saveLineAliases" class="p-5 space-y-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <span>Nombre / Teléfono de la Línea 1 (Puerto 1):</span>
              </label>
              <input 
                v-model="lineAliasInputs.line1" 
                type="text" 
                required
                placeholder="Ej. Entel 74844348 (Principal)" 
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
              />
              <p class="text-[10px] text-slate-400">Ejemplo: "Entel 74844348" o "Línea Principal".</p>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span>Nombre / Teléfono de la Línea 2 (Puerto 2):</span>
              </label>
              <input 
                v-model="lineAliasInputs.line2" 
                type="text" 
                required
                placeholder="Ej. Entel 78912345 (Secundario)" 
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-purple-500"
              />
              <p class="text-[10px] text-slate-400">Este nombre se reflejará en el selector, la lista de pasajeros y el historial.</p>
            </div>

            <!-- Botones de Acción -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
              <button 
                type="button" 
                @click="resetLineAliases" 
                class="text-[11px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 underline cursor-pointer"
              >
                Restablecer por defecto
              </button>

              <div class="flex items-center gap-2">
                <button 
                  type="button" 
                  @click="showLineNamingModal = false" 
                  class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition cursor-pointer"
                >
                  Cancelar
                </button>
                <button 
                  type="submit" 
                  class="px-4 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs transition cursor-pointer shadow-xs"
                >
                  Guardar Nombres
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- Modal de Alerta: Exceso de Caracteres SMS y Costo por Mensajes -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="showExceededSmsConfirmModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
        @click.self="showExceededSmsConfirmModal = false"
      >
        <div class="bg-white dark:bg-slate-900 border-2 border-amber-500 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <!-- Cabecera de Alerta -->
          <div class="p-4 border-b border-amber-200 dark:border-amber-900/50 bg-amber-500/15 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-xl shadow-sm shrink-0">
                <AlertTriangle class="w-6 h-6" />
              </div>
              <div>
                <h3 class="text-sm font-black text-amber-950 dark:text-amber-200 uppercase tracking-wide">
                  ¡Alerta! Has excedido los 160 caracteres
                </h3>
                <p class="text-[11px] text-amber-800 dark:text-amber-300 font-medium">
                  Se cobrarán más mensajes según la cantidad de caracteres redactados
                </p>
              </div>
            </div>
            <button 
              type="button" 
              @click="showExceededSmsConfirmModal = false" 
              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Cuerpo Informativo y Detalle de Costo -->
          <div class="p-5 space-y-4">
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 font-semibold">Longitud de tu mensaje:</span>
                <span class="font-mono font-bold text-amber-600 dark:text-amber-400">
                  {{ smsCharCount }} caracteres (excediste por {{ smsCharCount - 160 }})
                </span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 font-semibold">Límite para 1 solo mensaje:</span>
                <span class="font-mono text-slate-700 dark:text-slate-300">160 caracteres</span>
              </div>
              <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs font-bold">
                <span class="text-slate-700 dark:text-slate-200">Costo según cantidad de caracteres:</span>
                <span class="px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-800 dark:text-amber-300 border border-amber-500 font-extrabold">
                  {{ smsSegmentCount }} MENSAJES SMS por cada pasajero
                </span>
              </div>
            </div>

            <!-- Explicación de Normativa Celular -->
            <div class="space-y-1.5 text-xs">
              <div class="font-bold text-slate-600 dark:text-slate-400 text-[11px] uppercase tracking-wider">
                ¿Por qué te costará {{ smsSegmentCount }} mensajes?
              </div>
              <div class="p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 text-[11px] text-slate-700 dark:text-slate-300 leading-relaxed">
                Por normas de telefonía celular GSM, cuando un mensaje supera los 160 caracteres, las operadoras lo fragmentan en partes de 153 caracteres y te cobran <strong>{{ smsSegmentCount }} SMS independientes por cada pasajero</strong>.
              </div>
            </div>

            <!-- Resumen Total de Saldo a Descontar -->
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 space-y-1.5 text-xs text-rose-950 dark:text-rose-200">
              <div class="font-extrabold flex items-center justify-between">
                <span>Impacto total en el saldo del chip SIM:</span>
                <span class="font-mono text-base font-black text-rose-600 dark:text-rose-400">
                  {{ (broadcastPreviewData?.total_recipients || 0) * smsSegmentCount }} SMS
                </span>
              </div>
              <p class="text-[11px] text-rose-800 dark:text-rose-300">
                Se enviará a <strong>{{ broadcastPreviewData?.total_recipients || 0 }} pasajeros</strong>. En lugar de consumir {{ broadcastPreviewData?.total_recipients || 0 }} SMS de saldo, se debitarán <strong>{{ (broadcastPreviewData?.total_recipients || 0) * smsSegmentCount }} SMS</strong> (+{{ (broadcastPreviewData?.total_recipients || 0) * (smsSegmentCount - 1) }} SMS adicionales).
              </p>
            </div>

            <!-- Opciones y Acciones -->
            <div class="pt-2 flex flex-col gap-2">
              <button 
                type="button" 
                @click="handleConfirmExceededAndSend" 
                class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center justify-center gap-2 transition cursor-pointer shadow-xs"
              >
                <span>Acepto el sobrecosto: Enviar como {{ smsSegmentCount }} SMS por pasajero</span>
              </button>

              <button 
                type="button" 
                @click="showExceededSmsConfirmModal = false" 
                class="w-full py-2 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-center text-xs font-semibold transition cursor-pointer"
              >
                Cancelar y volver a editar el mensaje
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modal de Perfil de Usuario y Cambio de Contraseña -->
    <UserProfileModal 
      :is-open="showProfileModal" 
      @close="showProfileModal = false" 
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/useAuthStore';
import api from '@/api/client';
import { 
  Plane, Headphones, ShieldCheck, LogOut, LayoutDashboard, Ticket, Users, 
  MessageSquare, Radio, DollarSign, TrendingUp, Award, Zap, Search, UserPlus, 
  Eye, EyeOff, KeyRound, Smartphone, Phone, Building2, Info, RefreshCw,
  ArrowRight, Download, BellRing, BellOff, Bell, Lock, Loader2, CheckCircle2,
  AlertCircle, AlertTriangle, X, CalendarDays, Upload, Globe, ExternalLink,
  FileText, Check, Plus, Clock, UserCheck, Edit3, Trash2,
  Bot, RotateCcw, Sparkles, Copy, Save, ChevronDown, Send,
  ChevronLeft, ChevronRight
} from 'lucide-vue-next';
import logoImg from '../../images/logo.jpg';
import UserProfileModal from '@/components/profile/UserProfileModal.vue';
import CallCenterBroadcastPanel from '@/components/callcenter/CallCenterBroadcastPanel.vue';

const router = useRouter();
const authStore = useAuthStore();
const showProfileModal = ref(false);

const adminInitials = computed(() => {
  const name = authStore.user?.name || '';
  if (!name) return 'AD';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
});

const activeTab = ref('overview');
const loading = ref(false);

// Conteo reactivo de pasajeros y boletos para el badge del menú
const ticketCounts = ref({
  total: 0,
  nuevo: 0,
  contactado: 0,
  emitido: 0,
});

const tabs = computed(() => [
  { id: 'overview', label: 'Resumen', icon: LayoutDashboard },
  { 
    id: 'tickets', 
    label: 'Pasajeros', 
    icon: Users,
    badge: ticketCounts.value.nuevo > 0 ? ticketCounts.value.nuevo : undefined,
  },
  { id: 'broadcasts', label: 'Avisos SMS', icon: Radio },
  { id: 'callcenter', label: 'Avisos Call Center', icon: Headphones },
  { id: 'agents', label: 'Agentes', icon: Users },
  { id: 'monitor', label: 'Monitoreo', icon: MessageSquare },
  { id: 'bot_messages', label: 'Mensajes Bot', icon: Bot },
  { id: 'itinerary', label: 'Itinerario', icon: CalendarDays },
  { id: 'meta', label: 'Estado WhatsApp', icon: Smartphone },
]);

const kpis = ref({
  total_revenue: 0,
  revenue_today: 0,
  total_tickets: 0,
  tickets_today: 0,
  total_leads: 0,
  conversion_rate: 0,
});

const conversationsStats = ref({
  waiting_agent: 0,
  human_live: 0,
  bot_active: 0,
  total: 0,
});

const agentsList = ref([]);
const recentTickets = ref([]);

// Pestaña Pasajeros y Boletos
const ticketsList = ref([]);
const totalAuditedTickets = ref(0);
const ticketSearch = ref('');
const ticketStatusFilter = ref('all');
const ticketsLoading = ref(false);
let searchTimeout = null;

const airportOptions = [
  { code: 'VVI', name: 'Santa Cruz (Viru Viru - VVI)' },
  { code: 'LPB', name: 'La Paz (El Alto - LPB)' },
  { code: 'CBB', name: 'Cochabamba (Jorge Wilstermann - CBB)' },
  { code: 'CIJ', name: 'Cobija (Cap. Aníbal Arab - CIJ)' },
  { code: 'TJA', name: 'Tarija (Oriel Lea Plaza - TJA)' },
  { code: 'SRE', name: 'Sucre (Alcantarí - SRE)' },
  { code: 'TDD', name: 'Trinidad (Jorge Henrich - TDD)' },
  { code: 'RBQ', name: 'Rurrenabaque (RBQ)' },
  { code: 'RIB', name: 'Riberalta (RIB)' },
  { code: 'GYA', name: 'Guayaramerín (GYA)' },
  { code: 'BYC', name: 'Yacuiba (BYC)' },
  { code: 'PSZ', name: 'Puerto Suárez (PSZ)' },
];

// Pestaña Monitoreo
const monitorList = ref([]);
const monitorStatusFilter = ref('all');

// Pestaña Meta
const metaInfo = ref({});

// Pestaña Itinerario
const itineraryData = ref({
  image_url: null,
  web_url: 'https://tamep.bo/itinerarios',
  caption: '',
  has_image: false,
  updated_at: null,
  updated_by: null,
});
const itineraryForm = ref({
  web_url: 'https://tamep.bo/itinerarios',
  caption: '',
  imageFile: null,
  imagePreview: null,
});
const savingItinerary = ref(false);
const loadingItinerary = ref(false);

// Formulario nuevo agente
const newAgent = ref({
  name: '',
  email: '',
  password: '',
  role: 'agent',
});
const creatingAgent = ref(false);
const formFeedback = ref({ message: '', type: '' });

let pollInterval = null;

function onWindowFocus() {
  fetchMetrics();
  if (activeTab.value === 'tickets') {
    fetchTickets();
  }
}

onMounted(async () => {
  if (!authStore.isAdmin) {
    router.replace('/');
    return;
  }
  await fetchMetrics();
  await fetchTickets();
  await fetchMonitorConversations();
  await fetchItinerary();
  await fetchMetaStatus();

  // Escucha reactiva en tiempo real vía WebSocket / Echo
  if (window.Echo) {
    try {
      window.Echo.private('conversations')
        .listen('.ConversationUpdated', () => {
          fetchMetrics();
          if (activeTab.value === 'tickets') {
            fetchTickets();
          }
        });
    } catch (e) {
      console.warn('Echo listener warning in AdminDashboard:', e);
    }
  }

  // Refrescar al volver el foco a la ventana
  window.addEventListener('focus', onWindowFocus);

  // Polling automático ligero cada 6 segundos para garantizar actualización continua
  pollInterval = setInterval(() => {
    if (activeTab.value === 'overview' || activeTab.value === 'agents') {
      fetchMetrics();
    } else if (activeTab.value === 'tickets') {
      fetchTickets();
      fetchMetrics();
    } else if (activeTab.value === 'broadcasts') {
      fetchBroadcastCampaigns();
    } else if (activeTab.value === 'monitor') {
      fetchMonitorConversations();
    }
  }, 6000);

  nextTick(() => {
    syncEditorContent(broadcastForm.value.message);
  });
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  stopAutoBroadcastEngine();
  window.removeEventListener('focus', onWindowFocus);
  if (window.Echo) {
    try {
      window.Echo.private('conversations').stopListening('.ConversationUpdated');
    } catch (e) {
      // Ignorar
    }
  }
});

// Reactividad inmediata al cambiar de pestaña
watch(activeTab, (newTab) => {
  if (newTab === 'overview') {
    fetchMetrics();
  } else if (newTab === 'tickets') {
    fetchTickets();
    fetchMetrics();
  } else if (newTab === 'broadcasts') {
    nextTick(() => {
      syncEditorContent(broadcastForm.value.message);
    });
    fetchBroadcastCampaigns().then(() => {
      // Auto-iniciar motor en vivo si hay campañas en proceso o pendientes
      const hasActive = broadcastCampaigns.value.some(c => ['processing', 'pending'].includes(c.status));
      if (hasActive) {
        startAutoBroadcastEngine();
      }
    });
    fetchGatewayStatus();
  } else if (newTab === 'agents') {
    fetchMetrics();
  } else if (newTab === 'monitor') {
    fetchMonitorConversations();
  } else if (newTab === 'bot_messages') {
    fetchBotMessages();
  } else if (newTab === 'itinerary') {
    fetchItinerary();
  } else if (newTab === 'meta') {
    fetchMetaStatus();
  }
});

async function refreshCurrentTab() {
  loading.value = true;
  try {
    if (activeTab.value === 'overview') await fetchMetrics();
    else if (activeTab.value === 'tickets') await fetchTickets();
    else if (activeTab.value === 'broadcasts') await fetchBroadcastCampaigns();
    else if (activeTab.value === 'agents') await fetchMetrics();
    else if (activeTab.value === 'monitor') await fetchMonitorConversations();
    else if (activeTab.value === 'bot_messages') await fetchBotMessages();
    else if (activeTab.value === 'itinerary') await fetchItinerary();
    else if (activeTab.value === 'meta') await fetchMetaStatus();
  } finally {
    loading.value = false;
  }
}

async function fetchMetrics() {
  try {
    const res = await api.get('/admin/metrics');
    if (res.data?.success) {
      kpis.value = res.data.kpis;
      conversationsStats.value = res.data.conversations;
      agentsList.value = res.data.agents;
      recentTickets.value = res.data.recent_tickets;
      if (res.data.counts) {
        ticketCounts.value = res.data.counts;
      }
    }
  } catch (err) {
    console.error('Error al cargar métricas de administración:', err);
  }
}

async function fetchTickets() {
  ticketsLoading.value = true;
  try {
    const params = {
      status: ticketStatusFilter.value,
    };
    if (ticketSearch.value.trim()) params.search = ticketSearch.value.trim();
    const res = await api.get('/admin/tickets', { params });
    if (res.data?.success) {
      ticketsList.value = res.data.tickets.data || [];
      totalAuditedTickets.value = res.data.tickets.total || 0;
      if (res.data.counts) {
        ticketCounts.value = res.data.counts;
      }
    }
  } catch (err) {
    console.error('Error al cargar pasajeros y boletos:', err);
  } finally {
    ticketsLoading.value = false;
  }
}

function setTicketStatusFilter(status) {
  ticketStatusFilter.value = status;
  fetchTickets();
}

function debounceFetchTickets() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchTickets();
  }, 350);
}

// ==========================================
// GESTIÓN DE MODALES DE PASAJEROS Y BOLETOS
// ==========================================

const newPassengerModal = ref({
  isOpen: false,
  loading: false,
  form: {
    passenger_name: '',
    passport_number: '',
    phone: '',
    passengers_count: 1,
    origin: 'VVI',
    destination: 'LPB',
    flight_date: '',
    flight_number: '',
    booking_reference: '',
    total_price: '',
    status: 'nuevo',
    details: '',
  }
});

function openNewPassengerModal() {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const defaultDate = tomorrow.toISOString().split('T')[0];

  newPassengerModal.value = {
    isOpen: true,
    loading: false,
    form: {
      passenger_name: '',
      passport_number: '',
      phone: '',
      passengers_count: 1,
      origin: 'VVI',
      destination: 'LPB',
      flight_date: defaultDate,
      flight_number: '',
      booking_reference: '',
      total_price: '',
      status: 'nuevo',
      details: '',
    }
  };
}

function closeNewPassengerModal() {
  newPassengerModal.value.isOpen = false;
}

async function submitNewPassenger() {
  if (!newPassengerModal.value.form.passenger_name || !newPassengerModal.value.form.phone) {
    showToast('Nombre y teléfono son obligatorios', 'error');
    return;
  }
  newPassengerModal.value.loading = true;
  try {
    const res = await api.post('/admin/passengers', newPassengerModal.value.form);
    if (res.data?.success) {
      showToast('¡Pasajero y boleto registrados con éxito!', 'success');
      closeNewPassengerModal();
      await fetchTickets();
      await fetchMetrics();
    }
  } catch (err) {
    showToast(err.response?.data?.message || 'Error al registrar pasajero', 'error');
  } finally {
    newPassengerModal.value.loading = false;
  }
}

const passengerDetailsModal = ref({
  isOpen: false,
  loading: false,
  lead: null,
  status: 'nuevo',
  booking_reference: '',
});

function openPassengerDetails(lead) {
  passengerDetailsModal.value = {
    isOpen: true,
    loading: false,
    lead,
    status: lead.status || 'nuevo',
    booking_reference: lead.booking_reference || '',
  };
}

function closePassengerDetails() {
  passengerDetailsModal.value.isOpen = false;
  passengerDetailsModal.value.lead = null;
}

async function submitUpdatePassengerStatus() {
  if (!passengerDetailsModal.value.lead) return;
  passengerDetailsModal.value.loading = true;
  try {
    const leadId = passengerDetailsModal.value.lead.id;
    const res = await api.patch(`/admin/passengers/${leadId}/status`, {
      status: passengerDetailsModal.value.status,
      booking_reference: passengerDetailsModal.value.booking_reference,
    });
    if (res.data?.success) {
      showToast('Ficha del pasajero actualizada correctamente', 'success');
      closePassengerDetails();
      await fetchTickets();
      await fetchMetrics();
    }
  } catch (err) {
    showToast(err.response?.data?.message || 'Error al actualizar pasajero', 'error');
  } finally {
    passengerDetailsModal.value.loading = false;
  }
}

async function fetchMonitorConversations() {
  try {
    const res = await api.get('/admin/conversations/monitor', {
      params: { status: monitorStatusFilter.value }
    });
    if (res.data?.success) {
      monitorList.value = res.data.conversations.data || [];
    }
  } catch (err) {
    console.error('Error al cargar monitor de conversaciones:', err);
  }
}

const loadingMetaStatus = ref(false);

async function refreshMetaStatus() {
  loadingMetaStatus.value = true;
  try {
    await fetchMetaStatus();
    showToast('Estado de la cuenta de WhatsApp actualizado.', 'success');
  } catch (err) {
    showToast('No se pudo verificar el estado en este momento.', 'error');
  } finally {
    loadingMetaStatus.value = false;
  }
}

async function fetchMetaStatus() {
  try {
    const res = await api.get('/admin/meta-status');
    if (res.data?.success) {
      metaInfo.value = res.data;
    }
  } catch (err) {
    console.error('Error al cargar estado de Meta:', err);
  }
}

async function handleCreateAgent() {
  creatingAgent.value = true;
  formFeedback.value = { message: '', type: '' };
  try {
    const res = await api.post('/agents', newAgent.value);
    if (res.data?.success) {
      formFeedback.value = { message: 'Usuario creado exitosamente.', type: 'success' };
      newAgent.value = { name: '', email: '', password: '', role: 'agent' };
      await fetchMetrics();
    }
  } catch (err) {
    formFeedback.value = { 
      message: err.response?.data?.message || 'Error al registrar el usuario.', 
      type: 'error' 
    };
  } finally {
    creatingAgent.value = false;
  }
}

const passwordModal = ref({
  isOpen: false,
  agent: null,
  newPassword: '',
  showPassword: false,
  loading: false,
  error: '',
});

const statusConfirmModal = ref({
  isOpen: false,
  agent: null,
  loading: false,
});

const toast = ref({
  show: false,
  message: '',
  type: 'success',
});

let toastTimer = null;
function showToast(message, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer);
  toast.value = { show: true, message, type };
  toastTimer = setTimeout(() => {
    toast.value.show = false;
  }, 4000);
}

function openPasswordModal(agent) {
  passwordModal.value = {
    isOpen: true,
    agent,
    newPassword: '',
    showPassword: false,
    loading: false,
    error: '',
  };
}

function closePasswordModal() {
  passwordModal.value.isOpen = false;
}

async function submitChangePassword() {
  if (!passwordModal.value.agent) return;
  if (!passwordModal.value.newPassword || passwordModal.value.newPassword.length < 6) {
    passwordModal.value.error = 'La contraseña debe tener al menos 6 caracteres.';
    return;
  }

  passwordModal.value.loading = true;
  passwordModal.value.error = '';

  try {
    const agent = passwordModal.value.agent;
    const res = await api.put(`/agents/${agent.id}`, { 
      password: passwordModal.value.newPassword 
    });
    if (res.data?.success) {
      showToast(`Contraseña actualizada para ${agent.name}.`, 'success');
      closePasswordModal();
    }
  } catch (err) {
    passwordModal.value.error = err.response?.data?.message || 'Error al actualizar la contraseña.';
  } finally {
    passwordModal.value.loading = false;
  }
}

function requestToggleStatus(agent) {
  statusConfirmModal.value = {
    isOpen: true,
    agent,
    loading: false,
  };
}

async function confirmToggleStatus() {
  const agent = statusConfirmModal.value.agent;
  if (!agent) return;

  statusConfirmModal.value.loading = true;
  try {
    const res = await api.post(`/agents/${agent.id}/toggle`);
    if (res.data?.success) {
      agent.is_active = res.data.is_active;
      const actionText = agent.is_active ? 'activada' : 'suspendida';
      showToast(`La cuenta de ${agent.name} fue ${actionText} exitosamente.`, 'success');
      statusConfirmModal.value.isOpen = false;
    }
  } catch (err) {
    showToast(err.response?.data?.message || 'Error al cambiar estado.', 'error');
  } finally {
    statusConfirmModal.value.loading = false;
  }
}

const deleteConfirmModal = ref({
  isOpen: false,
  agent: null,
  loading: false,
});

function requestDeleteAgent(agent) {
  deleteConfirmModal.value = {
    isOpen: true,
    agent,
    loading: false,
  };
}

async function confirmDeleteAgent() {
  const agent = deleteConfirmModal.value.agent;
  if (!agent) return;

  deleteConfirmModal.value.loading = true;
  try {
    const res = await api.delete(`/agents/${agent.id}`);
    if (res.data?.success) {
      showToast(res.data.message || `Agente ${agent.name} eliminado exitosamente.`, 'success');
      deleteConfirmModal.value.isOpen = false;
      await fetchMetrics();
    }
  } catch (err) {
    showToast(err.response?.data?.message || 'Error al eliminar el agente.', 'error');
  } finally {
    deleteConfirmModal.value.loading = false;
  }
}

async function fetchItinerary() {
  loadingItinerary.value = true;
  try {
    const res = await api.get('/admin/itinerary');
    if (res.data?.success && res.data?.itinerary) {
      itineraryData.value = res.data.itinerary;
      itineraryForm.value.web_url = res.data.itinerary.web_url || 'https://tamep.bo/itinerarios';
      itineraryForm.value.caption = res.data.itinerary.caption || '';
      itineraryForm.value.imagePreview = res.data.itinerary.image_url || null;
      itineraryForm.value.imageFile = null;
    }
  } catch (err) {
    console.error('Error al cargar itinerario:', err);
  } finally {
    loadingItinerary.value = false;
  }
}

function handleImageSelect(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.size > 10 * 1024 * 1024) {
    showToast('El archivo supera los 10MB permitidos', 'error');
    return;
  }

  itineraryForm.value.imageFile = file;
  const reader = new FileReader();
  reader.onload = (e) => {
    itineraryForm.value.imagePreview = e.target.result;
  };
  reader.readAsDataURL(file);
}

function removeSelectedImage() {
  itineraryForm.value.imageFile = null;
  itineraryForm.value.imagePreview = itineraryData.value.image_url || null;
}

async function saveItinerary() {
  savingItinerary.value = true;
  try {
    const formData = new FormData();
    if (itineraryForm.value.imageFile) {
      formData.append('image', itineraryForm.value.imageFile);
    }
    if (itineraryForm.value.web_url) {
      formData.append('web_url', itineraryForm.value.web_url);
    }
    if (itineraryForm.value.caption !== undefined) {
      formData.append('caption', itineraryForm.value.caption);
    }

    const res = await api.post('/admin/itinerary', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (res.data?.success) {
      showToast('¡Itinerario actualizado con éxito!', 'success');
      await fetchItinerary();
    } else {
      showToast('No se pudo actualizar el itinerario', 'error');
    }
  } catch (err) {
    console.error('Error al guardar itinerario:', err);
    showToast(err.response?.data?.message || 'Error al guardar itinerario', 'error');
  } finally {
    savingItinerary.value = false;
  }
}

function formatCurrency(amount) {
  const val = Number(amount || 0);
  return `${val.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} BOB`;
}

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

function formatTime(timestamp) {
  if (!timestamp) return '';
  const d = new Date(timestamp);
  return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ========================================================
// GESTIÓN DE MENSAJES Y PLANTILLAS DEL BOT DE WHATSAPP
// ========================================================
const botMessages = ref([]);
const loadingBotMessages = ref(false);
const savingBotMessageId = ref(null);
const restoringBotMessageId = ref(null);
const botMessageSearch = ref('');
const selectedBotCategory = ref('all');
const editingBotMessages = ref({});

const botCategories = computed(() => {
  const cats = new Set(botMessages.value.map(m => m.category).filter(Boolean));
  return Array.from(cats);
});

const filteredBotMessages = computed(() => {
  let list = botMessages.value;

  if (selectedBotCategory.value !== 'all') {
    list = list.filter(m => m.category === selectedBotCategory.value);
  }

  if (botMessageSearch.value.trim()) {
    const q = botMessageSearch.value.toLowerCase().trim();
    list = list.filter(m => 
      (m.title && m.title.toLowerCase().includes(q)) ||
      (m.category && m.category.toLowerCase().includes(q)) ||
      (m.description && m.description.toLowerCase().includes(q)) ||
      (m.key && m.key.toLowerCase().includes(q)) ||
      (editingBotMessages.value[m.id] && editingBotMessages.value[m.id].toLowerCase().includes(q))
    );
  }

  return list;
});

const botMessagesStats = computed(() => {
  const total = botMessages.value.length;
  const custom = botMessages.value.filter(m => m.message !== m.default_message).length;
  const defaultCount = total - custom;
  return { total, custom, defaultCount };
});

async function fetchBotMessages() {
  loadingBotMessages.value = true;
  try {
    const res = await api.get('/admin/bot-messages');
    if (res.data?.success && res.data?.messages) {
      botMessages.value = res.data.messages;
      const drafts = {};
      res.data.messages.forEach(m => {
        drafts[m.id] = m.message;
      });
      editingBotMessages.value = drafts;
    }
  } catch (err) {
    console.error('Error al cargar mensajes del bot:', err);
    showToast('Error al cargar los mensajes del bot', 'error');
  } finally {
    loadingBotMessages.value = false;
  }
}

async function saveBotMessage(msg) {
  const newText = editingBotMessages.value[msg.id];
  if (!newText || !newText.trim()) {
    showToast('El texto del mensaje no puede estar vacío', 'error');
    return;
  }

  savingBotMessageId.value = msg.id;
  try {
    const res = await api.put(`/admin/bot-messages/${msg.id}`, {
      message: newText,
    });
    if (res.data?.success && res.data?.bot_message) {
      const idx = botMessages.value.findIndex(m => m.id === msg.id);
      if (idx !== -1) {
        botMessages.value[idx] = res.data.bot_message;
        editingBotMessages.value[msg.id] = res.data.bot_message.message;
      }
      showToast('Mensaje guardado correctamente y activo en WhatsApp');
    }
  } catch (err) {
    console.error('Error al guardar mensaje:', err);
    showToast(err.response?.data?.message || 'Error al guardar el mensaje', 'error');
  } finally {
    savingBotMessageId.value = null;
  }
}

async function restoreBotMessage(msg) {
  if (!confirm(`¿Deseas restablecer el mensaje "${msg.title}" a su versión de fábrica?`)) {
    return;
  }

  restoringBotMessageId.value = msg.id;
  try {
    const res = await api.post(`/admin/bot-messages/${msg.id}/restore`);
    if (res.data?.success && res.data?.bot_message) {
      const idx = botMessages.value.findIndex(m => m.id === msg.id);
      if (idx !== -1) {
        botMessages.value[idx] = res.data.bot_message;
        editingBotMessages.value[msg.id] = res.data.bot_message.message;
      }
      showToast('Mensaje restablecido a su versión predeterminada');
    }
  } catch (err) {
    console.error('Error al restablecer mensaje:', err);
    showToast('Error al restablecer el mensaje', 'error');
  } finally {
    restoringBotMessageId.value = null;
  }
}

function discardBotMessageChanges(msg) {
  editingBotMessages.value[msg.id] = msg.message;
}

function insertVariable(msgId, variableName) {
  const current = editingBotMessages.value[msgId] || '';
  editingBotMessages.value[msgId] = `${current}{${variableName}}`;
}

function insertFormat(msgId, prefix, suffix = prefix) {
  const current = editingBotMessages.value[msgId] || '';
  editingBotMessages.value[msgId] = `${current} ${prefix}texto${suffix} `;
}

function insertEmoji(msgId, emoji) {
  const current = editingBotMessages.value[msgId] || '';
  editingBotMessages.value[msgId] = `${current}${emoji}`;
}

function copyMessageText(text) {
  if (!navigator.clipboard) return;
  navigator.clipboard.writeText(text).then(() => {
    showToast('Texto copiado al portapapeles');
  });
}

function formatWhatsAppPreview(rawText) {
  if (!rawText) return '<span class="text-slate-400 italic">Sin contenido</span>';
  let escaped = String(rawText)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  
  escaped = escaped.replace(/```([\s\S]*?)```/g, '<code class="bg-black/10 dark:bg-black/30 px-1 py-0.5 rounded text-[11px] font-mono">$1</code>');
  escaped = escaped.replace(/\*([^\*\n]+)\*/g, '<strong class="font-bold">$1</strong>');
  escaped = escaped.replace(/_([^_\n]+)_/g, '<em class="italic">$1</em>');
  escaped = escaped.replace(/~([^~\n]+)~/g, '<del class="line-through opacity-70">$1</del>');
  escaped = escaped.replace(/\n/g, '<br>');
  escaped = escaped.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 underline break-all">$1</a>');

  return escaped;
}

// ========================================================
// PESTAÑA: DIFUSIÓN MASIVA POR SMS GOIP (ENTEL)
// ========================================================
const broadcastAirports = [
  { code: 'CBB', city: 'Cochabamba', name: 'Cochabamba (CBB)' },
  { code: 'CIJ', city: 'Cobija', name: 'Cobija (CIJ)' },
  { code: 'LPB', city: 'La Paz', name: 'La Paz (LPB)' },
  { code: 'VVI', city: 'Santa Cruz', name: 'Santa Cruz (VVI)' },
];

const availableFlightsList = ref([]);
const broadcastCampaigns = ref([]);
const broadcastPagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
  from: 0,
  to: 0,
  per_page: 15,
});
const broadcastFilters = ref({
  flight_number: '',
  date: '',
});
const broadcastLoading = ref(false);
const broadcastSubmitting = ref(false);
const broadcastPreviewLoading = ref(false);
const broadcastPreviewData = ref(null);

const smsTemplates = {
  eptam_cancellation: 'Sr/Sra {apellido}: EPTAM informa: El vuelo {vuelo} {origen}-{destino} de las {hora} fue cancelado. Para mayor informacion, comuniquese con nuestra oficina o espere nuevas instrucciones.',
  flight_delay: 'Sr/Sra {apellido}: TAMEP informa que su vuelo {vuelo} {origen}-{destino} del {fecha} fue demorado para las {hora}. Consultas: WhatsApp 73748234 o 57075796.',
  flight_cancellation: 'Sr/Sra {apellido}: TAMEP informa que su vuelo {vuelo} {origen}-{destino} del {fecha} fue cancelado por mantenimiento. Consultas: WhatsApp 73748234 o 57075796.',
  flight_reminder: 'Sr/Sra {apellido}: TAMEP informa que su vuelo {vuelo} {origen}-{destino} del {fecha} se encuentra confirmado. Consultas: WhatsApp 73748234 o 57075796.',
  custom: 'Sr/Sra {apellido}: TAMEP informa que ',
};

const broadcastForm = ref({
  title: '',
  channel: 'sms',
  goip_line: 'both', // 'both', '1', '2'
  flight_number: '',
  flight_date: new Date().toISOString().split('T')[0],
  flight_time: '',
  status: '',
  origin: '',
  destination: '',
  manual_phones: '',
  template_type: 'flight_delay',
  message: smsTemplates.flight_delay,
  manifest_passengers: [],
  dispatch_immediate: true,
});

// Nombres personalizados / Bautizo de Líneas SIM (GoIP)
const lineAliases = ref({
  line1: localStorage.getItem('crm_goip_line1_name') || 'Línea 1 (SIM 1)',
  line2: localStorage.getItem('crm_goip_line2_name') || 'Línea 2 (SIM 2)',
});

const lineAliasInputs = ref({
  line1: lineAliases.value.line1,
  line2: lineAliases.value.line2,
});

const showLineNamingModal = ref(false);

function openLineNamingModal() {
  lineAliasInputs.value.line1 = lineAliases.value.line1;
  lineAliasInputs.value.line2 = lineAliases.value.line2;
  showLineNamingModal.value = true;
}

function saveLineAliases() {
  const l1 = lineAliasInputs.value.line1.trim() || 'Línea 1 (SIM 1)';
  const l2 = lineAliasInputs.value.line2.trim() || 'Línea 2 (SIM 2)';
  lineAliases.value.line1 = l1;
  lineAliases.value.line2 = l2;
  localStorage.setItem('crm_goip_line1_name', l1);
  localStorage.setItem('crm_goip_line2_name', l2);
  showLineNamingModal.value = false;
  showToast('Nombres de líneas SIM guardados con éxito.', 'success');
}

function resetLineAliases() {
  lineAliasInputs.value.line1 = 'Línea 1 (SIM 1)';
  lineAliasInputs.value.line2 = 'Línea 2 (SIM 2)';
}

const showExceededSmsConfirmModal = ref(false);

function onPortChange() {
  if (broadcastPreviewData.value) {
    fetchBroadcastPreview();
  }
}

const verifyingFlight = ref(false);
const processingQueue = ref(false);
const flightDetectedInfo = ref(null);
const showManualPhones = ref(false);
const showBulkManualInput = ref(false);
const manualPassengerInput = ref({
  surname: '',
  phone: '',
  pnr: '',
});

const parsedManualRecipients = computed(() => {
  const raw = (broadcastForm.value.manual_phones || '').trim();
  if (!raw) return [];
  const lines = raw.split(/[\r\n;]+/).map(l => l.trim()).filter(Boolean);
  const result = [];
  for (const line of lines) {
    const match = line.match(/(?:\+?591[\s\-]*)?([67]\d{7}|\d{8,12})/);
    if (match) {
      const phone = match[1].replace(/\D/g, '');
      let lineWithoutPhone = line.replace(match[0], '');
      
      let pnr = null;
      const pnrMatch = lineWithoutPhone.match(/\[([A-Z0-9]{5,8})\]|\(([A-Z0-9]{5,8})\)|(?:pnr[:\s]*)([A-Z0-9]{5,8})/i);
      if (pnrMatch) {
        pnr = (pnrMatch[1] || pnrMatch[2] || pnrMatch[3]).toUpperCase();
        lineWithoutPhone = lineWithoutPhone.replace(pnrMatch[0], '');
      }

      let namePart = lineWithoutPhone.replace(/[\s\-,;:\/|]+/g, ' ').trim();
      result.push({
        phone,
        surname: namePart ? namePart.toUpperCase() : 'Pasajero',
        pnr,
        raw: line,
      });
    }
  }
  return result;
});

function addManualPassengerRow() {
  const phone = (manualPassengerInput.value.phone || '').replace(/\D/g, '');
  if (phone.length < 8) {
    showToast('Ingresa un número de celular válido de al menos 8 dígitos.', 'warning');
    return;
  }
  const surname = (manualPassengerInput.value.surname || '').trim().toUpperCase() || 'Pasajero';
  const pnr = (manualPassengerInput.value.pnr || '').trim().toUpperCase();
  const entry = pnr ? `${phone} - ${surname} [${pnr}]` : `${phone} - ${surname}`;
  
  const current = (broadcastForm.value.manual_phones || '').trim();
  if (current) {
    broadcastForm.value.manual_phones = `${current}\n${entry}`;
  } else {
    broadcastForm.value.manual_phones = entry;
  }
  manualPassengerInput.value.surname = '';
  manualPassengerInput.value.phone = '';
  manualPassengerInput.value.pnr = '';
  showToast(`Pasajero ${surname} (${phone}) agregado.`, 'success');
  
  if (broadcastPreviewData.value) {
    fetchBroadcastPreview();
  }
}

function removeManualPassengerAt(index) {
  const lines = (broadcastForm.value.manual_phones || '')
    .split(/[\r\n;]+/)
    .map(l => l.trim())
    .filter(Boolean);
  if (index >= 0 && index < lines.length) {
    lines.splice(index, 1);
    broadcastForm.value.manual_phones = lines.join('\n');
    if (broadcastPreviewData.value) {
      fetchBroadcastPreview();
    }
  }
}

const gatewayStatus = ref({
  loading: false,
  online: true,
  data: null,
});

const isAutoBroadcasting = ref(false);
let autoBroadcastTimer = null;

const messageCompliance = computed(() => {
  const msg = (broadcastForm.value.message || '').toLowerCase();
  const hasIdentity = msg.includes('tamep') || msg.includes('eptam') || msg.includes('tam ep');
  const hasShortener = /(bit\.ly|tinyurl\.com|t\.co|goo\.gl|is\.gd|cutt\.ly)/i.test(broadcastForm.value.message || '');
  return {
    hasIdentity,
    hasShortener,
    safe: hasIdentity && !hasShortener,
  };
});

const broadcastDetailModal = ref({
  isOpen: false,
  loading: false,
  campaign: null,
  recipients: [],
  pagination: {
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
  },
});

const importedManifest = ref(null);
const showManifestTable = ref(false);
const manifestLoading = ref(false);
const isDraggingManifest = ref(false);

const airportCityNames = {
  VVI: 'Santa Cruz',
  SRZ: 'Santa Cruz',
  LPB: 'La Paz',
  CBB: 'Cochabamba',
  TJA: 'Tarija',
  SRE: 'Sucre',
  CIJ: 'Cobija',
  TDD: 'Trinidad',
  RIB: 'Riberalta',
  GYA: 'Guayaramerin',
  RBQ: 'Rurrenabaque',
  UYU: 'Uyuni',
  BYC: 'Yacuiba',
  POI: 'Potosi',
};

function resolveCity(code) {
  if (!code) return '';
  return airportCityNames[String(code).toUpperCase()] || code;
}

function extractPaternalSurname(fullName) {
  if (!fullName) return 'Pasajero';
  const parts = String(fullName).trim().split(/\s+/);
  if (!parts.length) return 'Pasajero';
  const first = parts[0].toUpperCase();
  if (first === 'DE' && parts.length > 2 && parts[1].toUpperCase() === 'LA') {
    return `${parts[0]} ${parts[1]} ${parts[2]}`;
  }
  if (['DE', 'DEL'].includes(first) && parts.length > 1) {
    return `${parts[0]} ${parts[1]}`;
  }
  return parts[0];
}

const previewSimulatedMessage = computed(() => {
  let text = broadcastForm.value.message || '';
  const pax = importedManifest.value?.passengers?.[0] || broadcastPreviewData.value?.sample_recipients?.[0] || {
    passenger_name: 'CALLIZAYA ANTONIO VICTOR HUGO',
    paternal_surname: 'CALLIZAYA',
    flight_number: broadcastForm.value.flight_number || '708',
    flight_date: broadcastForm.value.flight_date || '11/10/2026',
    origin: broadcastForm.value.origin || 'VVI',
    destination: broadcastForm.value.destination || 'LPB',
    booking_reference: 'UUA84U',
  };

  const surname = pax.paternal_surname || extractPaternalSurname(pax.passenger_name);
  const origCity = flightDetectedInfo.value?.origin_city || resolveCity(broadcastForm.value.origin || pax.origin);
  const destCity = flightDetectedInfo.value?.destination_city || resolveCity(broadcastForm.value.destination || pax.destination);

  let dateStr = broadcastForm.value.flight_date || pax.flight_date || '11/10/2026';
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    const [y, m, d] = dateStr.split('-');
    dateStr = `${d}/${m}/${y}`;
  }

  const timeStr = broadcastForm.value.flight_time || importedManifest.value?.departure_time || '15:30';

  // Normalizar fórmulas de saludo o prefijos al estándar "Sr/Sra {apellido}: "
  text = text.replace(/^(?:Estimad[oa]\s+pasajer[oa]|Sr(?:@|\(a\))?\.?)\s*(\{apellido\})?:\s*/iu, 'Sr/Sra {apellido}: ');
  text = text.replace(/^\{apellido(?:_paterno)?\}:\s*/iu, 'Sr/Sra {apellido}: ');

  // Si no contiene ninguna variable de apellido/pasajero ni prefijo, anteponer "Sr/Sra [Apellido]: "
  if (text && !/\{(?:apellido|apellido_paterno|pasajero|nombre)\}/i.test(text)) {
    text = `Sr/Sra ${surname}: ${text}`;
  }

  const cleanFlight = (broadcastForm.value.flight_number || pax.flight_number || '708').replace(/^TM-?/i, '');

  text = text.replace(/{apellido}|{apellido_paterno}/gi, surname);
  text = text.replace(/{pasajero}|{nombre}/gi, surname); // Solo apellido paterno, nunca nombre completo
  text = text.replace(/{vuelo}/gi, cleanFlight);
  text = text.replace(/{fecha}/gi, dateStr);
  text = text.replace(/{hora}/gi, timeStr);
  text = text.replace(/{origen}/gi, origCity);
  text = text.replace(/{destino}/gi, destCity);
  text = text.replace(/{ruta}/gi, (origCity && destCity) ? `${origCity}-${destCity}` : '');
  text = text.replace(/{pnr}/gi, pax.booking_reference || pax.pnr || 'UUA84U');

  return text;
});

const smsCharCount = computed(() => {
  return (previewSimulatedMessage.value || broadcastForm.value.message || '').length;
});

const smsSegmentCount = computed(() => {
  const len = smsCharCount.value;
  if (len === 0) return 1;
  if (len <= 160) return 1;
  return Math.ceil(len / 153);
});

const smsCostClass = computed(() => {
  if (smsCharCount.value <= 160) return 'text-emerald-600 dark:text-emerald-400';
  if (smsCharCount.value <= 306) return 'text-amber-600 dark:text-amber-400';
  return 'text-red-500';
});

function handleFileDrop(event) {
  isDraggingManifest.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (file) {
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
      processManifestPdfFile(file);
    } else {
      showToast('El archivo arrastrado debe ser un documento PDF válido.', 'error');
    }
  }
}

function handleManifestFileUpload(event) {
  const file = event.target.files?.[0];
  if (file) {
    processManifestPdfFile(file);
  }
  if (event.target) {
    event.target.value = '';
  }
}

async function processManifestPdfFile(file) {
  if (!file) return;

  manifestLoading.value = true;
  const formData = new FormData();
  formData.append('pdf_file', file);

  try {
    const res = await api.post('/admin/broadcasts/import-pdf', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (res.data?.success && res.data.manifest) {
      applyImportedManifest(res.data.manifest);
      showToast(res.data.message || 'Manifiesto de pasajeros cargado exitosamente.');
    }
  } catch (e) {
    console.error('Error subiendo PDF de manifiesto:', e);
    const serverErrors = e.response?.data?.errors;
    const errorMsg = serverErrors
      ? Object.values(serverErrors).flat().join('. ')
      : (e.response?.data?.message || 'Error al procesar el archivo PDF del manifiesto');
    showToast(errorMsg, 'error');
  } finally {
    manifestLoading.value = false;
  }
}

function applyImportedManifest(manifest) {
  importedManifest.value = manifest;
  showManifestTable.value = true;

  // Auto-completar los datos del formulario con el manifiesto
  if (manifest.flight_number) {
    broadcastForm.value.flight_number = manifest.flight_number;
  }
  if (manifest.departure_date) {
    broadcastForm.value.flight_date = manifest.departure_date;
  }
  if (manifest.origin) {
    broadcastForm.value.origin = manifest.origin;
  }
  if (manifest.destination) {
    broadcastForm.value.destination = manifest.destination;
  }
  if (manifest.departure_time) {
    broadcastForm.value.flight_time = manifest.departure_time;
  }

  // Guardar lista de pasajeros en el formulario
  broadcastForm.value.manifest_passengers = (manifest.passengers || []).map((p, idx) => ({
    ...p,
    num: p.num || idx + 1,
  }));
  importedManifest.value.passengers = broadcastForm.value.manifest_passengers;

  flightDetectedInfo.value = {
    source: 'manifest_pdf',
    flight_number: manifest.flight_number,
    origin: manifest.origin,
    destination: manifest.destination,
    origin_city: resolveCity(manifest.origin),
    destination_city: resolveCity(manifest.destination),
    departure_time: manifest.departure_time,
    departure_date: manifest.departure_date,
    departure_date_raw: manifest.departure_date_raw,
    valid_phones_count: manifest.valid_phones_count,
    total_passengers: manifest.total_passengers,
  };

  fetchBroadcastPreview();
}

const showEditFlightModal = ref(false);
const editingManifestIndex = ref(null);
const manifestSearchQuery = ref('');

const filteredManifestPassengers = computed(() => {
  const list = broadcastForm.value.manifest_passengers || [];
  const q = (manifestSearchQuery.value || '').trim().toLowerCase();
  if (!q) return list;
  return list.filter(p => 
    (p.passenger_name || '').toLowerCase().includes(q) ||
    (p.phone || '').includes(q) ||
    (p.pnr || '').toLowerCase().includes(q)
  );
});

function startEditManifestPassenger(idx) {
  editingManifestIndex.value = idx;
}

function saveManifestPassenger(idx) {
  const pax = filteredManifestPassengers.value[idx];
  if (pax) {
    pax.phone = (pax.phone || '').replace(/\D/g, '');
    pax.passenger_name = (pax.passenger_name || '').trim().toUpperCase();
    pax.paternal_surname = extractPaternalSurname(pax.passenger_name);
  }
  syncManifestCounts();
  editingManifestIndex.value = null;
  showToast('Pasajero del manifiesto actualizado.', 'success');
  if (broadcastPreviewData.value) {
    fetchBroadcastPreview();
  }
}

function cancelEditManifestPassenger() {
  editingManifestIndex.value = null;
}

function deleteManifestPassenger(idx) {
  const targetPax = filteredManifestPassengers.value[idx];
  if (!targetPax) return;
  if (confirm(`¿Eliminar al pasajero ${targetPax.passenger_name || 'seleccionado'} del manifiesto?`)) {
    const realIdx = broadcastForm.value.manifest_passengers.indexOf(targetPax);
    if (realIdx !== -1) {
      broadcastForm.value.manifest_passengers.splice(realIdx, 1);
    }
    syncManifestCounts();
    showToast('Pasajero eliminado del manifiesto.', 'info');
    if (broadcastPreviewData.value) {
      fetchBroadcastPreview();
    }
  }
}

function addManifestPassenger() {
  const newPax = {
    num: (broadcastForm.value.manifest_passengers?.length || 0) + 1,
    pnr: 'MANUAL',
    passenger_name: '',
    paternal_surname: '',
    phone: '',
    origin: broadcastForm.value.origin || 'VVI',
    destination: broadcastForm.value.destination || 'LPB',
    flight_number: broadcastForm.value.flight_number || '',
  };
  broadcastForm.value.manifest_passengers.unshift(newPax);
  manifestSearchQuery.value = '';
  editingManifestIndex.value = 0;
  syncManifestCounts();
}

function syncManifestCounts() {
  const list = broadcastForm.value.manifest_passengers || [];
  const valid = list.filter(p => (p.phone || '').replace(/\D/g, '').length >= 8).length;
  if (importedManifest.value) {
    importedManifest.value.total_passengers = list.length;
    importedManifest.value.valid_phones_count = valid;
    importedManifest.value.passengers = list;
  }
  if (flightDetectedInfo.value) {
    flightDetectedInfo.value.total_passengers = list.length;
    flightDetectedInfo.value.valid_phones_count = valid;
  }
}

function updateFlightDetectedInfo() {
  if (flightDetectedInfo.value) {
    flightDetectedInfo.value.flight_number = broadcastForm.value.flight_number;
    flightDetectedInfo.value.origin = broadcastForm.value.origin;
    flightDetectedInfo.value.destination = broadcastForm.value.destination;
    flightDetectedInfo.value.origin_city = resolveCity(broadcastForm.value.origin);
    flightDetectedInfo.value.destination_city = resolveCity(broadcastForm.value.destination);
    flightDetectedInfo.value.departure_time = broadcastForm.value.flight_time;
    flightDetectedInfo.value.departure_date = broadcastForm.value.flight_date;
  }
  if (importedManifest.value) {
    importedManifest.value.flight_number = broadcastForm.value.flight_number;
    importedManifest.value.origin = broadcastForm.value.origin;
    importedManifest.value.destination = broadcastForm.value.destination;
    importedManifest.value.departure_time = broadcastForm.value.flight_time;
    importedManifest.value.departure_date = broadcastForm.value.flight_date;
  }
  if (broadcastPreviewData.value) {
    fetchBroadcastPreview();
  }
}

const previewSearchQuery = ref('');

const filteredPreviewRecipients = computed(() => {
  const list = broadcastPreviewData.value?.recipients || broadcastPreviewData.value?.sample_recipients || [];
  const q = (previewSearchQuery.value || '').trim().toLowerCase();
  if (!q) return list;
  return list.filter(p => 
    (p.passenger_name || '').toLowerCase().includes(q) ||
    (p.phone || '').includes(q) ||
    (p.flight_number || '').toLowerCase().includes(q) ||
    (p.booking_reference || '').toLowerCase().includes(q)
  );
});

function excludeRecipientFromPreview(pax) {
  const cleanPhone = (pax.phone || '').replace(/\D/g, '');
  let removed = false;

  // 1. Si está en manifest_passengers
  if (broadcastForm.value.manifest_passengers?.length) {
    const idx = broadcastForm.value.manifest_passengers.findIndex(p => (p.phone || '').replace(/\D/g, '') === cleanPhone);
    if (idx !== -1) {
      broadcastForm.value.manifest_passengers.splice(idx, 1);
      syncManifestCounts();
      removed = true;
    }
  }

  // 2. Si está en manual_phones
  if (broadcastForm.value.manual_phones) {
    const lines = broadcastForm.value.manual_phones.split(/[\r\n;]+/).map(l => l.trim()).filter(Boolean);
    const newLines = lines.filter(l => !l.includes(cleanPhone));
    if (newLines.length !== lines.length) {
      broadcastForm.value.manual_phones = newLines.join('\n');
      removed = true;
    }
  }

  if (removed) {
    showToast(`Pasajero ${pax.passenger_name} (${cleanPhone}) excluido del envío.`, 'info');
    fetchBroadcastPreview();
  } else {
    // Si viene de CRM
    if (broadcastPreviewData.value?.recipients) {
      broadcastPreviewData.value.recipients = broadcastPreviewData.value.recipients.filter(p => (p.phone || '').replace(/\D/g, '') !== cleanPhone);
      broadcastPreviewData.value.total_recipients = broadcastPreviewData.value.recipients.length;
      if (broadcastPreviewData.value.sample_recipients) {
        broadcastPreviewData.value.sample_recipients = broadcastPreviewData.value.recipients;
      }
      showToast(`Pasajero ${pax.passenger_name} omitido de la lista.`, 'info');
    }
  }
}

function clearFlightDetected() {
  flightDetectedInfo.value = null;
  broadcastForm.value.flight_number = '';
  broadcastForm.value.flight_time = '';
  clearImportedManifest();
}

function clearImportedManifest() {
  importedManifest.value = null;
  showManifestTable.value = false;
  broadcastForm.value.manifest_passengers = [];
  if (flightDetectedInfo.value?.source === 'manifest_pdf') {
    flightDetectedInfo.value = null;
    broadcastForm.value.flight_number = '';
    broadcastForm.value.flight_time = '';
  }
  fetchBroadcastPreview();
  showToast('Manifiesto PDF removido. Se usarán los pasajeros registrados en el CRM.', 'info');
}

const smsEditorRef = ref(null);

const availableSmsTags = [
  '{apellido}',
  '{vuelo}',
  '{origen}',
  '{destino}',
  '{fecha}',
  '{hora}',
  '{pnr}',
];

function tagToChipHtml(tag) {
  return `<span class="sms-variable-chip inline-block px-1.5 py-0.5 mx-0.5 rounded bg-sky-100/80 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 border border-sky-300 dark:border-sky-800 font-mono text-[11px] font-semibold select-none shadow-2xs align-baseline cursor-default" contenteditable="false" data-tag="${tag}">${tag}</span>`;
}

function textToEditorHtml(text) {
  if (!text) return '';
  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  
  html = html.replace(/\{(apellido|vuelo|origen|destino|fecha|hora|pnr)\}/g, (match) => {
    return tagToChipHtml(match);
  });
  
  html = html.replace(/\n/g, '<br>');
  return html;
}

function editorHtmlToText(editorEl) {
  if (!editorEl) return '';
  let text = '';

  function traverse(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      text += node.textContent;
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      if (node.classList && node.classList.contains('sms-variable-chip')) {
        text += node.getAttribute('data-tag') || '';
      } else if (node.tagName === 'BR') {
        text += '\n';
      } else if (node.tagName === 'DIV' || node.tagName === 'P') {
        if (text.length > 0 && !text.endsWith('\n')) {
          text += '\n';
        }
        for (const child of node.childNodes) {
          traverse(child);
        }
      } else {
        for (const child of node.childNodes) {
          traverse(child);
        }
      }
    }
  }

  for (const child of editorEl.childNodes) {
    traverse(child);
  }

  return text;
}

function syncEditorContent(text) {
  if (smsEditorRef.value) {
    smsEditorRef.value.innerHTML = textToEditorHtml(text || '');
  }
}

function onEditorInput() {
  if (!smsEditorRef.value) return;
  const text = editorHtmlToText(smsEditorRef.value);
  broadcastForm.value.message = text;
}

function onEditorPaste(e) {
  e.preventDefault();
  const text = (e.clipboardData || window.clipboardData).getData('text/plain');
  if (!text) return;
  document.execCommand('insertText', false, text);
  onEditorInput();
}

function insertSmsTag(tag) {
  if (!smsEditorRef.value) return;
  smsEditorRef.value.focus();

  const sel = window.getSelection();
  let range = null;
  if (sel && sel.rangeCount > 0) {
    range = sel.getRangeAt(0);
    if (!smsEditorRef.value.contains(range.commonAncestorContainer)) {
      range = null;
    }
  }

  const chipEl = document.createElement('span');
  chipEl.className = 'sms-variable-chip inline-block px-1.5 py-0.5 mx-0.5 rounded bg-sky-100/80 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 border border-sky-300 dark:border-sky-800 font-mono text-[11px] font-semibold select-none shadow-2xs align-baseline cursor-default';
  chipEl.setAttribute('contenteditable', 'false');
  chipEl.setAttribute('data-tag', tag);
  chipEl.textContent = tag;

  const spaceNode = document.createTextNode(' ');

  if (range) {
    range.deleteContents();
    range.insertNode(spaceNode);
    range.insertNode(chipEl);
    range.setStartAfter(spaceNode);
    range.setEndAfter(spaceNode);
    sel.removeAllRanges();
    sel.addRange(range);
  } else {
    smsEditorRef.value.appendChild(chipEl);
    smsEditorRef.value.appendChild(spaceNode);
    const newRange = document.createRange();
    newRange.setStartAfter(spaceNode);
    newRange.setEndAfter(spaceNode);
    sel.removeAllRanges();
    sel.addRange(newRange);
  }

  onEditorInput();
}

function selectFlightSituation(type) {
  broadcastForm.value.template_type = type;
  if (smsTemplates[type]) {
    broadcastForm.value.message = smsTemplates[type];
    syncEditorContent(smsTemplates[type]);
  }
}

function applySmsTemplate(type) {
  selectFlightSituation(type);
}

function formatBroadcastDate(d) {
  if (!d) return '-';
  try {
    const date = new Date(d);
    return date.toLocaleString('es-BO', { 
      day: '2-digit', 
      month: '2-digit', 
      year: 'numeric', 
      hour: '2-digit', 
      minute: '2-digit' 
    });
  } catch (e) {
    return String(d);
  }
}

async function fetchBroadcastPreview() {
  broadcastPreviewLoading.value = true;
  try {
    const res = await api.post('/admin/broadcasts/preview', broadcastForm.value);
    if (res.data?.success) {
      broadcastPreviewData.value = res.data;
    }
  } catch (e) {
    console.error('Error previsualizando destinatarios:', e);
    showToast(e.response?.data?.message || 'Error al previsualizar destinatarios', 'error');
  } finally {
    broadcastPreviewLoading.value = false;
  }
}

async function submitBroadcastCampaign() {
  const count = broadcastPreviewData.value?.total_recipients || 0;
  if (count === 0) {
    showToast('No hay destinatarios seleccionados para el envío.', 'warning');
    return;
  }

  // ALERTA OBLIGATORIA SI EXCEDE LOS 160 CARACTERES
  if (smsSegmentCount.value > 1) {
    showExceededSmsConfirmModal.value = true;
    return;
  }

  // Confirmación estándar si está dentro del límite de 1 SMS (<= 160 caracteres)
  const channelLabel = broadcastForm.value.goip_line === 'both' 
    ? `SMS GoIP (Ambas Líneas: ${lineAliases.value.line1} y ${lineAliases.value.line2})` 
    : `SMS GoIP (${broadcastForm.value.goip_line === '1' ? lineAliases.value.line1 : lineAliases.value.line2})`;
  if (!confirm(`¿Iniciar el envío masivo mediante ${channelLabel} a ${count} pasajero(s)? (Longitud óptima: 1 SMS por pasajero)`)) {
    return;
  }

  await executeBroadcastCampaign();
}

function handleConfirmExceededAndSend() {
  showExceededSmsConfirmModal.value = false;
  executeBroadcastCampaign();
}

async function executeBroadcastCampaign() {
  broadcastSubmitting.value = true;
  try {
    const payload = {
      ...broadcastForm.value,
      channel: broadcastForm.value.channel || 'sms',
      goip_line: broadcastForm.value.goip_line || 'both',
      dispatch_immediate: broadcastForm.value.dispatch_immediate,
    };
    const res = await api.post('/admin/broadcasts', payload);
    if (res.data?.success) {
      showToast(`¡Campaña iniciada con éxito! El sistema enviará los SMS automáticamente.`);
      broadcastPreviewData.value = null;
      await fetchBroadcastCampaigns();

      // INICIAR DESPACHO Y ACTUALIZACIÓN AUTOMÁTICA EN SEGUNDO PLANO
      startAutoBroadcastEngine();
    }
  } catch (e) {
    console.error('Error al iniciar la campaña:', e);
    showToast(e.response?.data?.message || 'Error al iniciar la campaña.', 'error');
  } finally {
    broadcastSubmitting.value = false;
  }
}

async function fetchBroadcastCampaigns(page = 1) {
  broadcastLoading.value = true;
  try {
    const params = { page };
    if (broadcastFilters.value.flight_number) {
      params.flight_number = broadcastFilters.value.flight_number;
    }
    if (broadcastFilters.value.date) {
      params.date = broadcastFilters.value.date;
    }
    const res = await api.get('/admin/broadcasts', { params });
    if (res.data?.success) {
      const paginator = res.data.campaigns;
      broadcastCampaigns.value = paginator.data || [];
      broadcastPagination.value = {
        current_page: paginator.current_page || 1,
        last_page: paginator.last_page || 1,
        total: paginator.total || 0,
        from: paginator.from || 0,
        to: paginator.to || 0,
        per_page: paginator.per_page || 15,
      };
    }
  } catch (e) {
    console.error('Error cargando campañas:', e);
  } finally {
    broadcastLoading.value = false;
  }
}

function changeBroadcastPage(page) {
  if (page >= 1 && page <= broadcastPagination.value.last_page && page !== broadcastPagination.value.current_page) {
    fetchBroadcastCampaigns(page);
  }
}

function clearBroadcastFilters() {
  broadcastFilters.value.flight_number = '';
  broadcastFilters.value.date = '';
  fetchBroadcastCampaigns(1);
}

async function openCampaignDetails(campaign, page = 1) {
  broadcastDetailModal.value.isOpen = true;
  broadcastDetailModal.value.loading = true;
  broadcastDetailModal.value.campaign = campaign;
  try {
    const res = await api.get(`/admin/broadcasts/${campaign.id}`, { params: { page } });
    if (res.data?.success) {
      broadcastDetailModal.value.campaign = res.data.campaign;
      const paginator = res.data.recipients;
      broadcastDetailModal.value.recipients = paginator.data || [];
      broadcastDetailModal.value.pagination = {
        current_page: paginator.current_page || 1,
        last_page: paginator.last_page || 1,
        total: paginator.total || 0,
        from: paginator.from || 0,
        to: paginator.to || 0,
      };
    }
  } catch (e) {
    console.error('Error cargando detalle:', e);
  } finally {
    broadcastDetailModal.value.loading = false;
  }
}

function changeCampaignDetailsPage(page) {
  if (!broadcastDetailModal.value.campaign) return;
  openCampaignDetails(broadcastDetailModal.value.campaign, page);
}

async function retryFailedCampaign(campaign) {
  if (!confirm(`¿Reintentar el envío a los ${campaign.failed_count} pasajeros fallidos?`)) {
    return;
  }
  try {
    const res = await api.post(`/admin/broadcasts/${campaign.id}/retry-failed`);
    if (res.data?.success) {
      showToast(res.data.message);
      await fetchBroadcastCampaigns();
      startAutoBroadcastEngine();
      if (broadcastDetailModal.value.isOpen) {
        await openCampaignDetails(campaign);
      }
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Error al reintentar fallidos.', 'error');
  }
}

function isFlightSelected(flight) {
  if (!flight || !broadcastForm.value.flight_number) return false;
  const cleanCurrent = (broadcastForm.value.flight_number || '').replace(/^TM-?\s*/i, '').trim();
  const cleanFl = (flight.flight_number || '').replace(/^TM-?\s*/i, '').trim();
  return cleanCurrent === cleanFl;
}

function selectAvailableFlight(flight) {
  if (!flight) return;

  // Limpiar cualquier PDF previo para que no se quede precargado eternamente
  if (importedManifest.value || broadcastForm.value.manifest_passengers.length) {
    importedManifest.value = null;
    showManifestTable.value = false;
    broadcastForm.value.manifest_passengers = [];
  }

  const cleanNumber = (flight.flight_number || '').replace(/^TM-?\s*/i, '').trim();
  broadcastForm.value.flight_number = cleanNumber;
  if (flight.departure_time) {
    broadcastForm.value.flight_time = flight.departure_time;
  }
  if (flight.origin) {
    broadcastForm.value.origin = flight.origin;
  }
  if (flight.destination) {
    broadcastForm.value.destination = flight.destination;
  }
  if (flight.departure_date) {
    broadcastForm.value.flight_date = flight.departure_date;
  }

  flightDetectedInfo.value = {
    source: 'transclick',
    flight_number: cleanNumber,
    origin: flight.origin || broadcastForm.value.origin,
    destination: flight.destination || broadcastForm.value.destination,
    origin_city: flight.origin_city || resolveCity(flight.origin || broadcastForm.value.origin),
    destination_city: flight.destination_city || resolveCity(flight.destination || broadcastForm.value.destination),
    departure_time: flight.departure_time || broadcastForm.value.flight_time || 'N/A',
    departure_date: flight.departure_date || broadcastForm.value.flight_date,
    status: flight.status || 'Programado',
  };

  const routeStr = `${flightDetectedInfo.value.origin_city} → ${flightDetectedInfo.value.destination_city}`;
  showToast(`Vuelo TM-${cleanNumber} seleccionado (${routeStr} - ${flight.departure_time || 'N/A'}).`);
  fetchBroadcastPreview();
}

function onFlightDateOrRouteChange() {
  // Limpiar manifiesto PDF previo si se cambia la fecha o ruta de búsqueda
  if (importedManifest.value || broadcastForm.value.manifest_passengers.length) {
    importedManifest.value = null;
    showManifestTable.value = false;
    broadcastForm.value.manifest_passengers = [];
  }
  if (broadcastForm.value.flight_date) {
    verifyFlightWithTransclick();
  }
}

async function verifyFlightWithTransclick() {
  if (!broadcastForm.value.flight_date) {
    showToast('Seleccione la fecha del vuelo para consultar Transclick', 'error');
    return;
  }
  verifyingFlight.value = true;
  availableFlightsList.value = [];
  try {
    const res = await api.post('/admin/broadcasts/check-flight', {
      origin: broadcastForm.value.origin || '',
      destination: broadcastForm.value.destination || '',
      flight_date: broadcastForm.value.flight_date || '',
      flight_number: broadcastForm.value.flight_number || '',
    });
    if (res.data?.success && res.data.found && res.data.flights?.length) {
      availableFlightsList.value = res.data.flights;
      showToast(`Transclick: ${res.data.flights.length} vuelo(s) programado(s) encontrado(s).`);

      // Si solo hay un vuelo disponible, seleccionarlo automáticamente
      if (res.data.flights.length === 1) {
        selectAvailableFlight(res.data.flights[0]);
      } else if (broadcastForm.value.flight_number) {
        const clean = (broadcastForm.value.flight_number || '').replace(/^TM-?\s*/i, '').trim();
        const found = res.data.flights.find(f => (f.flight_number || '').replace(/^TM-?\s*/i, '').trim() === clean);
        if (found) {
          selectAvailableFlight(found);
        }
      }
    } else {
      availableFlightsList.value = [];
      flightDetectedInfo.value = null;
      showToast(res.data?.message || 'No se encontraron vuelos programados para esa fecha.', 'info');
    }
  } catch (e) {
    console.error('Error al verificar vuelo con Transclick:', e);
    showToast(e.response?.data?.message || 'Error al conectar con Transclick.', 'error');
  } finally {
    verifyingFlight.value = false;
  }
}

async function triggerProcessQueue() {
  processingQueue.value = true;
  try {
    const res = await api.post('/admin/broadcasts/process-queue');
    if (res.data?.success) {
      showToast(res.data.message || 'Cola de mensajes procesada.');
      await fetchBroadcastCampaigns();
    }
  } catch (e) {
    console.error('Error al procesar la cola SMS:', e);
    showToast(e.response?.data?.message || 'Error al procesar la cola SMS.', 'error');
  } finally {
    processingQueue.value = false;
  }
}

// ========================================================
// MOTOR AUTOMÁTICO DE DESPACHO Y ACTUALIZACIÓN EN VIVO
// ========================================================
function startAutoBroadcastEngine() {
  if (isAutoBroadcasting.value && autoBroadcastTimer) return;
  isAutoBroadcasting.value = true;
  runAutoBroadcastCycle();
  autoBroadcastTimer = setInterval(runAutoBroadcastCycle, 4000);
}

function stopAutoBroadcastEngine() {
  isAutoBroadcasting.value = false;
  if (autoBroadcastTimer) {
    clearInterval(autoBroadcastTimer);
    autoBroadcastTimer = null;
  }
}

async function runAutoBroadcastCycle() {
  if (processingQueue.value) return;
  try {
    processingQueue.value = true;
    const res = await api.post('/admin/broadcasts/process-queue');
    const remaining = res.data?.remaining_count ?? 0;

    // Actualización silenciosa de campañas en vivo
    await fetchBroadcastCampaignsSilent();

    // Si el modal de detalle está abierto, refrescar también en vivo
    if (broadcastDetailModal.value.isOpen && broadcastDetailModal.value.campaign?.id) {
      const detailRes = await api.get(`/admin/broadcasts/${broadcastDetailModal.value.campaign.id}`);
      if (detailRes.data?.success) {
        broadcastDetailModal.value.campaign = detailRes.data.campaign;
        broadcastDetailModal.value.recipients = detailRes.data.recipients.data || [];
      }
    }

    // Verificar si quedan campañas activas pendientes
    const hasActive = broadcastCampaigns.value.some(c => ['processing', 'pending'].includes(c.status));
    if (remaining === 0 && !hasActive) {
      stopAutoBroadcastEngine();
    }
  } catch (e) {
    console.warn('AutoBroadcastEngine ciclo falló:', e);
  } finally {
    processingQueue.value = false;
  }
}

async function fetchBroadcastCampaignsSilent() {
  try {
    const params = {};
    if (broadcastFilters.value.flight_number) params.flight_number = broadcastFilters.value.flight_number;
    if (broadcastFilters.value.date) params.date = broadcastFilters.value.date;
    const res = await api.get('/admin/broadcasts', { params });
    if (res.data?.success) {
      broadcastCampaigns.value = res.data.campaigns.data || [];
    }
  } catch (e) {
    // Ignorar en ciclo silencioso
  }
}

async function cancelBroadcastCampaign(campaign) {
  if (!campaign || !campaign.id) return;
  if (!confirm(`¿Seguro que deseas cancelar el envío de la campaña "${campaign.title}"? Los mensajes que aún no han salido serán cancelados y no se consumirá saldo.`)) {
    return;
  }
  try {
    const res = await api.post(`/admin/broadcasts/${campaign.id}/cancel`);
    if (res.data?.success) {
      showToast(res.data.message || 'Campaña cancelada con éxito.', 'info');
      await fetchBroadcastCampaigns();
      if (broadcastDetailModal.value.isOpen && broadcastDetailModal.value.campaign?.id === campaign.id) {
        broadcastDetailModal.value.campaign = res.data.campaign;
        const detailRes = await api.get(`/admin/broadcasts/${campaign.id}`);
        if (detailRes.data?.success) {
          broadcastDetailModal.value.recipients = detailRes.data.recipients.data || [];
        }
      }
    }
  } catch (e) {
    console.error('Error al cancelar campaña:', e);
    showToast(e.response?.data?.message || 'Error al cancelar la campaña.', 'error');
  }
}

async function fetchGatewayStatus() {
  gatewayStatus.value.loading = true;
  try {
    const res = await api.get('/admin/broadcasts/gateway-status');
    if (res.data?.success) {
      gatewayStatus.value.data = res.data.gateway;
      gatewayStatus.value.online = res.data.gateway?.online ?? true;
    }
  } catch (e) {
    console.warn('No se pudo verificar el estado del gateway GoIP:', e);
  } finally {
    gatewayStatus.value.loading = false;
  }
}

function toggleAutoQueueWorker() {
  autoQueueWorker.value.active = !autoQueueWorker.value.active;
  if (autoQueueWorker.value.active) {
    showToast('Auto-procesador de cola SMS activado (Web Worker en segundo plano).', 'info');
    runAutoQueueWorkerCycle();
    autoQueueWorker.value.intervalId = setInterval(runAutoQueueWorkerCycle, 6000);
  } else {
    if (autoQueueWorker.value.intervalId) {
      clearInterval(autoQueueWorker.value.intervalId);
      autoQueueWorker.value.intervalId = null;
    }
    showToast('Auto-procesador de cola SMS detenido.', 'info');
  }
}

async function runAutoQueueWorkerCycle() {
  if (processingQueue.value) return;
  try {
    const res = await api.post('/admin/broadcasts/process-queue');
    if (res.data?.success) {
      autoQueueWorker.value.pendingCount = res.data.remaining_count ?? 0;
      if (res.data.remaining_count === 0 && autoQueueWorker.value.active) {
        await fetchBroadcastCampaigns();
      }
    }
  } catch (e) {
    console.warn('AutoQueueWorker ciclo falló:', e);
  }
}

async function handleLogout() {
  await authStore.logout();
  router.push('/login');
}
</script>
