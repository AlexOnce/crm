<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm animate-fade-in">
    <div class="w-full max-w-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] transition-colors duration-200">
      
      <!-- Cabecera del Modal -->
      <div class="px-6 py-4 bg-slate-50 dark:bg-slate-950/80 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-sky-500/20">
            <Users class="w-5 h-5" />
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
              Gestión del Equipo de Agentes
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-400 border border-sky-500/30">
                {{ agents.length }} registrados
              </span>
            </h2>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Control de usuarios, roles y atención del CRM</p>
          </div>
        </div>

        <button 
          @click="close"
          class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
          title="Cerrar"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Barra de Pestañas -->
      <div class="px-6 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40 flex items-center justify-between">
        <div class="flex gap-4">
          <button 
            @click="activeTab = 'list'"
            :class="[
              'py-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-2 cursor-pointer',
              activeTab === 'list' 
                ? 'border-sky-500 text-sky-600 dark:text-sky-400' 
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
            ]"
          >
            <Users class="w-3.5 h-3.5" />
            <span>Directorio de Agentes</span>
            <span class="px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-600 dark:text-slate-300">{{ agents.length }}</span>
          </button>
          
          <button 
            @click="activeTab = 'create'"
            :class="[
              'py-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-2 cursor-pointer',
              activeTab === 'create' 
                ? 'border-sky-500 text-sky-600 dark:text-sky-400' 
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
            ]"
          >
            <UserPlus class="w-3.5 h-3.5" />
            <span>Dar de Alta Nuevo Agente</span>
          </button>
        </div>

        <button 
          @click="fetchAgents" 
          :disabled="loading"
          class="p-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/80 rounded-lg transition-colors flex items-center gap-1 cursor-pointer"
          title="Actualizar lista"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{'animate-spin': loading}" />
          <span class="text-[10px]">Refrescar</span>
        </button>
      </div>

      <!-- Contenido de las Pestañas -->
      <div class="flex-1 overflow-y-auto p-6 space-y-4">
        
        <!-- Notificaciones de Feedback -->
        <div v-if="feedback.message" :class="[
          'p-3 rounded-xl text-xs flex items-center justify-between border',
          feedback.type === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-700 dark:text-red-300'
        ]">
          <span>{{ feedback.message }}</span>
          <button @click="feedback.message = ''" class="text-xs opacity-60 hover:opacity-100 p-0.5 cursor-pointer">
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- PESTAÑA 1: LISTADO DE AGENTES -->
        <div v-if="activeTab === 'list'" class="space-y-3">
          <div v-if="loading && agents.length === 0" class="text-center py-10 text-slate-400 dark:text-slate-500 text-xs">
            Cargando agentes del sistema...
          </div>

          <div v-else-if="agents.length === 0" class="text-center py-10 text-slate-400 dark:text-slate-500 text-xs">
            No hay agentes registrados aún.
          </div>

          <div 
            v-for="agent in agents" 
            :key="agent.id"
            class="p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700/80 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center font-bold text-slate-700 dark:text-slate-200 text-xs shadow-inner">
                {{ agent.name.substring(0, 2).toUpperCase() }}
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h4 class="font-bold text-sm text-slate-900 dark:text-white">{{ agent.name }}</h4>
                  <span :class="[
                    'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                    agent.role === 'admin' 
                      ? 'bg-purple-500/10 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 border-purple-500/20 dark:border-purple-500/30' 
                      : 'bg-blue-500/10 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 border-blue-500/20 dark:border-blue-500/30'
                  ]">
                    {{ agent.role === 'admin' ? 'Administrador' : 'Agente Ventas' }}
                  </span>
                  <span :class="[
                    'px-1.5 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1',
                    agent.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'
                  ]">
                    <span class="w-1.5 h-1.5 rounded-full" :class="agent.is_active ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-red-500 dark:bg-red-400'"></span>
                    {{ agent.is_active ? 'Activo' : 'Suspendido' }}
                  </span>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-3 mt-1">
                  <span class="font-mono">{{ agent.email }}</span>
                  <span>•</span>
                  <span>Chats atendidos: <strong class="text-slate-800 dark:text-slate-300 font-mono">{{ agent.assigned_conversations_count || 0 }}</strong></span>
                </div>
              </div>
            </div>

            <!-- Acciones por Agente -->
            <div class="flex items-center gap-2 self-end sm:self-center">
              <button 
                @click="openResetPassword(agent)"
                class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs font-medium transition-colors flex items-center gap-1 cursor-pointer"
                title="Restablecer contraseña"
              >
                <KeyRound class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" />
                <span>Contraseña</span>
              </button>

              <button 
                @click="toggleAgentStatus(agent)"
                :disabled="agent.id === currentUserId"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer',
                  agent.is_active 
                    ? 'bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/30' 
                    : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'
                ]"
                :title="agent.id === currentUserId ? 'No puedes suspender tu propia cuenta' : (agent.is_active ? 'Suspender acceso' : 'Activar acceso')"
              >
                {{ agent.is_active ? 'Suspender' : 'Activar' }}
              </button>

              <button 
                @click="openDeleteConfirm(agent)"
                :disabled="agent.id === currentUserId"
                class="px-2.5 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-600 hover:text-white text-red-600 dark:text-red-400 border border-red-500/30 text-xs font-medium transition-all flex items-center gap-1 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                :title="agent.id === currentUserId ? 'No puedes eliminar tu propia cuenta' : 'Eliminar permanentemente'"
              >
                <Trash2 class="w-3.5 h-3.5" />
                <span class="hidden sm:inline">Eliminar</span>
              </button>
            </div>
          </div>
        </div>

        <!-- PESTAÑA 2: FORMULARIO ALTA NUEVO AGENTE -->
        <div v-if="activeTab === 'create'" class="max-w-xl mx-auto bg-slate-50/70 dark:bg-slate-950/60 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-4">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Registrar Nuevo Miembro del Equipo</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            El nuevo agente podrá iniciar sesión inmediatamente en la plataforma con sus credenciales.
          </p>

          <form @submit.prevent="createAgent" class="space-y-4 pt-2">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre Completo</label>
              <input 
                v-model="newAgent.name"
                type="text" 
                required
                placeholder="Ej: Laura Paz (Ventas)" 
                class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
              />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Correo Electrónico</label>
              <input 
                v-model="newAgent.email"
                type="email" 
                required
                placeholder="laura@tamep.bo" 
                class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Contraseña Inicial</label>
                <input 
                  v-model="newAgent.password"
                  type="password" 
                  required
                  minlength="6"
                  placeholder="Mínimo 6 caracteres" 
                  class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
                />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Rol Operativo</label>
                <select 
                  v-model="newAgent.role"
                  class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs focus:outline-none focus:border-sky-500"
                >
                  <option value="agent">Agente de Ventas</option>
                  <option value="admin">Administrador / Supervisor</option>
                </select>
              </div>
            </div>

            <div class="pt-2 flex justify-end gap-2">
              <button 
                type="button"
                @click="activeTab = 'list'"
                class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium cursor-pointer transition-colors"
              >
                Cancelar
              </button>

              <button 
                type="submit"
                :disabled="submitting"
                class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold shadow-md shadow-sky-600/30 flex items-center gap-1.5 disabled:opacity-50 cursor-pointer transition-colors"
              >
                <span>{{ submitting ? 'Guardando...' : 'Crear Agente' }}</span>
              </button>
            </div>
          </form>
        </div>

        <!-- MODAL INTERNO: CAMBIAR CONTRASEÑA -->
        <div v-if="editingAgent" class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm">
          <div class="w-full max-w-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 space-y-4 shadow-2xl">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Cambiar Contraseña</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Nueva contraseña para <strong class="text-slate-800 dark:text-slate-200">{{ editingAgent.name }}</strong>:
            </p>
            
            <input 
              v-model="newPasswordInput" 
              type="password"
              placeholder="Nueva contraseña (mín 6 caracteres)"
              class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500"
            />

            <div class="flex justify-end gap-2 pt-2">
              <button 
                @click="editingAgent = null" 
                class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs cursor-pointer transition-colors"
              >
                Cancelar
              </button>
              <button 
                @click="submitPasswordChange" 
                :disabled="!newPasswordInput || newPasswordInput.length < 6"
                class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold disabled:opacity-50 cursor-pointer transition-colors"
              >
                Guardar
              </button>
            </div>
          </div>
        </div>

        <!-- MODAL INTERNO: CONFIRMAR ELIMINACIÓN -->
        <div v-if="agentToDelete" class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm">
          <div class="w-full max-w-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 space-y-4 shadow-2xl animate-slide-up">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                <AlertTriangle class="w-5 h-5" />
              </div>
              <div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">¿Eliminar Agente?</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Esta acción no se puede deshacer.</p>
              </div>
            </div>

            <p class="text-xs text-slate-600 dark:text-slate-300">
              ¿Confirmas eliminar a <strong class="text-slate-900 dark:text-white">{{ agentToDelete.name }}</strong> ({{ agentToDelete.email }})?
              <span v-if="agentToDelete.assigned_conversations_count > 0" class="block mt-2 text-[11px] text-amber-600 dark:text-amber-400 bg-amber-500/10 p-2 rounded-lg border border-amber-500/20">
                ⚠️ Tiene {{ agentToDelete.assigned_conversations_count }} chat(s) asignado(s). Al eliminarlo, quedarán desvinculados para que otro asesor los atienda.
              </span>
            </p>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
              <button 
                type="button"
                @click="agentToDelete = null" 
                :disabled="deleting"
                class="px-3.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs cursor-pointer transition-colors"
              >
                Cancelar
              </button>
              <button 
                type="button"
                @click="executeDeleteAgent" 
                :disabled="deleting"
                class="px-3.5 py-1.5 rounded-lg bg-red-600 hover:bg-red-500 text-white text-xs font-bold disabled:opacity-50 cursor-pointer transition-colors flex items-center gap-1.5 shadow-md shadow-red-600/30"
              >
                <RefreshCw v-if="deleting" class="w-3.5 h-3.5 animate-spin" />
                <Trash2 v-else class="w-3.5 h-3.5" />
                <span>{{ deleting ? 'Eliminando...' : 'Sí, Eliminar' }}</span>
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { Users, UserPlus, X, RefreshCw, KeyRound, Trash2, AlertTriangle } from 'lucide-vue-next';
import api from '@/api/client';
import { useAuthStore } from '@/stores/useAuthStore';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['close', 'updated']);

