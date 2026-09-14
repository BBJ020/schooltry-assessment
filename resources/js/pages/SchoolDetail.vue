<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api, { apiError } from '../services/api';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import TemporaryPassword from '../components/TemporaryPassword.vue';
import ValidationErrors from '../components/ValidationErrors.vue';

const route = useRoute();

const school = ref(null);
const loading = ref(true);
const error = ref('');
const validation = ref({});
const temporaryPassword = ref('');

const adminForm = ref({
    name: '',
    email: '',
});

const assignUserId = ref('');

async function load() {
    loading.value = true;

    try {
        school.value = (
            await api.get(`/superadmin/schools/${route.params.id}`)
        ).data.data;
    } catch (e) {
        error.value = apiError(e).message;
    } finally {
        loading.value = false;
    }
}

async function status(active) {
    if (!confirm(`${active ? 'Activate' : 'Deactivate'} this school?`)) return;

    try {
        school.value = (
            await api.post(
                `/superadmin/schools/${school.value.id}/${active ? 'activate' : 'deactivate'}`
            )
        ).data.data;

        await load();
    } catch (e) {
        error.value = apiError(e).message;
    }
}

async function save() {
    try {
        await api.put(`/superadmin/schools/${school.value.id}`, {
            name: school.value.name,
            slug: school.value.slug,
            email: school.value.email,
            phone: school.value.phone,
        });

        await load();
    } catch (e) {
        const n = apiError(e);
        error.value = n.message;
        validation.value = n.validation;
    }
}

async function createAdmin() {
    try {
        const response = await api.post(
            `/superadmin/schools/${school.value.id}/admins`,
            adminForm.value
        );

        temporaryPassword.value = response.data.temporary_password;
        adminForm.value = {
            name: '',
            email: '',
        };

        await load();
    } catch (e) {
        const n = apiError(e);
        error.value = n.message;
        validation.value = n.validation;
    }
}

async function assignAdmin() {
    try {
        await api.post(
            `/superadmin/schools/${school.value.id}/admins/assign`,
            { user_id: Number(assignUserId.value) }
        );

        assignUserId.value = '';
        await load();
    } catch (e) {
        const n = apiError(e);
        error.value = n.message;
        validation.value = n.validation;
    }
}

async function adminAction(admin, action) {
    if (action !== 'reset-password' && !confirm(`Confirm ${action}?`)) return;

    try {
        const response = await api.post(
            `/superadmin/users/${admin.id}/${action}`
        );

        if (action === 'reset-password') {
            temporaryPassword.value = response.data.temporary_password;
        }

        await load();
    } catch (e) {
        error.value = apiError(e).message;
    }
}

async function removeAdmin(admin) {
    if (!confirm('Remove this administrator role?')) return;

    try {
        await api.delete(
            `/superadmin/schools/${school.value.id}/admins/${admin.id}`
        );

        await load();
    } catch (e) {
        error.value = apiError(e).message;
    }
}

onMounted(load);
</script>

