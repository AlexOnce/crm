<template>
  <div class="relative inline-flex items-center">
    <button
      @click="themeStore.toggleTheme()"
      type="button"
      :class="[
        'px-2.5 sm:px-3 py-1.5 rounded-xl border text-xs flex items-center gap-1.5 transition-all duration-200 cursor-pointer font-semibold shadow-xs select-none group',
        themeStore.isDark
          ? 'bg-slate-900/90 hover:bg-slate-800 border-slate-700/80 text-amber-300 hover:text-amber-200 shadow-[0_0_12px_rgba(245,158,11,0.1)]'
          : 'bg-white hover:bg-slate-100 border-slate-200 text-slate-700 hover:text-slate-900 shadow-[0_1px_3px_rgba(0,0,0,0.06)]'
      ]"
      :title="themeStore.isDark ? 'Cambiar a Modo Claro' : 'Cambiar a Modo Oscuro'"
      aria-label="Alternar tema de la aplicación"
    >
      <!-- Icono animado con micro-transición -->
      <div class="relative w-4 h-4 flex items-center justify-center">
        <!-- Sol (visible en modo oscuro) -->
        <Sun
          v-if="themeStore.isDark"
          class="w-4 h-4 text-amber-400 group-hover:rotate-45 transition-transform duration-300"
        />
        <!-- Luna (visible en modo claro) -->
        <Moon
          v-else
          class="w-4 h-4 text-sky-600 group-hover:-rotate-12 transition-transform duration-300"
        />
      </div>

      <!-- Texto opcional en pantallas más grandes -->
      <span v-if="showLabel" class="hidden md:inline font-medium">
        {{ themeStore.isDark ? 'Modo Claro' : 'Modo Oscuro' }}
      </span>
    </button>
  </div>
</template>

<script setup>
import { Sun, Moon } from 'lucide-vue-next';
import { useThemeStore } from '@/stores/useThemeStore';

defineProps({
  showLabel: {
    type: Boolean,
    default: false,
  },
});

const themeStore = useThemeStore();
</script>
