<template>
  <aside class="w-full lg:w-84 h-full flex flex-col border-r border-slate-200 dark:border-slate-800/80 bg-white/95 dark:bg-slate-950/60 backdrop-blur-xl select-none shrink-0 transition-colors duration-200">
    <!-- Header & Search -->
    <div class="p-3 sm:p-4 border-b border-slate-200 dark:border-slate-800/60 space-y-2.5 sm:space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
          <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Bandeja de Chats</h2>
        </div>
        <button 
          @click="chatStore.fetchConversations()" 
          class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors cursor-pointer"
          title="Recargar conversaciones"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': chatStore.loadingList }" />
        </button>
      </div>

      <!-- Buscador -->
      <div class="relative">
        <input 
          v-model="chatStore.searchQuery"
          @input="debounceSearch"
          type="text" 
          placeholder="Buscar por teléfono, nombre o fecha..."
          class="w-full bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-900 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500/30 transition-all font-medium"
        />
      </div>

      <!-- Filtros de Estado -->
      <div class="grid grid-cols-3 gap-1 bg-slate-100 dark:bg-slate-900/80 p-1 rounded-xl border border-slate-200 dark:border-slate-800/80 text-[11px] font-medium">
        <button 
          @click="setFilter('waiting_agent')"
          :class="[
            'py-1.5 rounded-lg transition-all flex items-center justify-center gap-1.5 relative cursor-pointer',
            chatStore.filterStatus === 'waiting_agent' 
              ? 'bg-amber-500/20 text-amber-800 dark:text-amber-300 font-semibold border border-amber-500/40 shadow-xs' 
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          ]"
        >
          <span>En Espera</span>
          <span 
            v-if="chatStore.counts.waiting_agent > 0" 
            class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-bold rounded-full text-[10px] animate-pulse"
          >
            {{ chatStore.counts.waiting_agent }}
          </span>
        </button>

        <button 
          @click="setFilter('human_live')"
          :class="[
            'py-1.5 rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer',
            chatStore.filterStatus === 'human_live' 
              ? 'bg-sky-500/20 text-sky-700 dark:text-sky-300 font-semibold border border-sky-500/40 shadow-xs' 
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          ]"
        >
          <span>Mis Chats</span>
          <span v-if="chatStore.counts.human_live > 0" class="text-slate-500 text-[10px]">
            ({{ chatStore.counts.human_live }})
          </span>
        </button>

        <button 
          @click="setFilter('all')"
          :class="[
            'py-1.5 rounded-lg transition-all text-center cursor-pointer',
            chatStore.filterStatus === 'all' 
              ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-semibold border border-slate-300 dark:border-slate-700 shadow-xs' 
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
          ]"
        >
          Todos
        </button>
      </div>
    </div>

    <!-- Lista de Chats -->
    <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/40 p-2 space-y-1">
      <div 
        v-if="chatStore.loadingList && chatStore.conversations.length === 0" 
        class="text-center py-12 text-slate-400 dark:text-slate-500 text-xs"
      >
        Cargando conversaciones...
      </div>

      <div 
        v-else-if="chatStore.conversations.length === 0" 
        class="text-center py-12 px-4 text-slate-400 dark:text-slate-500 text-xs space-y-2"
      >
        <Inbox class="w-8 h-8 text-slate-400 dark:text-slate-600 mx-auto mb-1 stroke-1" />
        <div>No hay conversaciones con este filtro.</div>
      </div>

      <div 
        v-for="conv in chatStore.conversations" 
        :key="conv.id"
        @click="chatStore.selectConversation(conv)"
        :class="[
          'p-3 rounded-xl cursor-pointer transition-all border text-left',
          chatStore.activeConversation?.id === conv.id
            ? 'bg-sky-50 dark:bg-sky-950/40 border-sky-500/40 shadow-xs dark:shadow-[0_0_15px_rgba(2,132,199,0.15)]'
            : 'bg-transparent border-transparent hover:bg-slate-100/70 dark:hover:bg-slate-900/80 hover:border-slate-200 dark:hover:border-slate-800'
        ]"
      >
        <div class="flex items-start justify-between gap-2 mb-1">
          <div class="flex items-center gap-2 overflow-hidden">
            <!-- Avatar / Status dot -->
            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-gradient-to-br dark:from-slate-800 dark:to-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center font-bold text-xs text-sky-600 dark:text-sky-400 shrink-0 relative">
              {{ (conv.contact_name || conv.phone).substring(0, 2).toUpperCase() }}
              <span 
                v-if="conv.status === 'waiting_agent'"
                class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-amber-500 rounded-full border-2 border-white dark:border-slate-950 shadow-[0_0_8px_rgba(245,158,11,0.8)]"
              ></span>
              <span 
                v-else-if="conv.status === 'human_live'"
                class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-sky-400 rounded-full border-2 border-white dark:border-slate-950"
              ></span>
            </div>

            <div class="truncate">
              <div class="font-semibold text-xs text-slate-800 dark:text-slate-100 truncate flex items-center gap-1.5">
                <span>{{ conv.contact_name || (conv.channel === 'web' ? 'Visitante Web' : formatPhone(conv.phone)) }}</span>
                <span v-if="conv.channel === 'web'" class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                  Web
                </span>
              </div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                {{ conv.channel === 'web' ? (conv.session_data?.contact_phone ? ('Tel: ' + conv.session_data.contact_phone) : 'Chat Web') : ('+' + conv.phone) }}
              </div>
            </div>
          </div>

          <span 
            :title="formatFullDateTime(conv.last_interaction_at || conv.updated_at)"
            class="text-[10px] text-slate-400 dark:text-slate-500 shrink-0 font-medium font-sans"
          >
            {{ formatConversationDate(conv.last_interaction_at || conv.updated_at) }}
          </span>
        </div>

        <!-- Ruta o badge del lead si existe -->
        <div v-if="conv.latest_lead" class="flex items-center gap-1.5 my-1.5">
          <span class="px-2 py-0.5 rounded-md bg-blue-500/10 dark:bg-blue-500/15 border border-blue-500/25 dark:border-blue-500/30 text-[10px] font-semibold text-blue-700 dark:text-blue-300 flex items-center gap-1">
            <Plane class="w-3 h-3 text-sky-600 dark:text-sky-400 shrink-0" />
            <span class="flex items-center gap-1">{{ conv.latest_lead.origin || 'VVI' }} <ArrowRight class="w-2.5 h-2.5 text-blue-500 dark:text-blue-400" /> {{ conv.latest_lead.destination || 'LPB' }}</span>
          </span>
          <span 
            v-if="conv.latest_lead.flight_number" 
            class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-400"
          >
            {{ conv.latest_lead.flight_number }}
          </span>
        </div>

        <!-- Último mensaje -->
        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 truncate mt-1 flex items-center gap-1">
          <span v-if="conv.latest_message?.sender === 'agent'" class="text-sky-600 dark:text-sky-400 font-medium shrink-0">Tú: </span>
          <span v-else-if="conv.latest_message?.sender === 'bot'" class="text-amber-600 dark:text-amber-400 font-medium shrink-0">Bot: </span>
          <Mic v-if="conv.latest_message?.media_type === 'audio' || (conv.latest_message?.media_url && isAudioUrl(conv.latest_message.media_url))" class="w-3 h-3 text-emerald-500 dark:text-emerald-400 shrink-0" />
          <Camera v-else-if="conv.latest_message?.media_type === 'image'" class="w-3 h-3 text-sky-500 dark:text-sky-400 shrink-0" />
          <FileText v-else-if="conv.latest_message?.media_type === 'document'" class="w-3 h-3 text-rose-500 dark:text-rose-400 shrink-0" />
          <span class="truncate">{{ formatMessagePreview(conv.latest_message?.message, conv.session_data) || (conv.latest_message?.media_type === 'audio' ? 'Nota de voz' : 'Sin mensajes') }}</span>
        </p>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { RefreshCw, Inbox, Plane, ArrowRight, Mic, Camera, FileText } from 'lucide-vue-next';