<template>
    <section class="space-y-6">
        <RouterLink
            class="text-sm font-semibold text-indigo-600"
            to="/superadmin/schools"
        >
            ← Schools
        </RouterLink>

        <ErrorAlert
            :message="error"
            @dismiss="error = ''"
        />

        <LoadingState
            v-if="loading"
            label="Loading school…"
        />

        <template v-else-if="school">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black">
                        {{ school.name }}
                    </h1>

                    <p class="text-slate-500">
                        {{ school.slug }}
                    </p>
                </div>

                <button
                    :class="school.is_active ? 'btn-danger' : 'btn-primary'"
                    @click="status(!school.is_active)"
                >
                    {{ school.is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <div
                    v-for="key in [
                        'user_count',
                        'admin_count',
                        'lecturer_count',
                        'student_count',
                        'course_count'
                    ]"
                    :key="key"
                    class="card"
                >
                    <p class="text-xs uppercase text-slate-500">
                        {{ key.replaceAll('_', ' ') }}
                    </p>

                    <strong class="text-2xl">
                        {{ school[key] }}
                    </strong>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <form
                    class="card grid gap-3"
                    @submit.prevent="save"
                >
                    <h2 class="text-lg font-black">
                        School profile details
                    </h2>

                    <div>
                        <div style="display:block; margin-bottom:6px; font-size:14px; font-weight:700; color:#334155;">
                            School Name
                        </div>

                        <input
                            id="school-name"
                            v-model="school.name"
                            class="field"
                            type="text"
                            placeholder="School name"
                        >
                    </div>

                    <div>
                        <div style="display:block; margin-bottom:6px; font-size:14px; font-weight:700; color:#334155;">
                            School Slug
                        </div>

                        <input
                            id="school-slug"
                            v-model="school.slug"
                            class="field"
                            type="text"
                            placeholder="e.g. sust"
                        >
                    </div>

                    <div>
                        <div style="display:block; margin-bottom:6px; font-size:14px; font-weight:700; color:#334155;">
                            School Email Address
                        </div>

                        <input
                            id="school-email"
                            v-model="school.email"
                            class="field"
                            type="email"
                            placeholder="school@example.com"
                        >
                    </div>

                    <div>
                        <div style="display:block; margin-bottom:6px; font-size:14px; font-weight:700; color:#334155;">
                            School Phone Number
                        </div>

                        <input
                            id="school-phone"
                            v-model="school.phone"
                            class="field"
                            type="tel"
                            placeholder="+234..."
                        >
                    </div>

                    <ValidationErrors :errors="validation" />

                    <button class="btn-primary">
                        Save changes
                    </button>
                </form>

                <div class="space-y-4">
                    <form
                        class="card space-y-3"
                        @submit.prevent="createAdmin"
                    >
                        <h2 class="font-black">
                            Create administrator
                        </h2>

                        <div>
                            <label
                                for="admin-name"
                                class="mb-1 block text-sm font-semibold text-slate-700"
                            >
                                Administrator name
                            </label>

                            <input
                                id="admin-name"
                                v-model="adminForm.name"
                                class="field"
                                type="text"
                                placeholder="Full name"
                                required
                            >
                        </div>

                        <div>
                            <label
                                for="admin-email"
                                class="mb-1 block text-sm font-semibold text-slate-700"
                            >
                                Administrator email
                            </label>

                            <input
                                id="admin-email"
                                v-model="adminForm.email"
                                class="field"
                                type="email"
                                placeholder="Email address"
                                required
                            >
                        </div>

                        <button class="btn-primary">
                            Create and generate password
                        </button>
                    </form>

                    <form
                        class="card space-y-3"
                        @submit.prevent="assignAdmin"
                    >
                        <div>
                            <label
                                for="assign-user-id"
                                class="mb-1 block text-sm font-semibold text-slate-700"
                            >
                                Existing user ID
                            </label>

                            <input
                                id="assign-user-id"
                                v-model="assignUserId"
                                class="field"
                                type="number"
                                placeholder="Eligible user ID"
                                required
                            >
                        </div>

                        <button class="btn-secondary">
                            Assign existing administrator
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h2 class="mb-3 text-lg font-black">
                    School administrators
                </h2>

                <div
                    v-if="!school.admins?.length"
                    class="text-sm text-slate-500"
                >
                    No administrators assigned.
                </div>

                <div
                    v-for="admin in school.admins"
                    :key="admin.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 py-3"
                >
                    <div>
                        <strong>
                            {{ admin.name }}
                        </strong>

                        <small class="block text-slate-500">
                            {{ admin.email }}
                        </small>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            class="btn-secondary"
                            @click="adminAction(
                                admin,
                                admin.is_active ? 'deactivate' : 'activate'
                            )"
                        >
                            {{ admin.is_active ? 'Deactivate' : 'Activate' }}
                        </button>

                        <button
                            class="btn-secondary"
                            @click="adminAction(admin, 'reset-password')"
                        >
                            Reset password
                        </button>

                        <button
                            class="btn-danger"
                            @click="removeAdmin(admin)"
                        >
                            Remove role
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <TemporaryPassword
            v-if="temporaryPassword"
            :password="temporaryPassword"
            @close="temporaryPassword = ''"
        />
    </section>
</template>
