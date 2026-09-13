<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import ErrorAlert from '../components/ErrorAlert.vue';
import ValidationErrors from '../components/ValidationErrors.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const form = reactive({ email: '', password: '', device_name: 'SchoolTry browser' });

async function submit() {
    try {
        const destination = await auth.login({ ...form });
        await router.replace(destination);
    } catch {
        // The store exposes a safe, normalized error for the template.
    }
}
</script>

<template>
    <section class="grid min-h-screen lg:grid-cols-2">
        <div class="hidden bg-indigo-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3 text-xl font-bold">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-white text-indigo-700">S</span>
                SchoolTry
            </div>
            <div class="max-w-xl pb-16">
                <p class="mb-4 text-sm font-semibold uppercase tracking-[0.25em] text-indigo-200">Assignment workspace</p>
                <h1 class="text-5xl font-black leading-tight">Teaching and learning, in one secure place.</h1>
                <p class="mt-5 text-lg text-indigo-100">Create assessments, submit work, and release results with school-level data isolation.</p>
            </div>
        </div>

        <div class="flex items-center justify-center px-5 py-12">
            <form class="w-full max-w-md space-y-5" @submit.prevent="submit">
                <div>
                    <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">Welcome back</p>
                    <h2 class="mt-2 text-3xl font-black">Sign in to SchoolTry</h2>
                    <p class="mt-2 text-slate-500">Use the account issued by your school.</p>
                </div>

                <ErrorAlert :message="auth.error" @dismiss="auth.error = ''" />
                <ValidationErrors :errors="auth.validationErrors" />

                <label class="block">
                    <span class="label">Email address</span>
                    <input v-model.trim="form.email" class="field" type="email" autocomplete="username" required>
                </label>
                <label class="block">
                    <span class="label">Password</span>
                    <input v-model="form.password" class="field" type="password" autocomplete="current-password" required>
                </label>
                <button class="btn-primary w-full" type="submit" :disabled="auth.loading">
                    {{ auth.loading ? 'Signing in…' : 'Sign in' }}
                </button>
                <p class="text-center text-xs text-slate-400">Your session is kept only in this browser tab.</p>
            </form>
        </div>
    </section>
</template>