import { useChatStore } from '@/stores/useChatStore';
import { formatMessagePreview } from '@/services/messageFormatter';

const chatStore = useChatStore();

let searchTimeout = null;
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    chatStore.fetchConversations();
  }, 300);
}

function setFilter(status) {
  chatStore.filterStatus = status;
  chatStore.activeConversation = null;
  chatStore.messages = [];
  chatStore.activeLead = null;
  chatStore.fetchConversations();
}

function formatPhone(phone) {
  if (!phone) return '';
  return phone.replace(/(\d{3})(\d{4})(\d+)/, '$1 $2 $3');
}

function formatConversationDate(timestamp) {
  if (!timestamp) return '';
  const date = new Date(typeof timestamp === 'string' && timestamp.includes(' ') && !timestamp.includes('T') ? timestamp.replace(' ', 'T') : timestamp);
  const now = new Date();

  const dateMidnight = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  const todayMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
  const oneDayMs = 24 * 60 * 60 * 1000;
  const diffDays = Math.round((todayMidnight - dateMidnight) / oneDayMs);

  if (diffDays === 0) {
    // Hoy: formato HH:mm
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  } else if (diffDays === 1) {
    // Ayer
    return 'Ayer';
  } else if (diffDays > 1 && diffDays < 7) {
    // Días de la semana reciente
    const weekday = date.toLocaleDateString('es-ES', { weekday: 'short' });
    return weekday.charAt(0).toUpperCase() + weekday.slice(1);
  } else {
    // Fechas anteriores: DD/MM/AA
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: '2-digit' });
  }
}

function formatFullDateTime(timestamp) {
  if (!timestamp) return '';
  const date = new Date(typeof timestamp === 'string' && timestamp.includes(' ') && !timestamp.includes('T') ? timestamp.replace(' ', 'T') : timestamp);
  return date.toLocaleString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function isAudioUrl(url) {
  if (!url) return false;
  return /\.(ogg|mp3|wav|m4a|aac)($|\?)/i.test(url);
}
</script>