const authStore = useAuthStore();
const currentUserId = computed(() => authStore.user?.id);

const activeTab = ref('list');
const agents = ref([]);
const loading = ref(false);
const submitting = ref(false);

const feedback = ref({
  message: '',
  type: 'success'
});

const newAgent = ref({
  name: '',
  email: '',
  password: '',
  role: 'agent'
});

const editingAgent = ref(null);
const newPasswordInput = ref('');

const fetchAgents = async () => {
  loading.value = true;
  try {
    const res = await api.get('/agents');
    if (res.data && res.data.agents) {
      agents.value = res.data.agents;
    }
  } catch (err) {
    feedback.value = {
      message: 'Error al cargar la lista de agentes.',
      type: 'error'
    };
  } finally {
    loading.value = false;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchAgents();
    feedback.value.message = '';
  }
});

const toggleAgentStatus = async (agent) => {
  try {
    const res = await api.post(`/agents/${agent.id}/toggle`);
    agent.is_active = res.data.is_active;
    feedback.value = {
      message: res.data.message || 'Estado actualizado.',
      type: 'success'
    };
    emit('updated');
  } catch (err) {
    feedback.value = {
      message: err.response?.data?.message || 'Error al cambiar estado.',
      type: 'error'
    };
  }
};

const createAgent = async () => {
  submitting.value = true;
  feedback.value.message = '';
  try {
    const res = await api.post('/agents', newAgent.value);
    feedback.value = {
      message: `Agente ${res.data.agent.name} registrado con éxito.`,
      type: 'success'
    };
    newAgent.value = {
      name: '',
      email: '',
      password: '',
      role: 'agent'
    };
    await fetchAgents();
    activeTab.value = 'list';
    emit('updated');
  } catch (err) {
    feedback.value = {
      message: err.response?.data?.message || 'Error al crear agente. Verifica los datos.',
      type: 'error'
    };
  } finally {
    submitting.value = false;
  }
};

