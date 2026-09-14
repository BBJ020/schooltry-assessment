<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import ErrorAlert from '../components/ErrorAlert.vue';
import LoadingState from '../components/LoadingState.vue';
import ValidationErrors from '../components/ValidationErrors.vue';
import { useAssignmentsStore } from '../stores/assignments';
import { saveBlob } from '../utils/download';
import { safeFilename } from '../utils/filename';

const store = useAssignmentsStore();
const activeCourseId = ref(null);
const activeAssignmentId = ref(null);
const editingAssignmentId = ref(null);
const notice = ref('');
const assignmentFile = ref(null);
const assignmentFileInput = ref(null);
const editFile = ref(null);
const assignmentForm = reactive({
    title: '', description: '', maximum_score: 100, due_at: '', target_type: 'all', target_student_ids: [],
});
const gradeDrafts = reactive({});
const editForm = reactive({
    title: '', description: '', maximum_score: 100, due_at: '', target_type: 'all', target_student_ids: [], remove_file: false,
});

const selectedCourse = computed(() => store.lecturerCourses.find((course) => course.id === activeCourseId.value) || null);
const assignments = computed(() => store.courseAssignments[activeCourseId.value] || []);
const students = computed(() => store.courseStudents[activeCourseId.value] || []);
const submissions = computed(() => store.assignmentSubmissions[activeAssignmentId.value] || []);
const submissionsLoaded = computed(() => store.assignmentSubmissionsLoaded[activeAssignmentId.value] === true);
const selectedAssignment = computed(() => assignments.value.find((assignment) => assignment.id === activeAssignmentId.value) || null);

onMounted(async () => {
    try {
        await store.loadLecturerCourses();
    } catch { /* Safe error is displayed by the store. */ }
});

async function selectCourse(courseId) {
    activeCourseId.value = courseId;
    activeAssignmentId.value = null;
    cancelEdit();
    notice.value = '';
    try {
        await Promise.all([store.loadCourseAssignments(courseId), store.loadCourseStudents(courseId)]);
    } catch { /* Safe error is displayed by the store. */ }
}

async function createAssignment() {
    if (!selectedCourse.value) return;
    let payload = {
        title: assignmentForm.title,
        description: assignmentForm.description || null,
        maximum_score: assignmentForm.maximum_score,
        due_at: assignmentForm.due_at || null,
        target_type: assignmentForm.target_type,
        ...(assignmentForm.target_type === 'selected' ? { target_student_ids: [...assignmentForm.target_student_ids] } : {}),
    };

    payload = multipartPayload(payload, assignmentFile.value);

    try {
        await store.createAssignment(selectedCourse.value.id, payload);
        Object.assign(assignmentForm, {
            title: '', description: '', maximum_score: 100, due_at: '', target_type: 'all', target_student_ids: [],
        });
        assignmentFile.value = null;
        if (assignmentFileInput.value) assignmentFileInput.value.value = '';
        notice.value = 'Assignment created.';
    } catch { /* Validation errors are displayed below. */ }
}

function chooseAssignmentFile(event) {
    assignmentFile.value = event.target.files?.[0] || null;
}

function multipartPayload(payload, file) {
    if (!file) return payload;

    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
        if (value === null || value === '') return;
        if (Array.isArray(value)) value.forEach((item) => formData.append(`${key}[]`, String(item)));
        else formData.append(key, String(value));
    });
    formData.append('file', file);
    return formData;
}

function localDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
}

function startEdit(assignment) {
    editingAssignmentId.value = assignment.id;
    Object.assign(editForm, {
        title: assignment.title,
        description: assignment.description || '',
        maximum_score: Number(assignment.maximum_score),
        due_at: localDateTime(assignment.due_at),
        target_type: assignment.target_type,
        target_student_ids: [...(assignment.target_student_ids || [])],
        remove_file: false,
    });
    editFile.value = null;
}

function cancelEdit() {
    editingAssignmentId.value = null;
    editFile.value = null;
}

function chooseEditFile(event) {
    editFile.value = event.target.files?.[0] || null;
    if (editFile.value) editForm.remove_file = false;
}

async function saveAssignment(assignment) {
    const payload = {
        title: editForm.title,
        description: editForm.description || null,
        maximum_score: editForm.maximum_score,
        due_at: editForm.due_at || null,
        target_type: editForm.target_type,
        ...(editForm.target_type === 'selected' ? { target_student_ids: [...editForm.target_student_ids] } : {}),
        ...(editForm.remove_file ? { remove_file: true } : {}),
    };

    try {
        await store.updateAssignment(selectedCourse.value.id, assignment.id, multipartPayload(payload, editFile.value));
        cancelEdit();
        notice.value = 'Assignment updated.';
    } catch { /* Safe errors are displayed by the store. */ }
}

