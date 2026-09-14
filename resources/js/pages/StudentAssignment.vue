<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import ValidationErrors from '../components/ValidationErrors.vue';
import { useAssignmentsStore } from '../stores/assignments';
import { saveBlob } from '../utils/download';
import { safeFilename } from '../utils/filename';

const route = useRoute();
const store = useAssignmentsStore();
const assignmentId = Number(route.params.assignmentId);
const text = ref('');
const file = ref(null);
const fileInput = ref(null);
const saved = ref(false);
const ownSubmission = computed(() => store.studentSubmissions.find((item) => item.assignment_id === assignmentId));

onMounted(async () => {
    try {
        await Promise.all([store.loadStudentAssignment(assignmentId), store.loadStudentSubmissions()]);
        text.value = ownSubmission.value?.submission_text || '';
    } catch { /* Safe error is displayed by the store. */ }
});

function chooseFile(event) {
    file.value = event.target.files?.[0] || null;
}

async function submit() {
    const formData = new FormData();
    if (text.value) formData.append('submission_text', text.value);
    if (file.value) formData.append('file', file.value);

    try {
        await store.submitAssignment(assignmentId, formData);
        saved.value = true;
        file.value = null;
        if (fileInput.value) fileInput.value.value = '';
    } catch { /* Validation errors are displayed below. */ }
}

async function downloadAssignmentFile() {
    try {
        const blob = await store.downloadStudentAssignmentAttachment(assignmentId);
        saveBlob(blob, store.selectedAssignment.attachment.file_name);
    } catch { /* Safe error is displayed by the store. */ }
}
</script>

<template>
    <section class="mx-auto max-w-4xl space-y-6">
        <RouterLink class="text-sm font-semibold text-indigo-600 hover:text-indigo-800" :to="{ name: 'student-dashboard' }">← Back to assignments</RouterLink>
        <ErrorAlert :message="store.error" @dismiss="store.clearMessages" />
        <ValidationErrors :errors="store.validationErrors" />
        <LoadingState v-if="store.loading && !store.selectedAssignment" label="Loading assignment…" />

        <template v-if="store.selectedAssignment">
            <article class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span class="badge bg-indigo-50 text-indigo-700">{{ store.selectedAssignment.course?.code }}</span>
                        <h1 class="mt-3 text-3xl font-black">{{ store.selectedAssignment.title }}</h1>
                    </div>
                    <span class="text-sm font-semibold text-slate-500">{{ store.selectedAssignment.maximum_score }} points</span>
                </div>
                <p class="mt-5 whitespace-pre-wrap text-slate-700">{{ store.selectedAssignment.description }}</p>
                <button v-if="store.selectedAssignment.attachment" class="btn-secondary mt-5" type="button" @click="downloadAssignmentFile">
                    Download assignment file: {{ safeFilename(store.selectedAssignment.attachment.file_name) }}
                </button>
                <p class="mt-5 text-sm text-slate-500">Due: {{ store.selectedAssignment.due_at ? new Date(store.selectedAssignment.due_at).toLocaleString() : 'No deadline' }}</p>
            </article>

            <form class="card space-y-5" @submit.prevent="submit">
                <div>
                    <h2 class="text-xl font-bold">{{ ownSubmission ? 'Update submission' : 'Submit your work' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Enter text, attach a supported file, or provide both.</p>
                </div>
                <div v-if="saved" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Your submission was received.</div>
                <label class="block">
                    <span class="label">Submission text</span>
                    <textarea v-model="text" class="field min-h-44" maxlength="20000" placeholder="Write your response here" />
                </label>
                <label class="block">
                    <span class="label">Attachment</span>
                    <input ref="fileInput" class="field" type="file" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" @change="chooseFile">
                    <span v-if="file" class="mt-2 block text-xs text-slate-500">Selected: {{ safeFilename(file.name) }}</span>
                    <span v-else-if="ownSubmission?.file" class="mt-2 block text-xs text-slate-500">Current: {{ safeFilename(ownSubmission.file.original_filename) }}</span>
                </label>
                <button class="btn-primary" type="submit" :disabled="store.loading || (!text && !file)">
                    {{ store.loading ? 'Submitting…' : 'Submit assignment' }}
                </button>
            </form>
        </template>
    </section>
</template>
