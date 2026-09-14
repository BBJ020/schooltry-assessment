<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api, { apiError } from '../services/api';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import ValidationErrors from '../components/ValidationErrors.vue';

const stats = ref({});
const schools = ref([]);
const loading = ref(true);
const error = ref('');
const validation = ref({});
const search = ref('');
const form = ref({ name: '', slug: '', email: '' });

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [dashboard, listing] = await Promise.all([api.get('/superadmin/dashboard'), api.get('/superadmin/schools', { params: { search: search.value } })]);
        stats.value = dashboard.data.data;
        schools.value = listing.data.data;
    } catch (requestError) { error.value = apiError(requestError).message; }
    finally { loading.value = false; }
}

async function createSchool() {
    validation.value = {};
    try {
        await api.post('/superadmin/schools', form.value);
        form.value = { name: '', slug: '', email: '' };
        await load();
    } catch (requestError) {
        const normalized = apiError(requestError);
        error.value = normalized.message;
        validation.value = normalized.validation;
    }
}

onMounted(load);
</script>

<template>
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-widest text-indigo-600">Platform administration</p><h1 class="text-3xl font-black">School network</h1></div>
            <nav class="flex gap-2"><RouterLink class="btn-secondary" to="/superadmin">Dashboard</RouterLink><RouterLink class="btn-secondary" to="/superadmin/schools">Schools</RouterLink><RouterLink class="btn-secondary" to="/superadmin/admins">School Admins</RouterLink></nav>
        </div>
        <ErrorAlert :message="error" @dismiss="error = ''" />
        <LoadingState v-if="loading" label="Loading platform data…" />
        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(value, key) in stats" :key="key" class="card"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ key.replaceAll('_', ' ') }}</p><p class="mt-2 text-3xl font-black">{{ value }}</p></div>
            </div>
            <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div class="card overflow-x-auto">
                    <div class="mb-4 flex gap-2"><input v-model="search" class="field" placeholder="Search schools" @keyup.enter="load"><button class="btn-secondary" @click="load">Search</button></div>
                    <table class="w-full min-w-180 text-left text-sm"><thead class="text-slate-500"><tr><th class="p-2">School</th><th>Status</th><th>Admins</th><th>Users</th><th>Lecturers</th><th>Students</th><th>Courses</th><th></th></tr></thead>
                        <tbody><tr v-for="school in schools" :key="school.id" class="border-t border-slate-100"><td class="p-2"><strong>{{ school.name }}</strong><small class="block text-slate-500">{{ school.slug }}</small></td><td><span class="badge" :class="school.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ school.is_active ? 'Active' : 'Inactive' }}</span></td><td>{{ school.admin_count }}</td><td>{{ school.user_count }}</td><td>{{ school.lecturer_count }}</td><td>{{ school.student_count }}</td><td>{{ school.course_count }}</td><td><RouterLink class="font-semibold text-indigo-600" :to="`/superadmin/schools/${school.id}`">Manage</RouterLink></td></tr>
                            <tr v-if="!schools.length"><td colspan="8" class="p-8 text-center text-slate-500">No schools found.</td></tr></tbody></table>
                </div>
                <form class="card space-y-4" @submit.prevent="createSchool"><h2 class="text-lg font-black">Create school</h2><label><span class="label">Name</span><input v-model="form.name" class="field" required></label><label><span class="label">Slug</span><input v-model="form.slug" class="field" required></label><label><span class="label">Email</span><input v-model="form.email" class="field" type="email"></label><ValidationErrors :errors="validation"/><button class="btn-primary w-full">Create school</button></form>
            </div>
        </template>
    </section>
</template>
