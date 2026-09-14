import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import api, { apiError, clearStoredToken, getStoredToken, storeToken } from '../services/api';
import { landingRouteForRoles } from '../router/access';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(getStoredToken());
    const user = ref(null);
    const initialized = ref(false);
    const loading = ref(false);
    const error = ref('');
    const validationErrors = ref({});

    const isAuthenticated = computed(() => Boolean(token.value && user.value));
    const roles = computed(() => user.value?.roles || []);
    const landingRoute = computed(() => landingRouteForRoles(roles.value));

    function hasRole(role) {
        return roles.value.includes(role);
    }

    function clearSession() {
        token.value = null;
        user.value = null;
        clearStoredToken();
    }

    async function login(credentials) {
        loading.value = true;
        error.value = '';
        validationErrors.value = {};

        try {
            const response = await api.post('/login', credentials);
            token.value = response.data.access_token;
            user.value = response.data.user;
            storeToken(token.value);
            return landingRoute.value;
        } catch (requestError) {
            const normalized = apiError(requestError, 'Unable to sign in.');
            error.value = normalized.message;
            validationErrors.value = normalized.validation;
            throw requestError;
        } finally {
            loading.value = false;
            initialized.value = true;
        }
    }

    async function fetchMe() {
        const response = await api.get('/me');
        user.value = response.data.data;
        return user.value;
    }

    async function hydrate() {
        if (initialized.value) return;

        if (!token.value) {
            initialized.value = true;
            return;
        }

        try {
            await fetchMe();
        } catch {
            clearSession();
        } finally {
            initialized.value = true;
        }
    }

    async function logout() {
        try {
            if (token.value) await api.post('/logout');
        } finally {
            clearSession();
        }
    }

    return {
        error,
        hasRole,
        hydrate,
        initialized,
        isAuthenticated,
        landingRoute,
        loading,
        login,
        logout,
        roles,
        clearSession,
        token,
        user,
        validationErrors,
    };
});
