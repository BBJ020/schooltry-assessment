<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import { useAssignmentsStore } from '../stores/assignments';

const route = useRoute();
const store = useAssignmentsStore();
const assignmentId = Number(route.params.assignmentId);
const grade = ref(null);
const unavailable = ref(false);

onMounted(async () => {
    try {
        grade.value = await store.loadGrade(assignmentId);
        unavailable.value = grade.value === null;
    } catch {
        unavailable.value = false;
    }
});
</script>

<template>
    <section class="mx-auto max-w-2xl space-y-6">
        <RouterLink class="text-sm font-semibold text-indigo-600 hover:text-indigo-800" :to="{ name: 'student-dashboard' }">← Back to assignments</RouterLink>
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">Results</p>
            <h1 class="mt-1 text-3xl font-black">Assignment result</h1>
        </div>
        <ErrorAlert :message="store.error" @dismiss="store.clearMessages" />
        <LoadingState v-if="store.loading" label="Checking for a released result…" />
        <div v-else-if="unavailable" class="card py-14 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-100 text-2xl">⏳</div>
            <h2 class="mt-4 text-xl font-bold">Result not available</h2>
            <p class="mt-2 text-sm text-slate-500">Your lecturer has not released a result for this assignment yet.</p>
        </div>
        <article v-else-if="grade" class="card">
            <p class="text-sm font-semibold text-slate-500">Score</p>
            <p class="mt-1 text-5xl font-black text-indigo-700">{{ grade.score }}</p>
            <div class="mt-7 border-t border-slate-100 pt-6">
                <h2 class="font-bold">Lecturer feedback</h2>
                <p class="mt-2 whitespace-pre-wrap text-slate-700">{{ grade.feedback || 'No feedback was provided.' }}</p>
            </div>
        </article>
    </section>
</template>
