import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useThemeStore = defineStore('theme', () => {
  // Obtenemos el tema guardado en localStorage o por defecto 'dark'
  const savedTheme = localStorage.getItem('crm_theme') || 'dark';
  const theme = ref(savedTheme); // 'dark' | 'light' | 'system'

  const systemIsDark = ref(
    typeof window !== 'undefined' && window.matchMedia
      ? window.matchMedia('(prefers-color-scheme: dark)').matches
      : true
  );

  // Escuchar cambios de tema del sistema operativo
  if (typeof window !== 'undefined' && window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', (e) => {
      systemIsDark.value = e.matches;
      if (theme.value === 'system') {
        applyTheme(e.matches ? 'dark' : 'light');
      }
    });
  }

  const isDark = computed(() => {
    if (theme.value === 'system') {
      return systemIsDark.value;
    }
    return theme.value === 'dark';
  });

  function applyTheme(effectiveTheme) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    const metaThemeColor = document.querySelector('meta[name="theme-color"]');

    if (effectiveTheme === 'dark') {
      root.classList.add('dark');
      root.classList.remove('light');
      root.setAttribute('data-theme', 'dark');
      if (metaThemeColor) {
        metaThemeColor.setAttribute('content', '#020617');
      }
    } else {
      root.classList.remove('dark');
      root.classList.add('light');
      root.setAttribute('data-theme', 'light');
      if (metaThemeColor) {
        metaThemeColor.setAttribute('content', '#f8fafc');
      }
    }
  }

  function setTheme(newTheme) {
    theme.value = newTheme;
    localStorage.setItem('crm_theme', newTheme);
    const effective = newTheme === 'system' ? (systemIsDark.value ? 'dark' : 'light') : newTheme;
    applyTheme(effective);
  }

  function toggleTheme() {
    if (isDark.value) {
      setTheme('light');
    } else {
      setTheme('dark');
    }
  }

  function initTheme() {
    const effective = theme.value === 'system' ? (systemIsDark.value ? 'dark' : 'light') : theme.value;
    applyTheme(effective);
  }

  // Inicializar inmediatamente
  initTheme();

  return {
    theme,
    isDark,
    setTheme,
    toggleTheme,
    initTheme,
  };
});