async function deleteAssignment(assignment) {
    if (!window.confirm(`Delete “${assignment.title}”? This cannot be undone.`)) return;

    try {
        await store.deleteAssignment(selectedCourse.value.id, assignment.id);
        if (activeAssignmentId.value === assignment.id) activeAssignmentId.value = null;
        if (editingAssignmentId.value === assignment.id) cancelEdit();
        notice.value = 'Assignment deleted.';
    } catch { /* Safe errors are displayed by the store. */ }
}

async function downloadAttachment(assignment) {
    try {
        const blob = await store.downloadLecturerAssignmentAttachment(assignment.id);
        saveBlob(blob, assignment.attachment.file_name);
    } catch { /* Safe error is displayed by the store. */ }
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
        await store.releaseAssignment(selectedCourse.value.id, assignmentId);
        notice.value = 'Results released.';
    } catch { /* Safe error is displayed by the store. */ }
}
</script>

<template>
    <section class="space-y-7">
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">Lecturer workspace</p>
            <h1 class="mt-1 text-3xl font-black">Courses and assessments</h1>
            <p class="mt-2 text-slate-500">Select a course assigned to you by a school administrator to manage its assessments.</p>
        </div>

        <ErrorAlert :message="store.error" @dismiss="store.clearMessages" />
        <ValidationErrors :errors="store.validationErrors" />
        <div v-if="notice" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ notice }}</div>

        <div class="grid gap-6 xl:grid-cols-[340px_1fr]">
            <aside>
                <div class="card">
                    <h2 class="text-lg font-bold">Assigned courses</h2>
                    <p class="mb-4 mt-1 text-sm text-slate-500">Choose one to load its assignments and submissions.</p>
                    <LoadingState v-if="store.loading && !store.lecturerCourses.length" label="Loading courses…" />
                    <p v-else-if="!store.lecturerCourses.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                        No courses have been assigned to you. Contact your school administrator.
                    </p>
                    <div v-else class="space-y-2">
                        <button
                            v-for="course in store.lecturerCourses"
                            :key="course.id"
                            type="button"
                            class="w-full rounded-xl border p-3 text-left transition"
                            :class="activeCourseId === course.id ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100' : 'border-slate-200 hover:bg-slate-50'"
                            :aria-pressed="activeCourseId === course.id"
                            @click="selectCourse(course.id)"
                        >
                            <span class="flex items-center justify-between gap-2">
                                <strong>{{ course.code }}</strong>
                                <span v-if="activeCourseId === course.id" class="badge bg-indigo-100 text-indigo-800">Selected</span>
                            </span>
                            <span class="text-sm text-slate-500">{{ course.title }}</span>
                        </button>
                    </div>
                </div>
            </aside>

            <div class="space-y-6">
                <div v-if="selectedCourse" class="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Selected course</p>
                    <p class="mt-1 text-lg font-black text-slate-900">{{ selectedCourse.code }} · {{ selectedCourse.title }}</p>
                </div>
                <div v-else class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    Select an assigned course to activate the Create Assignment form and load course activity.
                </div>

                <form class="card space-y-4" @submit.prevent="createAssignment">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Create assignment</h2>
                        <span v-if="selectedCourse" class="text-xs text-slate-500">{{ students.length }} enrolled students</span>
                    </div>
                    <fieldset :disabled="!selectedCourse || store.loading" :class="{ 'opacity-50': !selectedCourse }" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block"><span class="label">Title</span><input v-model.trim="assignmentForm.title" class="field" required></label>
                            <label class="block"><span class="label">Maximum score</span><input v-model.number="assignmentForm.maximum_score" class="field" type="number" min="0.01" step="0.01" required></label>
                            <label class="block md:col-span-2"><span class="label">Description</span><textarea v-model="assignmentForm.description" class="field min-h-24" /></label>
                            <label class="block md:col-span-2">
                                <span class="label">Assignment file</span>
                                <input ref="assignmentFileInput" class="field" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip" @change="chooseAssignmentFile">
                                <span v-if="assignmentFile" class="mt-2 block text-xs text-slate-500">Selected: {{ safeFilename(assignmentFile.name) }}</span>
                                <span v-else class="mt-2 block text-xs text-slate-500">Optional · PDF, Office documents, text, or ZIP · maximum 20 MB</span>
                            </label>
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
                        <button class="btn-primary" type="submit">Create assignment</button>
                    </fieldset>
                </form>

                <div v-if="selectedCourse" class="card">
                    <h2 class="mb-4 text-lg font-bold">Assignments</h2>
                    <LoadingState v-if="store.loading && !assignments.length" label="Loading assignments…" />
                    <p v-else-if="!assignments.length" class="text-sm text-slate-500">No assignments in this course.</p>
                    <div v-else class="space-y-3">
                        <article v-for="assignment in assignments" :key="assignment.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold">{{ assignment.title }}</h3>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">{{ assignment.description }}</p>
                                    <button v-if="assignment.attachment" class="mt-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800" type="button" @click="downloadAttachment(assignment)">
                                        Download: {{ safeFilename(assignment.attachment.file_name) }}
                                    </button>
                                </div>
                                <span class="badge" :class="assignment.is_released ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ assignment.is_released ? 'Released' : 'Unreleased' }}
                                </span>
                            </div>
                            <form v-if="editingAssignmentId === assignment.id" class="mt-4 space-y-4 rounded-xl bg-slate-50 p-4" @submit.prevent="saveAssignment(assignment)">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="block"><span class="label">Title</span><input v-model.trim="editForm.title" class="field" required></label>
                                    <label class="block"><span class="label">Maximum score</span><input v-model.number="editForm.maximum_score" class="field" type="number" min="0.01" step="0.01" required></label>
                                    <label class="block md:col-span-2"><span class="label">Description</span><textarea v-model="editForm.description" class="field min-h-24" /></label>
                                    <label class="block"><span class="label">Due date</span><input v-model="editForm.due_at" class="field" type="datetime-local"></label>
                                    <label class="block"><span class="label">Audience</span><select v-model="editForm.target_type" class="field"><option value="all">All enrolled students</option><option value="selected">Selected students</option></select></label>
                                    <label class="block md:col-span-2">
                                        <span class="label">Replace assignment file</span>
                                        <input class="field" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip" @change="chooseEditFile">
                                        <span v-if="editFile" class="mt-2 block text-xs text-slate-500">Selected: {{ safeFilename(editFile.name) }}</span>
                                        <span v-else-if="assignment.attachment" class="mt-2 block text-xs text-slate-500">Current: {{ safeFilename(assignment.attachment.file_name) }}</span>
                                    </label>
                                </div>
                                <fieldset v-if="editForm.target_type === 'selected'" class="rounded-xl border border-slate-200 p-4">
                                    <legend class="px-2 text-sm font-semibold">Select students</legend>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <label v-for="student in students" :key="student.id" class="flex items-center gap-2 text-sm">
                                            <input v-model="editForm.target_student_ids" type="checkbox" :value="student.id">
                                            <span>{{ student.name }}</span>
                                        </label>
                                    </div>
                                </fieldset>
                                <label v-if="assignment.attachment && !editFile" class="flex items-center gap-2 text-sm">
                                    <input v-model="editForm.remove_file" type="checkbox">
                                    <span>Remove the current assignment file</span>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <button class="btn-primary" type="submit" :disabled="store.loading">Save changes</button>
                                    <button class="btn-secondary" type="button" @click="cancelEdit">Cancel</button>
                                </div>
                            </form>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="btn-secondary" type="button" @click="openSubmissions(assignment.id)">View submissions</button>
                                <button class="btn-secondary" type="button" :disabled="assignment.has_submissions" :title="assignment.has_submissions ? 'Assignments with submissions cannot be edited.' : ''" @click="startEdit(assignment)">Edit</button>
                                <button class="btn-secondary text-red-700" type="button" :disabled="assignment.has_submissions" :title="assignment.has_submissions ? 'Assignments with submissions cannot be deleted.' : ''" @click="deleteAssignment(assignment)">Delete</button>
                                <button v-if="!assignment.is_released" class="btn-primary" type="button" @click="release(assignment.id)">Release results</button>
                            </div>
                        </article>
                    </div>
                </div>

                <div v-if="activeAssignmentId" class="card">
                    <h2 class="mb-1 text-lg font-bold">Submissions</h2>
                    <p class="mb-4 text-sm text-slate-500">{{ selectedAssignment?.title }} · {{ selectedCourse?.code }}</p>
                    <LoadingState v-if="store.loading && !submissionsLoaded" label="Loading submissions…" />
                    <p v-else-if="submissionsLoaded && !submissions.length" class="text-sm text-slate-500">No students have submitted yet.</p>
                    <div v-else-if="submissions.length" class="space-y-4">
                        <article v-for="submission in submissions" :key="submission.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <strong>{{ submission.student?.name }}</strong>
                                    <span class="badge" :class="submission.grade_status === 'graded' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                        {{ submission.grade_status === 'graded' ? 'Graded' : 'Not graded' }}
                                    </span>
                                </div>
                                <span class="text-xs text-slate-500">Submitted {{ new Date(submission.submitted_at).toLocaleString() }}</span>
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
        </div>
    </section>
</template>
