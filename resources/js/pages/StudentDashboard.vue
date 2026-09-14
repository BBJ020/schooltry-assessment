<script setup>
import { computed, onMounted } from 'vue';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import { useAssignmentsStore } from '../stores/assignments';

const store = useAssignmentsStore();

const submissionsByAssignment = computed(() => Object.fromEntries(
    store.studentSubmissions.map((submission) => [submission.assignment_id, submission]),
));

const assignmentsById = computed(() => Object.fromEntries(
    store.studentAssignments.map((assignment) => [assignment.id, assignment]),
));

function submissionAssignmentLabel(submission) {
    const assignment = assignmentsById.value[submission.assignment_id];

    if (!assignment) {
        return `Assignment #${submission.assignment_id}`;
    }

    const courseCode = assignment.course?.code || 'Course';

    return `Assignment: ${courseCode} - ${assignment.title}`;
}

onMounted(async () => {
    try {
        await Promise.all([
            store.loadStudentAssignments(),
            store.loadStudentSubmissions(),
        ]);
    } catch {
        /* Safe error is displayed by the store. */
    }
});
</script>

<template>
    <section class="space-y-7">
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">
                Student workspace
            </p>

            <h1 class="mt-1 text-3xl font-black">
                Your assignments
            </h1>

            <p class="mt-2 text-slate-500">
                This list contains only assignments returned for your account by the API.
            </p>
        </div>

        <ErrorAlert
            :message="store.error"
            @dismiss="store.clearMessages"
        />

        <LoadingState
            v-if="store.loading && !store.studentAssignments.length"
            label="Loading your assignments…"
        />

        <div
            v-else-if="!store.studentAssignments.length"
            class="card py-14 text-center"
        >
            <p class="font-semibold">
                No assignments available
            </p>

            <p class="mt-1 text-sm text-slate-500">
                New work assigned to you will appear here.
            </p>
        </div>

        <div
            v-else
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="assignment in store.studentAssignments"
                :key="assignment.id"
                class="card flex flex-col"
            >
                <div class="flex items-start justify-between gap-3">
                    <span class="badge bg-indigo-50 text-indigo-700">
                        {{ assignment.course?.code }}
                    </span>

                    <span
                        class="badge"
                        :class="
                            submissionsByAssignment[assignment.id]
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-slate-100 text-slate-600'
                        "
                    >
                        {{
                            submissionsByAssignment[assignment.id]
                                ? 'Submitted'
                                : 'Not submitted'
                        }}
                    </span>
                </div>

                <h2 class="mt-4 text-lg font-bold">
                    {{ assignment.title }}
                </h2>

                <p class="mt-2 line-clamp-3 whitespace-pre-wrap text-sm text-slate-600">
                    {{ assignment.description }}
                </p>

                <p class="mt-4 text-xs text-slate-500">
                    Due:
                    {{
                        assignment.due_at
                            ? new Date(assignment.due_at).toLocaleString()
                            : 'No deadline'
                    }}
                </p>

                <div class="mt-auto flex gap-2 pt-5">
                    <RouterLink
                        class="btn-primary flex-1"
                        :to="{
                            name: 'student-assignment',
                            params: { assignmentId: assignment.id },
                        }"
                    >
                        View & submit
                    </RouterLink>

                    <RouterLink
                        class="btn-secondary"
                        :to="{
                            name: 'student-result',
                            params: { assignmentId: assignment.id },
                        }"
                    >
                        Result
                    </RouterLink>
                </div>
            </article>
        </div>

        <div class="card">
            <h2 class="text-lg font-bold">
                Submission history
            </h2>

            <p
                v-if="!store.studentSubmissions.length"
                class="mt-3 text-sm text-slate-500"
            >
                You have not submitted any work yet.
            </p>

            <div
                v-else
                class="mt-4 divide-y divide-slate-100"
            >
                <div
                    v-for="submission in store.studentSubmissions"
                    :key="submission.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <strong class="text-sm">
                            {{ submissionAssignmentLabel(submission) }}
                        </strong>

                        <p class="mt-1 text-xs text-slate-500">
                            Submitted
                            {{ new Date(submission.submitted_at).toLocaleString() }}
                        </p>
                    </div>

                    <span class="badge bg-emerald-100 text-emerald-800">
                        Received
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>