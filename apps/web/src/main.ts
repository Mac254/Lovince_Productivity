import './styles/app.css';
import { onUnauthorized } from '@focus/api-client';
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './app/router';
import { useAuthStore } from './stores/auth';
import { useDashboardStore } from './stores/dashboard';
import { useGoalsStore } from './stores/goals';
import { usePreferencesStore } from './stores/preferences';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
usePreferencesStore(pinia);
app.use(router);

// Only a session that was valid and has since expired needs a redirect.
onUnauthorized(() => {
    const auth = useAuthStore(pinia);
    if (!auth.isAuthenticated) return;
    auth.clear();
    useGoalsStore(pinia).reset();
    useDashboardStore(pinia).reset();
    if (router.currentRoute.value.name !== 'login') {
        void router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
    }
});

app.mount('#app');

// Installed-app support in production builds only; the dev server always serves fresh code.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => undefined);
    });
}
