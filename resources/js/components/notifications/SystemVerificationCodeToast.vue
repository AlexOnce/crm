<template>
  <!-- Contenedor flotante de notificaciones de códigos OTP -->
  <div 
    class="fixed top-5 right-5 z-[9999] flex flex-col gap-3 max-w-sm w-full pointer-events-none"
    aria-live="polite"
  >
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-[-20px] scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-90 translate-x-20"
    >
      <div
        v-for="item in activeCodes"
        :key="item.id"
        class="pointer-events-auto p-4 rounded-2xl bg-slate-900/95 dark:bg-slate-950/95 border border-sky-500/40 shadow-2xl shadow-sky-950/50 backdrop-blur-xl text-white overflow-hidden relative group"
      >
        <!-- Barra de progreso de expiración -->
        <div 
          class="absolute top-0 left-0 h-1 bg-gradient-to-r from-sky-400 to-indigo-500 transition-all duration-1000 ease-linear"
          :style="{ width: `${item.progressPercent}%` }"
        />

        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-sky-500/20 border border-sky-500/30 flex items-center justify-center text-sky-400 shrink-0 shadow-inner">
              <ShieldCheck class="w-5 h-5 animate-pulse" />
            </div>
            <div>
              <div class="flex items-center gap-1.5">
                <span class="text-[10px] uppercase font-black tracking-widest px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300 border border-sky-500/30">
                  {{ formatServiceName(item.service) }}
                </span>
                <span class="text-[11px] text-slate-400 font-medium">Meta Cloud</span>
              </div>
              <h4 class="text-xs font-bold text-slate-200 mt-0.5">Código de Verificación</h4>
            </div>
          </div>

          <button
            @click="dismissCode(item.id)"
            class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800/80 transition-colors cursor-pointer"
            title="Cerrar notificación"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Caja destacada del Código OTP -->
        <div class="my-3 p-3 rounded-xl bg-slate-950/80 border border-slate-800 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 font-mono">
            <KeyRound class="w-4 h-4 text-sky-400 shrink-0" />
            <span class="text-2xl font-black tracking-[0.25em] text-sky-400 selection:bg-sky-500 selection:text-white">
              {{ item.code }}
            </span>
          </div>

          <button
            @click="copyCode(item)"
            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
            :class="item.copied ? 'bg-emerald-600 text-white' : 'bg-sky-600 hover:bg-sky-500 text-white'"
          >
            <Check v-if="item.copied" class="w-3.5 h-3.5 stroke-[3]" />
            <Copy v-else class="w-3.5 h-3.5" />
            <span>{{ item.copied ? '¡Copiado!' : 'Copiar' }}</span>
          </button>
        </div>

        <!-- Pie de notificación con temporizador y emisor -->
        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-800/80">
          <div class="flex items-center gap-1">
            <Clock class="w-3.5 h-3.5 text-amber-400" />
            <span>Expira en: <strong class="text-amber-300 font-mono">{{ item.timeRemaining }}</strong></span>
          </div>
          <span v-if="item.sender_phone" class="font-mono text-slate-500 text-[10px]">
            De: {{ item.sender_phone }}
          </span>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { getEcho } from '@/echo';
import { ShieldCheck, KeyRound, Copy, Check, X, Clock } from 'lucide-vue-next';

const activeCodes = ref([]);
let intervalId = null;

function formatServiceName(service) {
  switch (service) {
    case 'facebook_page_linking':
      return 'Facebook Page';
    case 'instagram_linking':
      return 'Instagram';
    case 'whatsapp_business_verify':
      return 'WhatsApp Cloud';
    default:
      return 'Meta Verify';
  }
}

function handleIncomingOtp(payload) {
  // Evitar duplicados
  if (activeCodes.value.some((c) => c.code === payload.code)) {
    return;
  }

  const durationMs = 15 * 60 * 1000; // 15 minutos
  const expiresAtTimestamp = payload.expires_at ? new Date(payload.expires_at).getTime() : Date.now() + durationMs;

  const newItem = {
    id: payload.id || Date.now(),
    code: payload.code,
    service: payload.service || 'facebook_page_linking',
    sender_phone: payload.sender_phone,
    full_message: payload.full_message,
    expiresAtTimestamp,
    timeRemaining: '15:00',
    progressPercent: 100,
    copied: false,
  };

  activeCodes.value.unshift(newItem);

  // Reproducir sonido sutil de alerta si el navegador lo permite
  try {
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.frequency.setValueAtTime(880, audioCtx.currentTime); // Nota La5
    gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
    osc.start();
    osc.stop(audioCtx.currentTime + 0.3);
  } catch {
    // Silencioso si audioContext no está permitido por política de interacción
  }
}

async function copyCode(item) {
  try {
    await navigator.clipboard.writeText(item.code);
    item.copied = true;
    setTimeout(() => {
      item.copied = false;
    }, 2500);
  } catch (err) {
    console.error('Error al copiar código al portapapeles:', err);
  }
}

function dismissCode(id) {
  activeCodes.value = activeCodes.value.filter((c) => c.id !== id);
}

function updateCountdowns() {
  const now = Date.now();
  activeCodes.value = activeCodes.value.filter((item) => {
    const diff = item.expiresAtTimestamp - now;
    if (diff <= 0) {
      return false; // Expirado, remover de la lista
    }

    const totalDuration = 15 * 60 * 1000;
    item.progressPercent = Math.max(0, Math.min(100, (diff / totalDuration) * 100));

    const totalSeconds = Math.floor(diff / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    item.timeRemaining = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

    return true;
  });
}

onMounted(() => {
  const echo = getEcho();
  if (echo) {
    // Escuchar en el canal privado de conversaciones y en el canal exclusivo de verificaciones
    echo.private('conversations')
      .listen('.SystemVerificationCodeReceived', (event) => {
        handleIncomingOtp(event);
      });

    echo.private('system.verifications')
      .listen('.SystemVerificationCodeReceived', (event) => {
        handleIncomingOtp(event);
      });
  }

  intervalId = setInterval(updateCountdowns, 1000);
});

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }

  const echo = getEcho();
  if (echo) {
    try {
      echo.private('conversations').stopListening('.SystemVerificationCodeReceived');
      echo.private('system.verifications').stopListening('.SystemVerificationCodeReceived');
    } catch {
      // Ignorar si ya se desconectó
    }
  }
});
</script>
