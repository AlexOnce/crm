<template>
  <div class="min-h-screen w-full flex items-center justify-center bg-slate-100 dark:bg-slate-950 p-4 sm:p-6 relative overflow-y-auto font-sans select-none transition-colors duration-300">
    <!-- Botón Flotante para Alternar Tema Claro / Oscuro -->
    <div class="fixed top-4 right-4 z-30">
      <ThemeToggle :show-label="true" />
    </div>

    <!-- Banner de Fondo Institucional -->
    <div 
      class="fixed inset-0 bg-cover bg-center bg-no-repeat scale-105 pointer-events-none transition-all duration-700"
      :style="{ backgroundImage: `url(${fondoImg})` }"
    ></div>
    <!-- Capa elegante con efecto degradado y desenfoque sutil según el modo -->
    <div class="fixed inset-0 bg-gradient-to-t from-slate-100/95 via-slate-100/85 to-slate-200/70 dark:from-slate-950 dark:via-slate-950/85 dark:to-slate-900/60 backdrop-blur-[2px] pointer-events-none transition-colors duration-300"></div>

    <div class="w-full max-w-md bg-white/95 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700/60 backdrop-blur-2xl rounded-2xl sm:rounded-3xl p-6 sm:p-8 shadow-2xl shadow-slate-300/40 dark:shadow-black/90 relative z-10 space-y-6 my-auto animate-slide-up transition-colors duration-200">
      <!-- Encabezado y Logo Institucional -->
      <div class="text-center space-y-3">
        <div class="inline-flex p-2.5 rounded-2xl bg-slate-100/80 dark:bg-white/10 border border-slate-200 dark:border-white/20 backdrop-blur-md shadow-md mx-auto ring-4 ring-sky-500/10">
          <img 
            :src="logoImg" 
            alt="Logo TAMep" 
            class="h-14 w-auto object-contain rounded-xl shadow-xs"
          />
        </div>
        <div>
          <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white drop-shadow-xs">TAMep</h2>
          <h3 class="text-base sm:text-lg font-bold text-sky-600 dark:text-sky-400 tracking-tight mt-1">
            Panel de Atención al Cliente
          </h3>
        </div>
      </div>

      <!-- Alerta de Error con opción de cierre y detalle -->
      <div 
        v-if="authStore.error" 
        class="p-3.5 rounded-xl bg-red-500/10 dark:bg-red-500/15 border border-red-500/30 text-xs text-red-700 dark:text-red-200 flex items-start gap-3 shadow-sm transition-all"
        role="alert"
      >
        <AlertCircle class="w-4 h-4 text-red-500 dark:text-red-400 shrink-0 mt-0.5" />
        <div class="flex-1 space-y-1">
          <p class="font-medium leading-relaxed">{{ authStore.error }}</p>
          <p v-if="cooldownSeconds > 0" class="text-[11px] text-amber-600 dark:text-amber-300 font-mono font-semibold">
            Reintento habilitado en: {{ cooldownSeconds }}s
          </p>
        </div>
        <button 
          type="button" 
          @click="authStore.clearError()" 
          class="text-red-400 hover:text-red-600 dark:hover:text-red-200 transition-colors cursor-pointer"
          title="Cerrar alerta"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Formulario de Acceso -->
      <form @submit.prevent="handleLogin" class="space-y-4 text-xs select-text">
        <!-- Campo de Correo Electrónico -->
        <div>
          <label for="login-email" class="block text-slate-700 dark:text-slate-300 font-semibold mb-1.5">
            Correo Institucional
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
              <Mail class="w-4 h-4" />
            </div>
            <input 
              id="login-email"
              ref="emailInput"
              v-model="email"
              type="email" 
              required
              autocomplete="email"
              placeholder="agente@tamep.bo"
              :disabled="authStore.loading || cooldownSeconds > 0"
              @input="onInputChange"
              class="w-full bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-3 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 transition-all font-medium disabled:opacity-50"
            />
          </div>
        </div>

        <!-- Campo de Contraseña con Revelado Opcional e Indicador Caps Lock -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label for="login-password" class="block text-slate-700 dark:text-slate-300 font-semibold">
              Contraseña
            </label>
            <span v-if="capsLockActive" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
              <ArrowUpSquare class="w-3 h-3" />
              Bloq Mayús activado
            </span>
          </div>

          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
              <Lock class="w-4 h-4" />
            </div>
            <input 
              id="login-password"
              ref="passwordInput"
              v-model="password"
              :type="showPassword ? 'text' : 'password'" 
              required
              autocomplete="current-password"
              placeholder="••••••••••••"
              :disabled="authStore.loading || cooldownSeconds > 0"
              @keydown="checkCapsLock"
              @keyup="checkCapsLock"
              @input="onInputChange"
              class="w-full bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-11 py-3 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 transition-all font-mono disabled:opacity-50"
            />
            <!-- Botón para alternar visibilidad de contraseña (Eye / EyeOff) -->
            <button
              type="button"
              tabindex="-1"
              @click="togglePasswordVisibility"
              :disabled="authStore.loading || cooldownSeconds > 0"
              :title="showPassword ? 'Ocultar contraseña' : 'Ver contraseña'"
              aria-label="Alternar visibilidad de la contraseña"
              class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-sky-500 focus:text-sky-500 focus:outline-none cursor-pointer transition-colors"
            >
              <EyeOff v-if="showPassword" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Opciones de Sesión: Recordar Correo -->
        <div class="flex items-center justify-between pt-1">
          <label class="flex items-center gap-2 cursor-pointer text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-300 text-xs">
            <input 
              v-model="rememberEmail"
              type="checkbox"
              class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sky-600 focus:ring-sky-500/30 focus:ring-offset-0 transition cursor-pointer"
            />
            <span>Recordar mi correo en este equipo</span>
          </label>
        </div>

        <!-- Botón de Envío con Estados Dinámicos -->
        <button
          type="submit"
          :disabled="authStore.loading || cooldownSeconds > 0"
          class="w-full py-3.5 rounded-xl bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700 hover:from-sky-400 hover:via-sky-500 hover:to-blue-600 disabled:opacity-50 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-sky-600/30 transition-all active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer"
        >
          <template v-if="authStore.loading">
            <Loader2 class="w-4 h-4 animate-spin text-white" />
            <span>Verificando Credenciales...</span>
          </template>
          <template v-else-if="cooldownSeconds > 0">
            <Lock class="w-4 h-4 text-amber-300" />
            <span>Pausado por Seguridad ({{ cooldownSeconds }}s)</span>
          </template>
          <template v-else>
            <LogIn class="w-4 h-4" />
            <span>Iniciar Sesión</span>
          </template>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/useAuthStore';
