<template>
  <div class="space-y-6">
    <!-- 1. HEADER DE ESTADO Y MÉTRICAS DE CONEXIÓN A ISSABEL -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start md:items-center space-x-4">
          <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0">
            <Headphones class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center space-x-3">
              <h2 class="text-xl font-bold text-slate-800 dark:text-white">Avisos Call Center (Issabel PBX)</h2>
              <button
                type="button"
                @click="showConnectionDetails"
                :class="serverStatus.connected 
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' 
                  : 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border-rose-200 dark:border-rose-800 hover:bg-rose-200 dark:hover:bg-rose-900/40 cursor-pointer'"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border transition-all"
                :title="serverStatus.connected ? 'AMI Conectado - Click para ver detalles' : 'Click para ver diagnóstico del error'"
              >
                <span 
                  :class="serverStatus.connected ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'" 
                  class="w-2 h-2 rounded-full mr-1.5"
                ></span>
                {{ serverStatus.connected ? 'AMI Conectado' : 'Desconectado (Ver error)' }}
              </button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Servidor: <span class="font-mono font-medium">{{ serverStatus.host }}:{{ serverStatus.port }}</span> • 
              Driver: <span class="font-semibold uppercase text-purple-600 dark:text-purple-400">{{ serverStatus.sip_driver }}</span> • 
              Versión: <span class="font-mono">{{ serverStatus.banner || 'Asterisk AMI' }}</span>
            </p>
          </div>
        </div>

        <!-- Botones de Acción Rápida -->
        <div class="flex items-center space-x-2 shrink-0">
          <button
            @click="fetchServerStatus(true)"
            :disabled="loadingStatus"
            class="inline-flex items-center space-x-1.5 px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors disabled:opacity-50"
            title="Verificar conexión con Asterisk y mostrar diagnóstico"
          >
            <RefreshCw :class="{ 'animate-spin': loadingStatus }" class="w-3.5 h-3.5" />
            <span>Verificar Conexión</span>
          </button>

          <button
            @click="fetchExtensions"
            :disabled="loadingExtensions"
            class="inline-flex items-center space-x-1.5 px-3 py-2 text-xs font-medium rounded-xl bg-purple-600 hover:bg-purple-700 text-white transition-colors disabled:opacity-50 shadow-sm"
          >
            <Users :class="{ 'animate-spin': loadingExtensions }" class="w-3.5 h-3.5" />
            <span>Refrescar Extensiones</span>
          </button>
        </div>
      </div>

      <!-- Métricas rápidas de extensiones -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-100 dark:border-slate-800">
        <div class="bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium block">Total Extensiones</span>
          <span class="text-2xl font-bold text-slate-800 dark:text-white mt-1 block">{{ extensions.length }}</span>
        </div>
        <div class="bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium block">Extensiones Online</span>
          <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{{ onlineExtensionsCount }}</span>
        </div>
        <div class="bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium block">Ext. Seleccionadas</span>
          <span class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1 block">{{ selectedExtensions.length }}</span>
        </div>
        <div class="bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium block">Total Comunicados</span>
          <span class="text-2xl font-bold text-slate-800 dark:text-white mt-1 block">{{ pagination.total || broadcasts.length }}</span>
        </div>
      </div>
    </div>

    <!-- 2. FORMULARIO PRINCIPAL DE EMISIÓN (2 COLUMNAS) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Columna Izquierda: Redacción & Destinatarios (7 columnas) -->
      <div class="lg:col-span-7 space-y-6">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-base font-semibold text-slate-800 dark:text-white flex items-center space-x-2">
              <Send class="w-4 h-4 text-purple-600 dark:text-purple-400" />
              <span>Nuevo Comunicado</span>
            </h3>
            <span class="text-xs text-slate-500 dark:text-slate-400">
              Extensión Emisora: <span class="font-bold text-purple-600 dark:text-purple-400">{{ form.source_extension || '1' }}</span>
            </span>
          </div>

          <!-- Título y Extensión Emisora -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2 space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Título / Asunto del Comunicado <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="form.title"
                type="text"
                placeholder="Ej. Aviso de Guardia - Turno Mañana"
                class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Extensión Remitente
              </label>
              <input
                v-model="form.source_extension"
                type="text"
                placeholder="1"
                class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
              />
            </div>
          </div>

          <!-- Plantillas Rápidas -->
          <div class="space-y-1.5">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 block">Plantillas Rápidas:</span>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="(tpl, idx) in quickTemplates"
                :key="idx"
                type="button"
                @click="applyTemplate(tpl)"
                class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-purple-50 hover:border-purple-300 dark:hover:bg-purple-950/40 text-slate-700 dark:text-slate-300 transition-colors"
              >
                {{ tpl.label }}
              </button>
            </div>
          </div>

          <!-- Mensaje -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Mensaje SIP para Softphones <span class="text-rose-500">*</span>
              </label>
              <span class="text-xs text-slate-400">{{ form.message.length }} / 2000 caracteres</span>
            </div>
            <textarea
              v-model="form.message"
              rows="4"
              placeholder="Escribe el aviso que recibirán los operadores en sus softphones..."
              class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
            ></textarea>
          </div>

          <!-- Adjuntar Imagen -->
          <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Adjuntar Imagen (Opcional)
            </label>
            <div
              v-if="!form.image_url"
              class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-purple-400 rounded-xl p-4 text-center transition-colors cursor-pointer bg-slate-50/50 dark:bg-slate-800/30"
              @click="$refs.fileInput.click()"
            >
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleFileUpload"
              />
              <div class="flex flex-col items-center justify-center space-y-1.5">
                <Upload :class="{ 'animate-bounce': uploadingImage }" class="w-6 h-6 text-slate-400" />
                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">
                  {{ uploadingImage ? 'Subiendo imagen...' : 'Haz clic para seleccionar o arrastra una imagen (JPG, PNG, WEBP)' }}
                </span>
                <span class="text-[11px] text-slate-400">Máximo 10 MB • Se generará enlace directo para softphones</span>
              </div>
            </div>

            <!-- Miniatura de Imagen Adjunta -->
            <div v-else class="relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 p-2 bg-slate-50 dark:bg-slate-800 flex items-center justify-between">
              <div class="flex items-center space-x-3 truncate">
                <img :src="imagePreviewUrl" alt="Adjunto" class="w-12 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700" />
                <div class="truncate">
                  <span class="text-xs font-medium text-slate-800 dark:text-slate-200 block truncate">Imagen adjunta lista</span>
                  <a :href="form.image_url" target="_blank" class="text-[11px] text-purple-600 hover:underline flex items-center space-x-1">
                    <span>Ver original</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
              </div>
              <button
                @click="removeImage"
                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                title="Quitar imagen"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Selector de Extensiones SIP -->
          <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                  Extensiones Destino <span class="text-rose-500">*</span>
                </label>
                <span class="text-xs text-slate-500">
                  {{ selectedExtensions.length }} seleccionadas de {{ extensions.length }} disponibles
                </span>
              </div>

              <!-- Botones de Selección Rápida -->
              <div class="flex items-center space-x-1.5 shrink-0">
                <button
                  type="button"
                  @click="selectAllExtensions"
                  class="px-2 py-1 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium"
                >
                  Todas
                </button>
                <button
                  type="button"
                  @click="selectOnlineExtensions"
                  class="px-2 py-1 text-xs rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 text-emerald-700 dark:text-emerald-300 font-medium"
                >
                  Solo Online ({{ onlineExtensionsCount }})
                </button>
                <button
                  type="button"
                  @click="clearSelectedExtensions"
                  class="px-2 py-1 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 font-medium"
                >
                  Limpiar
                </button>
              </div>
            </div>

            <!-- Filtro rápido de búsqueda de extensión -->
            <div class="relative">
              <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="extensionSearch"
                type="text"
                placeholder="Buscar por número de extensión o IP..."
                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>

            <!-- Grid de Extensiones -->
            <div v-if="filteredExtensions.length > 0" class="max-h-56 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-3 gap-2 border border-slate-200 dark:border-slate-800 rounded-xl p-2.5 bg-slate-50/50 dark:bg-slate-900/50">
              <label
                v-for="ext in filteredExtensions"
                :key="ext.extension"
                :class="selectedExtensions.includes(ext.extension)
                  ? 'border-purple-500 bg-purple-50/50 dark:bg-purple-950/30 dark:border-purple-500'
                  : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800'"
                class="flex items-center space-x-2.5 p-2 rounded-lg border cursor-pointer hover:border-purple-300 dark:hover:border-purple-600 transition-all select-none"
              >
                <input
                  type="checkbox"
                  :value="ext.extension"
                  v-model="selectedExtensions"
                  class="rounded text-purple-600 focus:ring-purple-500 border-slate-300 dark:border-slate-600 w-3.5 h-3.5"
                />
                <div class="truncate flex-1">
                  <div class="flex items-center space-x-1.5">
                    <span class="text-xs font-bold text-slate-800 dark:text-white">Ext {{ ext.extension }}</span>
                    <span
                      :class="ext.online ? 'bg-emerald-500' : 'bg-slate-400'"
                      class="w-1.5 h-1.5 rounded-full shrink-0"
                      :title="ext.online ? 'Online' : 'Offline'"
                    ></span>
                  </div>
                  <span class="text-[10px] text-slate-400 block truncate font-mono">{{ ext.ip }}</span>
                </div>
              </label>
            </div>
            <div v-else class="text-center py-6 text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
              {{ loadingExtensions ? 'Consultando extensiones en Issabel...' : 'No se encontraron extensiones registradas.' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Columna Derecha: Previsualización en Vivo y Disparo (5 columnas) -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
          <h3 class="text-base font-semibold text-slate-800 dark:text-white flex items-center space-x-2 pb-3 border-b border-slate-100 dark:border-slate-800">
            <Eye class="w-4 h-4 text-purple-600 dark:text-purple-400" />
            <span>Vista Previa del Softphone</span>
          </h3>

          <!-- Mockup Visual de Pantalla de Softphone / Teléfono IP -->
          <div class="bg-slate-950 rounded-2xl p-4 border-4 border-slate-800 shadow-xl text-slate-100 space-y-3 font-sans">
            <!-- Barra superior del Softphone -->
            <div class="flex items-center justify-between pb-2 border-b border-slate-800 text-[11px] text-slate-400">
              <span class="flex items-center space-x-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>SIP MESSAGE</span>
              </span>
              <span>De: Ext {{ form.source_extension || '1' }}</span>
            </div>

            <!-- Cabecera del aviso -->
            <div class="bg-slate-900/90 rounded-xl p-3 border border-slate-800">
              <span class="text-[10px] uppercase tracking-wider font-bold text-purple-400 block">
                {{ form.title || 'COMUNICADO OFICIAL' }}
              </span>
              <p class="text-xs text-slate-200 mt-2 whitespace-pre-line leading-relaxed">
                {{ form.message || 'El texto que escribas en el formulario se visualizará aquí en tiempo real tal como lo recibirá el softphone del operador.' }}
              </p>

              <!-- Vista previa de imagen dentro del mockup -->
              <div v-if="form.image_url || form.local_preview" class="mt-3 rounded-lg overflow-hidden border border-slate-800 bg-black/40">
                <img :src="imagePreviewUrl" alt="Adjunto" class="w-full max-h-40 object-cover" />
                <span class="text-[10px] text-slate-400 p-1.5 block bg-slate-950/80 truncate font-mono">
                  📷 {{ form.image_url || 'Cargando archivo...' }}
                </span>
              </div>
            </div>

            <!-- Resumen de Destinatarios -->
            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
              <span>Destinatarios:</span>
              <span class="font-bold text-white">{{ selectedExtensions.length }} operadores</span>
            </div>
          </div>

          <!-- Botón Principal de Envío -->
          <button
            @click="submitBroadcast"
            :disabled="sending || selectedExtensions.length === 0 || !form.title.trim() || !form.message.trim()"
            class="w-full py-3.5 px-4 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
          >
            <Loader2 v-if="sending" class="w-4 h-4 animate-spin" />
            <Send v-else class="w-4 h-4" />
            <span>{{ sending ? 'Despachando a Issabel...' : `Emitir a ${selectedExtensions.length} Extensión(es)` }}</span>
          </button>

          <p class="text-[11px] text-slate-400 text-center leading-normal">
            El mensaje se enviará por Asterisk AMI de forma instantánea a los softphones activos.
          </p>
        </div>
      </div>
    </div>

    <!-- 3. TABLA DE HISTORIAL DE COMUNICADOS EMITIDOS -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h3 class="text-base font-bold text-slate-800 dark:text-white">Historial de Comunicados Call Center</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Registro de todas las emisiones enviadas a las extensiones</p>
        </div>
        <button
          @click="fetchBroadcasts(1)"
          :disabled="loadingHistory"
          class="inline-flex items-center space-x-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 self-start sm:self-auto"
        >
          <RefreshCw :class="{ 'animate-spin': loadingHistory }" class="w-3.5 h-3.5" />
          <span>Actualizar Historial</span>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="py-3 px-4">ID</th>
              <th class="py-3 px-4">Fecha y Hora</th>
              <th class="py-3 px-4">Título / Remitente</th>
              <th class="py-3 px-4">Mensaje</th>
              <th class="py-3 px-4 text-center">Adjunto</th>
              <th class="py-3 px-4 text-center">Entrega</th>
              <th class="py-3 px-4 text-center">Estado</th>
              <th class="py-3 px-4 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
            <tr v-if="loadingHistory && broadcasts.length === 0">
              <td colspan="8" class="text-center py-8 text-slate-400">
                <Loader2 class="w-5 h-5 animate-spin mx-auto mb-2" />
                Cargando historial...
              </td>
            </tr>
            <tr v-else-if="broadcasts.length === 0">
              <td colspan="8" class="text-center py-8 text-slate-400">
                No se han emitido comunicados aún.
              </td>
            </tr>
            <tr
              v-for="b in broadcasts"
              :key="b.id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors"
            >
              <td class="py-3 px-4 font-mono font-medium">#{{ b.id }}</td>
              <td class="py-3 px-4 whitespace-nowrap text-slate-500">
                {{ formatDate(b.created_at) }}
              </td>
              <td class="py-3 px-4">
                <span class="font-bold text-slate-800 dark:text-white block">{{ b.title }}</span>
                <span class="text-[11px] text-slate-400">De Ext: {{ b.source_extension }}</span>
              </td>
              <td class="py-3 px-4 max-w-xs truncate" :title="b.message">
                {{ b.message }}
              </td>
              <td class="py-3 px-4 text-center">
                <a
                  v-if="b.image_url"
                  :href="b.image_url"
                  target="_blank"
                  class="inline-flex items-center space-x-1 text-purple-600 hover:text-purple-700 text-xs font-semibold"
                >
                  <Upload class="w-3.5 h-3.5" />
                  <span>Ver Foto</span>
                </a>
                <span v-else class="text-slate-400">-</span>
              </td>
              <td class="py-3 px-4 text-center whitespace-nowrap">
                <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ b.sent_count }}</span>
                <span class="text-slate-400"> / {{ b.total_extensions }}</span>
                <span v-if="b.failed_count > 0" class="text-rose-500 font-bold ml-1">({{ b.failed_count }} fallos)</span>
              </td>
              <td class="py-3 px-4 text-center">
                <span
                  :class="{
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400': b.status === 'completed',
                    'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400': b.status === 'partial',
                    'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400': b.status === 'failed',
                  }"
                  class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider"
                >
                  {{ b.status === 'completed' ? 'Completado' : b.status === 'partial' ? 'Parcial' : 'Fallido' }}
                </span>
              </td>
              <td class="py-3 px-4 text-right whitespace-nowrap">
                <div class="inline-flex items-center space-x-1.5">
                  <button
                    @click="viewDetails(b)"
                    class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors"
                    title="Ver detalles por extensión"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="b.failed_count > 0"
                    @click="retryFailed(b)"
                    class="p-1.5 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 hover:bg-amber-100 text-amber-600 dark:text-amber-400 transition-colors"
                    title="Reintentar extensiones fallidas"
                  >
                    <RotateCcw class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-500">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
        <div class="flex items-center space-x-1">
          <button
            :disabled="pagination.current_page <= 1"
            @click="fetchBroadcasts(pagination.current_page - 1)"
            class="px-2.5 py-1 rounded border border-slate-200 dark:border-slate-700 disabled:opacity-40"
          >
            Anterior
          </button>
          <button
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchBroadcasts(pagination.current_page + 1)"
            class="px-2.5 py-1 rounded border border-slate-200 dark:border-slate-700 disabled:opacity-40"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- 4. MODAL DE DETALLES DEL COMUNICADO -->
    <div
      v-if="selectedBroadcast"
      class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
        <!-- Cabecera del Modal -->
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="text-base font-bold text-slate-800 dark:text-white">
              Detalle del Comunicado #{{ selectedBroadcast.id }}
            </h3>
            <span class="text-xs text-slate-500">{{ selectedBroadcast.title }} • De: Ext {{ selectedBroadcast.source_extension }}</span>
          </div>
          <button
            @click="selectedBroadcast = null"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Contenido Scrollable -->
        <div class="p-5 space-y-4 overflow-y-auto flex-1">
          <!-- Mensaje -->
          <div class="bg-slate-50 dark:bg-slate-800/60 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800">
            <span class="text-xs font-semibold text-slate-500 block mb-1">Mensaje Emitido:</span>
            <p class="text-xs text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed">{{ selectedBroadcast.message }}</p>
            <div v-if="selectedBroadcast.image_url" class="mt-3">
              <a :href="selectedBroadcast.image_url" target="_blank" class="text-xs text-purple-600 hover:underline flex items-center space-x-1 font-semibold">
                <Upload class="w-3.5 h-3.5" />
                <span>Ver Imagen Adjunta</span>
              </a>
            </div>
          </div>

          <!-- Tabla de Extensiones -->
          <div>
            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
              Estado por Extensión ({{ selectedBroadcast.recipients?.length || 0 }})
            </h4>
            <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                  <tr>
                    <th class="py-2.5 px-3">Extensión</th>
                    <th class="py-2.5 px-3">Estado</th>
                    <th class="py-2.5 px-3">Respuesta de Asterisk</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="r in selectedBroadcast.recipients" :key="r.id">
                    <td class="py-2.5 px-3 font-bold font-mono">Ext {{ r.extension }}</td>
                    <td class="py-2.5 px-3">
                      <span
                        :class="r.status === 'sent'
                          ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50'
                          : 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/50'"
                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase inline-flex items-center space-x-1"
                      >
                        <Check v-if="r.status === 'sent'" class="w-3 h-3 mr-0.5" />
                        <X v-else class="w-3 h-3 mr-0.5" />
                        <span>{{ r.status === 'sent' ? 'Entregado' : 'Fallido' }}</span>
                      </span>
                    </td>
                    <td class="py-2.5 px-3 text-slate-500 text-[11px] truncate max-w-xs">
                      {{ r.response_message || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer Modal -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-end">
          <button
            @click="selectedBroadcast = null"
            class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-white hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/api/client';
import Swal from 'sweetalert2';
import {
  Headphones, Users, Send, Upload, RefreshCw, Eye, Search,
  Trash2, ExternalLink, Loader2, Check, X, RotateCcw
} from 'lucide-vue-next';

// 1. Estado del Servidor Issabel
const serverStatus = ref({
  connected: false,
  host: '186.121.251.146',
  port: 5038,
  source_extension: '1',
  sip_driver: 'sip',
  banner: '',
  message: '',
  error_type: null,
  diagnostic: null,
});
const loadingStatus = ref(false);

// 2. Extensiones
const extensions = ref([]);
const loadingExtensions = ref(false);
const selectedExtensions = ref([]);
const extensionSearch = ref('');

// 3. Formulario
const form = ref({
  title: '',
  source_extension: '1',
  message: '',
  image_url: '',
  image_path: '',
  image_size: 0,
  image_mime: '',
  image_name: '',
  local_preview: '',
  rcs_xml: '',
});

const imagePreviewUrl = computed(() => {
  if (form.value.local_preview) return form.value.local_preview;
  if (form.value.image_path) return `/storage/${form.value.image_path}`;
  return form.value.image_url;
});
const uploadingImage = ref(false);
const sending = ref(false);

// 4. Historial
const broadcasts = ref([]);
const loadingHistory = ref(false);
const selectedBroadcast = ref(null);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

// Plantillas Rápidas
const quickTemplates = [
  {
    label: '🚨 Aviso Urgente',
    title: 'Aviso Urgente Call Center',
    message: 'ATENCIÓN AGENTES: Comunicado de carácter urgente para todo el personal de guardia en call center.',
  },
  {
    label: '✈️ Vuelo Demorado',
    title: 'Alerta Operativa: Vuelo Demorado',
    message: 'Estimados agentes, el vuelo programado para hoy presenta una demora de itinerario. Por favor priorizar la atención de pasajeros afectados.',
  },
  {
    label: '📢 Reunión Operativa',
    title: 'Convocatoria: Reunión Operativa',
    message: 'Se solicita la presencia de todos los operadores en la sala de coordinación a las 15:00 horas.',
  },
  {
    label: '🔄 Cambio de Turno',
    title: 'Novedades de Cambio de Turno',
    message: 'Recordatorio para el pase de guardia: verificar bandejas de pendientes y registrar las novedades antes de cerrar sesión.',
  },
];

// Computed
const onlineExtensionsCount = computed(() => {
  return extensions.value.filter(e => e.online).length;
});

const filteredExtensions = computed(() => {
  const q = extensionSearch.value.trim().toLowerCase();
  if (!q) return extensions.value;
  return extensions.value.filter(e =>
    e.extension.toLowerCase().includes(q) ||
    e.ip.toLowerCase().includes(q)
  );
});

// Métodos de Selección
function selectAllExtensions() {
  selectedExtensions.value = extensions.value.map(e => e.extension);
}

function selectOnlineExtensions() {
  selectedExtensions.value = extensions.value.filter(e => e.online).map(e => e.extension);
}

function clearSelectedExtensions() {
  selectedExtensions.value = [];
}

function applyTemplate(tpl) {
  form.value.title = tpl.title;
  form.value.message = tpl.message;
}

// Carga de Datos y Diagnóstico de Conexión
async function fetchServerStatus(manual = false) {
  loadingStatus.value = true;
  try {
    const res = await api.get('/admin/callcenter/status');
    if (res.data?.success) {
      serverStatus.value = {
        connected: !!res.data.connected,
        host: res.data.host || '186.121.251.146',
        port: res.data.port || 5038,
        source_extension: res.data.source_extension || '1',
        sip_driver: res.data.sip_driver || 'sip',
        banner: res.data.banner || '',
        message: res.data.message || '',
        error_type: res.data.error_type || null,
        diagnostic: res.data.diagnostic || null,
      };
      form.value.source_extension = res.data.source_extension || '1';

      // Impresión de Diagnóstico detallado en Consola del Navegador
      console.groupCollapsed(
        `%c[CALL CENTER PBX] ${res.data.connected ? '🟢 CONECTADO' : '🔴 ERROR DE CONEXIÓN'} (${res.data.host}:${res.data.port})`,
        res.data.connected 
          ? 'background:#059669;color:#fff;font-weight:bold;padding:3px 8px;border-radius:4px;' 
          : 'background:#dc2626;color:#fff;font-weight:bold;padding:3px 8px;border-radius:4px;'
      );
      console.log('📡 Servidor Host:', res.data.host);
      console.log('🔌 Puerto AMI:', res.data.port);
      console.log('🎙️ Driver SIP:', res.data.sip_driver);
      console.log('💬 Mensaje:', res.data.message);
      if (res.data.banner) {
        console.log('🏷️ Banner Asterisk:', res.data.banner);
      }
      if (!res.data.connected) {
        console.warn('⚠️ Tipo de Error:', res.data.error_type || 'DESCONOCIDO');
        console.error('🚨 DIAGNÓSTICO Y SOLUCIÓN:', res.data.diagnostic || res.data.message);
        console.info('💡 RECOMENDACIÓN: Revisa las variables en el archivo .env de tu hosting y asegúrate de que el puerto 5038 TCP saliente no esté bloqueado por el firewall.');
      }
      console.groupEnd();

      if (manual) {
        showConnectionDetails();
      }
    }
  } catch (err) {
    serverStatus.value.connected = false;
    serverStatus.value.message = err.response?.data?.message || err.message;
    console.error('%c[CALL CENTER PBX] Fallo en la petición HTTP del endpoint /status:', 'background:#b91c1c;color:#fff;padding:2px 6px;border-radius:4px;', err);
    if (manual) {
      Swal.fire({
        icon: 'error',
        title: 'Error de Red / Endpoint',
        text: 'No se pudo consultar el estado del servidor Call Center: ' + (err.response?.data?.message || err.message),
        confirmButtonColor: '#7c3aed',
      });
    }
  } finally {
    loadingStatus.value = false;
  }
}

function showConnectionDetails() {
  if (serverStatus.value.connected) {
    Swal.fire({
      icon: 'success',
      title: '¡Conexión Exitosa!',
      html: `
        <div class="text-left space-y-2 text-sm text-slate-700 dark:text-slate-300">
          <p>Conectado satisfactoriamente con el servidor Issabel PBX.</p>
          <div class="bg-slate-100 dark:bg-slate-800 p-3 rounded-xl font-mono text-xs space-y-1">
            <p><b>Host:</b> ${serverStatus.value.host}:${serverStatus.value.port}</p>
            <p><b>Driver:</b> ${serverStatus.value.sip_driver}</p>
            <p><b>Banner:</b> <span class="text-emerald-600">${serverStatus.value.banner || 'Asterisk Call Manager'}</span></p>
          </div>
        </div>
      `,
      confirmButtonColor: '#7c3aed',
    });
  } else {
    Swal.fire({
      icon: 'error',
      title: 'Fallo de Conexión con Issabel PBX',
      html: `
        <div class="text-left space-y-3 text-sm text-slate-700 dark:text-slate-300">
          <div class="bg-rose-50 dark:bg-rose-950/40 p-3 rounded-xl border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300">
            <p class="font-bold text-xs uppercase text-rose-600 dark:text-rose-400">Error: ${serverStatus.value.error_type || 'Fallo de Conexión'}</p>
            <p class="mt-1 font-mono text-xs">${serverStatus.value.message || 'No se pudo conectar a Asterisk AMI.'}</p>
          </div>
          <div class="bg-amber-50 dark:bg-amber-950/40 p-3.5 rounded-xl border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-xs leading-relaxed">
            <p class="font-bold mb-1">💡 Diagnóstico y solución:</p>
            <p>${serverStatus.value.diagnostic || 'Revisa la consola del navegador (tecla F12) para ver los registros técnicos completos.'}</p>
          </div>
          <div class="bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl font-mono text-xs text-slate-500">
            Host configurado: <b>${serverStatus.value.host}:${serverStatus.value.port}</b>
          </div>
        </div>
      `,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#7c3aed',
    });
  }
}

async function fetchExtensions() {
  loadingExtensions.value = true;
  try {
    const res = await api.get('/admin/callcenter/extensions');
    if (res.data?.success) {
      extensions.value = res.data.extensions || [];
      console.log(`%c[CALL CENTER EXTENSIONES] ${extensions.value.length} extensiones cargadas (${onlineExtensionsCount.value} online)`, 'color:#059669;font-weight:bold;');
      // Si no hay extensiones seleccionadas y hay online, preseleccionar online
      if (selectedExtensions.value.length === 0) {
        selectOnlineExtensions();
      }
    }
  } catch (err) {
    console.error('%c[CALL CENTER EXTENSIONES] Error al consultar extensiones SIP desde Issabel:', 'background:#dc2626;color:#fff;padding:2px 6px;border-radius:4px;', err.response?.data?.message || err.message);
  } finally {
    loadingExtensions.value = false;
  }
}

async function fetchBroadcasts(page = 1) {
  loadingHistory.value = true;
  try {
    const res = await api.get('/admin/callcenter/broadcasts', { params: { page } });
    if (res.data?.success) {
      broadcasts.value = res.data.broadcasts.data || [];
      pagination.value = {
        current_page: res.data.broadcasts.current_page,
        last_page: res.data.broadcasts.last_page,
        total: res.data.broadcasts.total,
      };
    }
  } catch (err) {
    console.error('Error fetching broadcasts:', err);
  } finally {
    loadingHistory.value = false;
  }
}

// Manejo de Imágenes
async function handleFileUpload(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  // Previsualización local inmediata que nunca falla en el navegador
  form.value.local_preview = URL.createObjectURL(file);

  uploadingImage.value = true;
  const formData = new FormData();
  formData.append('image', file);

  try {
    const res = await api.post('/admin/callcenter/upload-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (res.data?.success) {
      form.value.image_url = res.data.image_url;
      form.value.image_path = res.data.image_path;
      form.value.image_size = res.data.image_size || 0;
      form.value.image_mime = res.data.image_mime || '';
      form.value.image_name = res.data.image_name || '';
      form.value.rcs_xml = res.data.rcs_xml || '';
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Error al subir imagen',
      text: err.response?.data?.message || 'No se pudo subir la imagen.',
    });
  } finally {
    uploadingImage.value = false;
  }
}

function removeImage() {
  if (form.value.local_preview) {
    URL.revokeObjectURL(form.value.local_preview);
  }
  form.value.local_preview = '';
  form.value.image_url = '';
  form.value.image_path = '';
  form.value.image_size = 0;
  form.value.image_mime = '';
  form.value.image_name = '';
  form.value.rcs_xml = '';
}

// Envío de Comunicado
async function submitBroadcast() {
  if (selectedExtensions.value.length === 0) {
    Swal.fire('Atención', 'Debes seleccionar al menos una extensión destino.', 'warning');
    return;
  }
  if (!form.value.title.trim() || !form.value.message.trim()) {
    Swal.fire('Atención', 'Completa el título y el mensaje del comunicado.', 'warning');
    return;
  }

  const result = await Swal.fire({
    title: '¿Emitir Comunicado?',
    text: `Se enviará el mensaje SIP a ${selectedExtensions.value.length} extensión(es) activa(s) desde la Extensión ${form.value.source_extension}.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#7c3aed',
    cancelButtonColor: '#64748b',
    confirmButtonText: `Sí, emitir a ${selectedExtensions.value.length} extensiones`,
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  sending.value = true;
  try {
    const res = await api.post('/admin/callcenter/send', {
      title: form.value.title.trim(),
      message: form.value.message.trim(),
      extensions: selectedExtensions.value,
      source_extension: form.value.source_extension,
      image_url: form.value.image_url || null,
      image_path: form.value.image_path || null,
      image_size: form.value.image_size || null,
      image_mime: form.value.image_mime || null,
      image_name: form.value.image_name || null,
      rcs_xml: form.value.rcs_xml || null,
    });

    if (res.data?.success) {
      await Swal.fire({
        icon: 'success',
        title: '¡Comunicado Emitido!',
        text: res.data.message,
        confirmButtonColor: '#7c3aed',
      });

      // Limpiar formulario excepto remitente
      if (form.value.local_preview) {
        URL.revokeObjectURL(form.value.local_preview);
      }
      form.value.local_preview = '';
      form.value.title = '';
      form.value.message = '';
      form.value.image_url = '';
      form.value.image_path = '';
      form.value.image_size = 0;
      form.value.image_mime = '';
      form.value.image_name = '';
      form.value.rcs_xml = '';

      fetchBroadcasts(1);
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Error de emisión',
      text: err.response?.data?.message || 'Ocurrió un error al despachar por Asterisk AMI.',
    });
  } finally {
    sending.value = false;
  }
}

// Ver Detalle
async function viewDetails(broadcast) {
  try {
    const res = await api.get(`/admin/callcenter/broadcasts/${broadcast.id}`);
    if (res.data?.success) {
      selectedBroadcast.value = res.data.broadcast;
    }
  } catch (err) {
    console.error('Error viewing broadcast:', err);
  }
}

// Reintentar Fallidos
async function retryFailed(broadcast) {
  const result = await Swal.fire({
    title: '¿Reintentar extensiones fallidas?',
    text: `Se volverá a intentar el envío a las ${broadcast.failed_count} extensiones que no recibieron el mensaje.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#7c3aed',
    confirmButtonText: 'Sí, reintentar ahora',
    cancelButtonText: 'Cancelar',
  });

  if (!result.isConfirmed) return;

  try {
    const res = await api.post(`/admin/callcenter/broadcasts/${broadcast.id}/retry-failed`);
    if (res.data?.success) {
      Swal.fire('Reintento Exitoso', res.data.message, 'success');
      fetchBroadcasts(pagination.value.current_page);
    }
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message || 'Error al reintentar.', 'error');
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

onMounted(() => {
  fetchServerStatus();
  fetchExtensions();
  fetchBroadcasts(1);
});
</script>
