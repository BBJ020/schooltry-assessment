<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import ValidationErrors from '../components/ValidationErrors.vue';
import { useAssignmentsStore } from '../stores/assignments';
import { safeFilename } from '../utils/filename';

const store = useAssignmentsStore();
const activeCourseId = ref(null);
const activeAssignmentId = ref(null);
const notice = ref('');
const courseForm = reactive({ code: '', title: '', description: '' });
const assignmentForm = reactive({
    title: '', description: '', maximum_score: 100, due_at: '', target_type: 'all', target_student_ids: [],
});
const gradeDrafts = reactive({});

const assignments = computed(() => store.courseAssignments[activeCourseId.value] || []);
const students = computed(() => store.courseStudents[activeCourseId.value] || []);
const submissions = computed(() => store.assignmentSubmissions[activeAssignmentId.value] || []);

onMounted(async () => {
    try {
        await store.loadLecturerCourses();
        if (store.lecturerCourses.length) await selectCourse(store.lecturerCourses[0].id);
    } catch { /* Safe error is displayed by the store. */ }
});

async function selectCourse(courseId) {
    activeCourseId.value = courseId;
    activeAssignmentId.value = null;
    try {
        await Promise.all([store.loadCourseAssignments(courseId), store.loadCourseStudents(courseId)]);
    } catch { /* Safe error is displayed by the store. */ }
}

async function createCourse() {
    try {
        const course = await store.createCourse({ ...courseForm });
        Object.assign(courseForm, { code: '', title: '', description: '' });
        notice.value = 'Course created.';
        await selectCourse(course.id);
    } catch { /* Validation errors are displayed below. */ }
}

async function createAssignment() {
    if (!activeCourseId.value) return;
    const payload = {
        title: assignmentForm.title,
        description: assignmentForm.description || null,
        maximum_score: assignmentForm.maximum_score,
        due_at: assignmentForm.due_at || null,
        target_type: assignmentForm.target_type,
        ...(assignmentForm.target_type === 'selected' ? { target_student_ids: [...assignmentForm.target_student_ids] } : {}),
    };
    try {
        await store.createAssignment(activeCourseId.value, payload);
        Object.assign(assignmentForm, {
            title: '', description: '', maximum_score: 100, due_at: '', target_type: 'all', target_student_ids: [],
        });
        notice.value = 'Assignment created.';
    } catch { /* Validation errors are displayed below. */ }
}

async function openSubmissions(assignmentId) {
    activeAssignmentId.value = assignmentId;
    try {
        await store.loadAssignmentSubmissions(assignmentId);
        for (const submission of submissions.value) {
            gradeDrafts[submission.id] = {
                score: submission.grade?.score ?? '',
                feedback: submission.grade?.feedback ?? '',
            };
        }
    } catch { /* Safe error is displayed by the store. */ }
}

async function saveGrade(submissionId) {
    try {
        await store.gradeSubmission(submissionId, {
            score: gradeDrafts[submissionId].score,
            feedback: gradeDrafts[submissionId].feedback || null,
        });
        notice.value = 'Grade saved. Students cannot view it until results are released.';
        await store.loadAssignmentSubmissions(activeAssignmentId.value);
    } catch { /* Validation errors are displayed below. */ }
}

async function release(assignmentId) {
    if (!window.confirm('Release all results for this assignment to students?')) return;
    try {
        await store.releaseAssignment(activeCourseId.value, assignmentId);
        notice.value = 'Results released.';
    } catch { /* Safe error is displayed by the store. */ }
}
</script>

