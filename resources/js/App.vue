<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();
const dashboard = computed(() => auth.landingRoute);

async function logout() {
    await auth.logout();
    await router.replace({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header v-if="auth.isAuthenticated" class="border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <RouterLink :to="dashboard" class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-600 font-black text-white">S</span>
                    <span>
                        <strong class="block text-base leading-tight">SchoolTry</strong>
                        <small class="text-slate-500">{{ auth.user?.school?.name }}</small>
                    </span>
                </RouterLink>
                <div class="flex items-center gap-4">
                    <span class="hidden text-sm text-slate-600 sm:block">{{ auth.user?.name }}</span>
                    <button class="btn-secondary" type="button" @click="logout">Sign out</button>
                </div>
            </div>
        </header>

        <main :class="auth.isAuthenticated ? 'mx-auto max-w-7xl px-5 py-8 lg:px-8' : ''">
            <RouterView />
        </main>
    </div>
</template>
