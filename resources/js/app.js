import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { onUnauthorized } from './services/api';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

const auth = useAuthStore(pinia);
onUnauthorized(async () => {
    auth.clearSession();
    if (router.currentRoute.value.name !== 'login') {
        await router.replace({ name: 'login', query: { reason: 'expired' } });
    }
});

app.use(router);

app.mount('#app');