import ThemeToggle from '@/components/common/ThemeToggle.vue';
import { 
  LogIn, 
  Eye, 
  EyeOff, 
  Lock, 
  Mail, 
  Loader2, 
  AlertCircle, 
  ArrowUpSquare, 
  X 
} from 'lucide-vue-next';
import logoImg from '../../images/logo.jpg';
import fondoImg from '../../images/fondo.jpg';

const router = useRouter();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const capsLockActive = ref(false);
const rememberEmail = ref(false);
const cooldownSeconds = ref(0);

const emailInput = ref(null);
const passwordInput = ref(null);

let cooldownTimer = null;

function togglePasswordVisibility() {
  showPassword.value = !showPassword.value;
}

function checkCapsLock(event) {
  if (event && typeof event.getModifierState === 'function') {
    capsLockActive.value = event.getModifierState('CapsLock');
  }
}

function onInputChange() {
  if (authStore.error && cooldownSeconds.value === 0) {
    authStore.clearError();
  }
}

function startCooldown(seconds) {
  cooldownSeconds.value = seconds;
  if (cooldownTimer) clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    cooldownSeconds.value--;
    if (cooldownSeconds.value <= 0) {
      clearInterval(cooldownTimer);
      cooldownTimer = null;
      authStore.clearError();
    }
  }, 1000);
}

async function handleLogin() {
  if (cooldownSeconds.value > 0) return;

  // Persistir o limpiar correo recordado
  if (rememberEmail.value && email.value) {
    localStorage.setItem('crm_remember_email', 'true');
    localStorage.setItem('crm_saved_email', email.value.trim());
  } else {
    localStorage.removeItem('crm_remember_email');
    localStorage.removeItem('crm_saved_email');
  }

  const success = await authStore.login(email.value, password.value);
  if (success) {
    if (authStore.user?.role === 'admin') {
      router.push('/admin');
    } else {
      router.push('/');
    }
  } else {
    // Si se activa bloqueo por rate limit (429)
    if (authStore.rateLimitedUntil) {
      const remainingSecs = Math.max(1, Math.ceil((authStore.rateLimitedUntil - Date.now()) / 1000));
      startCooldown(remainingSecs);
    }
  }
}

onMounted(() => {
  // Cargar correo recordado si existe
  const savedRemember = localStorage.getItem('crm_remember_email') === 'true';
  const savedEmail = localStorage.getItem('crm_saved_email');
  if (savedRemember && savedEmail) {
    rememberEmail.value = true;
    email.value = savedEmail;
    // Enfocar automáticamente en la contraseña
    setTimeout(() => {
      passwordInput.value?.focus();
    }, 150);
  } else {
    setTimeout(() => {
      emailInput.value?.focus();
    }, 150);
  }

  // Verificar si hay un cooldown activo de rate-limiting
  if (authStore.rateLimitedUntil && authStore.rateLimitedUntil > Date.now()) {
    const remaining = Math.ceil((authStore.rateLimitedUntil - Date.now()) / 1000);
    startCooldown(remaining);
  }
});

onBeforeUnmount(() => {
  if (cooldownTimer) {
    clearInterval(cooldownTimer);
  }
});
</script>
