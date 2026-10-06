import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import { initPwa } from './services/pwaService';
import { useThemeStore } from './stores/useThemeStore';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Inicializar tema claro / oscuro guardado
const themeStore = useThemeStore(pinia);
themeStore.initTheme();

app.mount('#app');

// Inicializar Service Worker y soporte PWA
initPwa();