const openResetPassword = (agent) => {
  editingAgent.value = agent;
  newPasswordInput.value = '';
};

const submitPasswordChange = async () => {
  if (!editingAgent.value || !newPasswordInput.value) return;
  try {
    await api.put(`/agents/${editingAgent.value.id}`, {
      password: newPasswordInput.value
    });
    feedback.value = {
      message: `Contraseña actualizada para ${editingAgent.value.name}.`,
      type: 'success'
    };
    editingAgent.value = null;
    newPasswordInput.value = '';
  } catch (err) {
    feedback.value = {
      message: err.response?.data?.message || 'Error al actualizar contraseña.',
      type: 'error'
    };
  }
};

const agentToDelete = ref(null);
const deleting = ref(false);

const openDeleteConfirm = (agent) => {
  agentToDelete.value = agent;
};

const executeDeleteAgent = async () => {
  if (!agentToDelete.value) return;
  deleting.value = true;
  try {
    const res = await api.delete(`/agents/${agentToDelete.value.id}`);
    feedback.value = {
      message: res.data?.message || `Agente ${agentToDelete.value.name} eliminado correctamente.`,
      type: 'success'
    };
    agentToDelete.value = null;
    await fetchAgents();
    emit('updated');
  } catch (err) {
    feedback.value = {
      message: err.response?.data?.message || 'Error al eliminar agente.',
      type: 'error'
    };
  } finally {
    deleting.value = false;
  }
};

const close = () => {
  emit('close');
};
</script>
