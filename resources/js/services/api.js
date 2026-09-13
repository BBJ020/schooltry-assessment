import axios from 'axios';

const TOKEN_KEY = 'schooltry_access_token';
let unauthorizedHandler = null;

export const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export function getStoredToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function storeToken(token) {
    sessionStorage.setItem(TOKEN_KEY, token);
}

export function clearStoredToken() {
    sessionStorage.removeItem(TOKEN_KEY);
}

export function onUnauthorized(handler) {
    unauthorizedHandler = handler;
}

export function apiError(error, fallback = 'Something went wrong. Please try again.') {
    const status = error?.response?.status;
    const messages = {
        401: 'Your session has expired. Please sign in again.',
        403: 'You do not have permission to perform this action.',
        404: 'The requested item is unavailable.',
        422: 'Please correct the highlighted fields.',
        429: 'Too many attempts. Please wait and try again.',
    };

    return {
        status,
        message: messages[status] || fallback,
        validation: status === 422 && error.response?.data?.errors ? error.response.data.errors : {},
    };
}

api.interceptors.request.use((config) => {
    const token = getStoredToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401 && !error.config?.url?.endsWith('/login')) {
            clearStoredToken();
            await unauthorizedHandler?.();
        }

        return Promise.reject(error);
    },
);

export default api;
