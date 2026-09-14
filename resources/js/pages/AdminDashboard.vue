<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api, { apiError } from '../services/api';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import TemporaryPassword from '../components/TemporaryPassword.vue';
import ValidationErrors from '../components/ValidationErrors.vue';
import { tenantUserPayload } from '../utils/management';

const route = useRoute();
const section = computed(() => route.meta.section || 'dashboard');
const loading = ref(true); const error = ref(''); const validation = ref({}); const stats = ref({}); const records = ref([]); const search = ref(''); const temporaryPassword = ref(''); const lecturers = ref([]);
const userForm = ref({ name: '', email: '', role: 'student' });
const courseForm = ref({ code: '', title: '', description: '', lecturer_id: '' });
const navigation = [['Dashboard', '/admin'], ['Users', '/admin/users'], ['Students', '/admin/students'], ['Lecturers', '/admin/lecturers'], ['Administrators', '/admin/admins'], ['Courses', '/admin/courses'], ['Enrollments', '/admin/enrollments']];

async function load() {
    loading.value = true; error.value = '';
    try {
        if (section.value === 'dashboard') { stats.value = (await api.get('/admin/dashboard')).data.data; records.value = []; }
        else if (['courses', 'enrollments'].includes(section.value)) {
            records.value = (await api.get('/admin/courses', { params: { search: search.value } })).data.data;
            lecturers.value = (await api.get('/admin/lecturers')).data.data;
        } else {
            const endpoint = section.value === 'users' ? '/admin/users' : `/admin/${section.value}`;
            records.value = (await api.get(endpoint, { params: { search: search.value } })).data.data;
        }
    } catch (requestError) { error.value = apiError(requestError).message; }
    finally { loading.value = false; }
}
async function createUser() { validation.value = {}; try { const response = await api.post('/admin/users', tenantUserPayload(userForm.value)); temporaryPassword.value = response.data.temporary_password; userForm.value = { name: '', email: '', role: 'student' }; await load(); } catch (e) { const n = apiError(e); error.value = n.message; validation.value = n.validation; } }
async function createCourse() { validation.value = {}; try { await api.post('/admin/courses', { ...courseForm.value, lecturer_id: Number(courseForm.value.lecturer_id) }); courseForm.value = { code: '', title: '', description: '', lecturer_id: '' }; await load(); } catch (e) { const n = apiError(e); error.value = n.message; validation.value = n.validation; } }
async function userAction(user, action) { if (action !== 'reset-password' && !confirm(`Confirm ${action}?`)) return; try { const response = await api.post(`/admin/users/${user.id}/${action}`); if (action === 'reset-password') temporaryPassword.value = response.data.temporary_password; await load(); } catch (e) { error.value = apiError(e).message; } }
async function changeRole(user) { try { await api.put(`/admin/users/${user.id}/role`, { role: user.roles[0] }); await load(); } catch (e) { error.value = apiError(e).message; } }
async function editUser(user) { const name = prompt('Name', user.name); if (name === null) return; const email = prompt('Email', user.email); if (email === null) return; try { await api.put(`/admin/users/${user.id}`, { name, email }); await load(); } catch (e) { error.value = apiError(e).message; } }
watch(section, load); onMounted(load);
</script>

<template>
    <section class="space-y-6"><div><p class="text-sm font-bold uppercase tracking-widest text-indigo-600">School administration</p><h1 class="text-3xl font-black capitalize">{{ section }}</h1></div>
        <nav class="flex flex-wrap gap-2"><RouterLink v-for="[label, path] in navigation" :key="path" :to="path" class="btn-secondary">{{ label }}</RouterLink></nav><ErrorAlert :message="error" @dismiss="error = ''"/><LoadingState v-if="loading" label="Loading school data…"/>
        <template v-else><div v-if="section === 'dashboard'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><div v-for="(value, key) in stats" :key="key" class="card"><p class="text-xs font-bold uppercase text-slate-500">{{ key.replaceAll('_', ' ') }}</p><p class="mt-2 text-3xl font-black">{{ value }}</p></div></div>
            <div v-else-if="['courses', 'enrollments'].includes(section)" class="grid gap-6 lg:grid-cols-[1fr_22rem]"><div class="card overflow-x-auto"><div class="mb-4 flex gap-2"><input v-model="search" class="field" placeholder="Search courses" @keyup.enter="load"><button class="btn-secondary" @click="load">Search</button></div><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Course</th><th>Lecturer</th><th>Students</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="course in records" :key="course.id" class="border-t"><td class="p-2"><strong>{{ course.code }}</strong><small class="block">{{ course.title }}</small></td><td>{{ course.lecturer?.name }}</td><td>{{ course.student_count }}</td><td>{{ course.is_active ? 'Active' : 'Inactive' }}</td><td><RouterLink class="text-indigo-600" :to="`/admin/courses/${course.id}`">Manage</RouterLink></td></tr><tr v-if="!records.length"><td colspan="5" class="p-8 text-center text-slate-500">No courses found.</td></tr></tbody></table></div>
                <form class="card space-y-3" @submit.prevent="createCourse"><h2 class="font-black">Create course</h2><input v-model="courseForm.code" class="field" placeholder="Code" required><input v-model="courseForm.title" class="field" placeholder="Title" required><textarea v-model="courseForm.description" class="field" placeholder="Description"></textarea><select v-model="courseForm.lecturer_id" class="field" required><option value="">Select lecturer</option><option v-for="user in lecturers" :key="user.id" :value="user.id">{{ user.name }}</option></select><ValidationErrors :errors="validation"/><button class="btn-primary w-full">Create course</button></form></div>
            <div v-else class="grid gap-6 lg:grid-cols-[1fr_22rem]"><div class="card overflow-x-auto"><div class="mb-4 flex gap-2"><input v-model="search" class="field" placeholder="Search users" @keyup.enter="load"><button class="btn-secondary" @click="load">Search</button></div><table class="w-full text-left text-sm"><thead><tr><th class="p-2">User</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr v-for="user in records" :key="user.id" class="border-t"><td class="p-2"><strong>{{ user.name }}</strong><small class="block text-slate-500">{{ user.email }}</small></td><td><select v-model="user.roles[0]" class="field max-w-36" @change="changeRole(user)"><option>student</option><option>lecturer</option><option>admin</option></select></td><td>{{ user.is_active ? 'Active' : 'Inactive' }}</td><td class="space-x-2"><button class="text-indigo-600" @click="userAction(user, user.is_active ? 'deactivate' : 'activate')">{{ user.is_active ? 'Deactivate' : 'Activate' }}</button><button class="text-indigo-600" @click="editUser(user)">Edit</button><button class="text-indigo-600" @click="userAction(user, 'reset-password')">Reset</button></td></tr><tr v-if="!records.length"><td colspan="4" class="p-8 text-center text-slate-500">No users found.</td></tr></tbody></table></div>
                <form class="card space-y-3" @submit.prevent="createUser"><h2 class="font-black">Create tenant user</h2><input v-model="userForm.name" class="field" placeholder="Name" required><input v-model="userForm.email" class="field" placeholder="Email" type="email" required><select v-model="userForm.role" class="field"><option>student</option><option>lecturer</option><option>admin</option></select><ValidationErrors :errors="validation"/><button class="btn-primary w-full">Create user</button></form></div></template>
        <TemporaryPassword v-if="temporaryPassword" :password="temporaryPassword" @close="temporaryPassword = ''"/>
    </section>
</template>
