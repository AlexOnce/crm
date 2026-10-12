<template>
  <!-- Solo se muestra un indicador minimalista y limpio si la ventana gratuita de 72h está activa -->
  <span 
    v-if="isFreeWindowActive"
    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 whitespace-nowrap shrink-0 select-none shadow-2xs leading-none align-middle"
    title="Ventana de atención gratuita de 72 horas otorgada por Meta para este chat"
  >
    <!-- Punto con animación de pulso perfectamente centrado horizontal y verticalmente -->
    <span class="relative flex h-2 w-2 shrink-0 items-center justify-center">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
      <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
    </span>
    <span class="tracking-tight">72h Gratis</span>
    <span v-if="remainingHours !== null" class="font-mono text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
      ({{ remainingHours }}h)
    </span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  conversation: {
    type: Object,
    default: () => null
  }
})

// Verificar si la ventana de 72 horas sigue vigente
const isFreeWindowActive = computed(() => {
  if (props.conversation?.is_free_window_active !== undefined) {
    return Boolean(props.conversation.is_free_window_active)
  }
  if (!props.conversation?.free_window_expires_at) return false
  return new Date(props.conversation.free_window_expires_at).getTime() > Date.now()
})

// Horas restantes de la ventana gratuita
const remainingHours = computed(() => {
  if (props.conversation?.remaining_free_window_hours !== undefined && props.conversation?.remaining_free_window_hours !== null) {
    return Math.max(0, Number(props.conversation.remaining_free_window_hours))
  }
  if (!props.conversation?.free_window_expires_at) return null
  const diffMs = new Date(props.conversation.free_window_expires_at).getTime() - Date.now()
  if (diffMs <= 0) return 0
  return Math.max(1, Math.round(diffMs / (1000 * 60 * 60)))
})
</script>