<template>
    <section class="space-y-7">
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">Lecturer workspace</p>
            <h1 class="mt-1 text-3xl font-black">Courses and assessments</h1>
            <p class="mt-2 text-slate-500">Only courses owned by your account are returned by the API.</p>
        </div>

        <ErrorAlert :message="store.error" @dismiss="store.clearMessages" />
        <ValidationErrors :errors="store.validationErrors" />
        <div v-if="notice" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ notice }}</div>

        <div class="grid gap-6 xl:grid-cols-[340px_1fr]">
            <aside class="space-y-6">
                <form class="card space-y-4" @submit.prevent="createCourse">
                    <h2 class="text-lg font-bold">Create course</h2>
                    <label class="block"><span class="label">Course code</span><input v-model.trim="courseForm.code" class="field" maxlength="50" required></label>
                    <label class="block"><span class="label">Title</span><input v-model.trim="courseForm.title" class="field" required></label>
                    <label class="block"><span class="label">Description</span><textarea v-model="courseForm.description" class="field min-h-24" /></label>
                    <button class="btn-primary w-full" type="submit" :disabled="store.loading">Create course</button>
                </form>

                <div class="card">
                    <h2 class="mb-3 text-lg font-bold">My courses</h2>
                    <LoadingState v-if="store.loading && !store.lecturerCourses.length" label="Loading courses…" />
                    <p v-else-if="!store.lecturerCourses.length" class="text-sm text-slate-500">No courses yet.</p>
                    <div v-else class="space-y-2">
                        <button
                            v-for="course in store.lecturerCourses"
                            :key="course.id"
                            type="button"
                            class="w-full rounded-xl border p-3 text-left transition"
                            :class="activeCourseId === course.id ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'"
                            @click="selectCourse(course.id)"
                        >
                            <strong class="block">{{ course.code }}</strong>
                            <span class="text-sm text-slate-500">{{ course.title }}</span>
                        </button>
                    </div>
                </div>
            </aside>

            <div v-if="activeCourseId" class="space-y-6">
                <form class="card space-y-4" @submit.prevent="createAssignment">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Create assignment</h2>
                        <span class="text-xs text-slate-500">{{ students.length }} enrolled students</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block"><span class="label">Title</span><input v-model.trim="assignmentForm.title" class="field" required></label>
                        <label class="block"><span class="label">Maximum score</span><input v-model.number="assignmentForm.maximum_score" class="field" type="number" min="0.01" step="0.01" required></label>
                        <label class="block md:col-span-2"><span class="label">Description</span><textarea v-model="assignmentForm.description" class="field min-h-24" /></label>
                        <label class="block"><span class="label">Due date</span><input v-model="assignmentForm.due_at" class="field" type="datetime-local"></label>
                        <label class="block"><span class="label">Audience</span><select v-model="assignmentForm.target_type" class="field"><option value="all">All enrolled students</option><option value="selected">Selected students</option></select></label>
                    </div>
                    <fieldset v-if="assignmentForm.target_type === 'selected'" class="rounded-xl border border-slate-200 p-4">
                        <legend class="px-2 text-sm font-semibold">Select students</legend>
                        <p v-if="!students.length" class="text-sm text-slate-500">No enrolled students are available.</p>
                        <div v-else class="grid gap-2 sm:grid-cols-2">
                            <label v-for="student in students" :key="student.id" class="flex items-center gap-2 text-sm">
                                <input v-model="assignmentForm.target_student_ids" type="checkbox" :value="student.id">
                                <span>{{ student.name }}</span>
                            </label>
                        </div>
                    </fieldset>
                    <button class="btn-primary" type="submit" :disabled="store.loading">Create assignment</button>
                </form>

                <div class="card">
                    <h2 class="mb-4 text-lg font-bold">Assignments</h2>
                    <LoadingState v-if="store.loading && !assignments.length" label="Loading assignments…" />
                    <p v-else-if="!assignments.length" class="text-sm text-slate-500">No assignments in this course.</p>
                    <div v-else class="space-y-3">
                        <article v-for="assignment in assignments" :key="assignment.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold">{{ assignment.title }}</h3>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ assignment.description }}</p>
                                </div>
                                <span class="badge" :class="assignment.is_released ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ assignment.is_released ? 'Released' : 'Unreleased' }}
                                </span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="btn-secondary" type="button" @click="openSubmissions(assignment.id)">View submissions</button>
                                <button v-if="!assignment.is_released" class="btn-primary" type="button" @click="release(assignment.id)">Release results</button>
                            </div>
                        </article>
                    </div>
                </div>

                <div v-if="activeAssignmentId" class="card">
                    <h2 class="mb-4 text-lg font-bold">Submissions</h2>
                    <p v-if="!submissions.length" class="text-sm text-slate-500">No students have submitted yet.</p>
                    <div v-else class="space-y-4">
                        <article v-for="submission in submissions" :key="submission.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap justify-between gap-2">
                                <strong>{{ submission.student?.name }}</strong>
                                <span class="text-xs text-slate-500">{{ new Date(submission.submitted_at).toLocaleString() }}</span>
                            </div>
                            <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ submission.submission_text }}</p>
                            <p v-if="submission.file" class="mt-2 text-sm text-slate-500">Attachment: {{ safeFilename(submission.file.original_filename) }}</p>
                            <form class="mt-4 grid gap-3 md:grid-cols-[140px_1fr_auto]" @submit.prevent="saveGrade(submission.id)">
                                <input v-model.number="gradeDrafts[submission.id].score" class="field" type="number" min="0" step="0.01" placeholder="Score" required>
                                <input v-model="gradeDrafts[submission.id].feedback" class="field" placeholder="Feedback">
                                <button class="btn-primary" type="submit">Save grade</button>
                            </form>
                        </article>
                    </div>
                </div>
            </div>
            <div v-else class="card grid min-h-64 place-items-center text-center text-slate-500">Create or select a course to begin.</div>
        </div>
    </section>
</template>
