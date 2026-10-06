<template>
  <div class="h-screen w-screen flex flex-col bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 overflow-hidden font-sans transition-colors duration-200">
    <!-- Navbar Superior (En móviles < lg se oculta al tener un chat abierto para maximizar el área de conversación) -->
    <header 
      :class="[
        'h-14 bg-white/95 dark:bg-slate-950/90 border-b border-slate-200 dark:border-slate-800/80 px-3 sm:px-6 items-center justify-between shrink-0 z-20 backdrop-blur-md transition-colors duration-200 shadow-xs dark:shadow-none',
        chatStore.activeConversation ? 'hidden lg:flex' : 'flex'
      ]"
    >
      <!-- Marca & Operación -->
      <div class="flex items-center gap-2.5 sm:gap-3">
        <div class="w-8 h-8 rounded-lg bg-white/10 dark:bg-white/10 border border-slate-200 dark:border-white/20 flex items-center justify-center overflow-hidden shrink-0 shadow-xs ring-1 ring-slate-200/50 dark:ring-white/10">
          <img :src="logoImg" alt="Logo TAMep" class="w-full h-full object-contain p-0.5 rounded-lg" />
        </div>
        <h1 class="font-extrabold text-xs sm:text-sm tracking-wide text-slate-900 dark:text-white">WhatsApp</h1>
      </div>

      <!-- Métricas Rápidas en Vivo -->
      <div class="hidden md:flex items-center gap-5 text-xs">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
          <span class="text-slate-500 dark:text-slate-400 font-medium">En espera:</span>
          <span class="font-bold text-amber-600 dark:text-amber-300 font-mono">{{ chatStore.counts.waiting_agent }}</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-sky-500"></div>
          <span class="text-slate-500 dark:text-slate-400 font-medium">En atención:</span>
          <span class="font-bold text-sky-600 dark:text-sky-300 font-mono">{{ chatStore.counts.human_live }}</span>
        </div>
      </div>

      <!-- Perfil del Agente & Salida Rápida -->
      <div class="flex items-center gap-2">
        <!-- Perfil Interactivo del Agente: Al hacer clic abre perfil, tema, notificaciones y seguridad -->
        <button 
          @click="showProfileModal = true"
          class="flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 border border-slate-200/80 dark:border-slate-800 transition-all cursor-pointer group text-left shadow-2xs"
          title="Ver mi perfil, tema, alertas y seguridad"
        >
          <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-gradient-to-tr from-sky-500/20 to-indigo-500/20 text-sky-600 dark:text-sky-400 font-bold flex items-center justify-center text-xs border border-sky-500/30 shrink-0 group-hover:border-sky-500/60 transition-all shadow-xs">
            {{ agentInitials }}
          </div>
          <div class="hidden sm:block text-right pr-0.5">
            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors flex items-center justify-end gap-1">
              <span>{{ authStore.user?.name || 'Agente de Ventas' }}</span>
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

    <!-- Layout Dividido Principal (Dashboard SPA Adaptable) -->
    <main class="flex-1 flex overflow-hidden relative">
      <!-- Columna Izquierda: Lista de Chats -->
      <ChatSidebar 
        :class="[
          'h-full transition-all duration-200',
          chatStore.activeConversation ? 'hidden lg:flex' : 'flex w-full lg:w-84'
        ]"
      />

      <!-- Columna Central: Ventana de Chat en Vivo -->
      <ChatWindow 
        :class="[
          'h-full transition-all duration-200',
          chatStore.activeConversation ? 'flex w-full flex-1' : 'hidden lg:flex flex-1'
        ]"
        @open-booking="showMobileBooking = true"
      />

      <!-- Columna Derecha Desktop: Datos Recopilados (Fija en >= lg) -->
      <div class="hidden lg:flex h-full shrink-0">
        <TicketBookingPanel />
      </div>

      <!-- Drawer Deslizante Móvil: Datos Recopilados (Solo < lg) -->
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-x-full"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-full"
      >
        <div 
          v-if="showMobileBooking && chatStore.activeConversation"
          class="fixed inset-0 z-50 lg:hidden flex justify-end bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-sm"
          @click.self="showMobileBooking = false"
        >
          <div class="w-full sm:w-96 max-w-[92vw] h-full shadow-2xl flex flex-col bg-slate-200 dark:bg-slate-200">
            <TicketBookingPanel is-mobile @close="showMobileBooking = false" />
          </div>
        </div>
      </Transition>
    </main>
    <!-- Modal de Perfil de Usuario y Cambio de Contraseña -->
    <UserProfileModal 
      :is-open="showProfileModal" 
      @close="showProfileModal = false" 
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChatStore } from '@/stores/useChatStore';
import { LogOut, ChevronDown } from 'lucide-vue-next';
import logoImg from '../../images/logo.jpg';
import ChatSidebar from '@/components/chat/ChatSidebar.vue';
import ChatWindow from '@/components/chat/ChatWindow.vue';
import TicketBookingPanel from '@/components/booking/TicketBookingPanel.vue';
import UserProfileModal from '@/components/profile/UserProfileModal.vue';

const router = useRouter();
const authStore = useAuthStore();
const chatStore = useChatStore();

const showMobileBooking = ref(false);
const showProfileModal = ref(false);

const agentInitials = computed(() => {
  const name = authStore.user?.name || '';
  if (!name) return 'AV';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
});

watch(
  () => chatStore.activeConversation,
  (val) => {
    if (!val) showMobileBooking.value = false;
  }
);

let chatSyncInterval = null;
let listSyncInterval = null;

onMounted(async () => {
  await chatStore.fetchConversations();
  
  // 1. Conexión de WebSockets en tiempo real instantáneo (< 100ms)
  chatStore.initWebSockets();

  // 2. Sincronización continua de respaldo (Smart Sync):
  //    - Si hay un chat abierto, consulta nuevos mensajes cada 2.5 segundos de forma silenciosa
  chatSyncInterval = setInterval(() => {
    chatStore.syncActiveChat();
  }, 2500);

  //    - Actualiza contadores y bandeja cada 8 segundos de forma silenciosa
  listSyncInterval = setInterval(() => {
    chatStore.fetchConversations(true);
  }, 8000);
});

onUnmounted(() => {
  if (chatSyncInterval) clearInterval(chatSyncInterval);
  if (listSyncInterval) clearInterval(listSyncInterval);
});

async function handleLogout() {
  await authStore.logout();
  router.push('/login');
}
</script>
