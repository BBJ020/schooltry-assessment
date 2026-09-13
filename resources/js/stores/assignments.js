import { ref } from 'vue';
import { defineStore } from 'pinia';
import api, { apiError } from '../services/api';

const collection = (response) => response.data.data || [];
const resource = (response) => response.data.data;

async function allPages(url) {
    const items = [];
    let next = url;

    while (next) {
        const response = await api.get(next);
        items.push(...collection(response));
        next = response.data.links?.next || null;
    }

    return items;
}

export const useAssignmentsStore = defineStore('assignments', () => {
    const lecturerCourses = ref([]);
    const courseAssignments = ref({});
    const courseStudents = ref({});
    const assignmentSubmissions = ref({});
    const studentAssignments = ref([]);
    const studentSubmissions = ref([]);
    const selectedAssignment = ref(null);
    const grades = ref({});
    const loading = ref(false);
    const error = ref('');
    const validationErrors = ref({});

    function begin() {
        loading.value = true;
        error.value = '';
        validationErrors.value = {};
    }

    function fail(requestError, fallback) {
        const normalized = apiError(requestError, fallback);
        error.value = normalized.message;
        validationErrors.value = normalized.validation;
    }

    async function run(callback, fallback) {
        begin();
        try {
            return await callback();
        } catch (requestError) {
            fail(requestError, fallback);
            throw requestError;
        } finally {
            loading.value = false;
        }
    }

    const loadLecturerCourses = () => run(async () => {
        lecturerCourses.value = await allPages('/lecturer/courses');
    }, 'Unable to load courses.');

    const createCourse = (payload) => run(async () => {
        const course = resource(await api.post('/lecturer/courses', payload));
        lecturerCourses.value.unshift(course);
        return course;
    }, 'Unable to create the course.');

    const loadCourseAssignments = (courseId) => run(async () => {
        courseAssignments.value[courseId] = await allPages(`/lecturer/courses/${courseId}/assignments`);
    }, 'Unable to load assignments.');

    const loadCourseStudents = (courseId) => run(async () => {
        courseStudents.value[courseId] = await allPages(`/lecturer/courses/${courseId}/students`);
    }, 'Unable to load enrolled students.');

    const createAssignment = (courseId, payload) => run(async () => {
        const assignment = resource(await api.post(`/lecturer/courses/${courseId}/assignments`, payload));
        courseAssignments.value[courseId] ||= [];
        courseAssignments.value[courseId].unshift(assignment);
        return assignment;
    }, 'Unable to create the assignment.');

    const loadAssignmentSubmissions = (assignmentId) => run(async () => {
        assignmentSubmissions.value[assignmentId] = await allPages(`/lecturer/assignments/${assignmentId}/submissions`);
    }, 'Unable to load submissions.');

    const gradeSubmission = (submissionId, payload) => run(async () => {
        return resource(await api.post(`/lecturer/submissions/${submissionId}/grade`, payload));
    }, 'Unable to save the grade.');

    const releaseAssignment = (courseId, assignmentId) => run(async () => {
        const assignment = resource(await api.post(`/lecturer/assignments/${assignmentId}/release`, {}));
        const index = (courseAssignments.value[courseId] || []).findIndex((item) => item.id === assignmentId);
        if (index !== -1) courseAssignments.value[courseId][index] = assignment;
        return assignment;
    }, 'Unable to release the results.');

    const loadStudentAssignments = () => run(async () => {
        studentAssignments.value = await allPages('/student/assignments');
    }, 'Unable to load assignments.');

    const loadStudentAssignment = (assignmentId) => run(async () => {
        selectedAssignment.value = resource(await api.get(`/student/assignments/${assignmentId}`));
        return selectedAssignment.value;
    }, 'Unable to load the assignment.');

    const loadStudentSubmissions = () => run(async () => {
        studentSubmissions.value = await allPages('/student/submissions');
    }, 'Unable to load submissions.');

    const submitAssignment = (assignmentId, formData) => run(async () => {
        const submission = resource(await api.post(`/student/assignments/${assignmentId}/submissions`, formData));
        const index = studentSubmissions.value.findIndex((item) => item.id === submission.id);
        if (index === -1) studentSubmissions.value.unshift(submission);
        else studentSubmissions.value[index] = submission;
        return submission;
    }, 'Unable to submit the assignment.');

    async function loadGrade(assignmentId) {
        delete grades.value[assignmentId];
        begin();
        try {
            const grade = resource(await api.get(`/student/assignments/${assignmentId}/grade`));
            grades.value[assignmentId] = grade;
            return grade;
        } catch (requestError) {
            if (requestError.response?.status === 404) return null;
            fail(requestError, 'Unable to load the result.');
            throw requestError;
        } finally {
            loading.value = false;
        }
    }

    function clearMessages() {
        error.value = '';
        validationErrors.value = {};
    }

    return {
        assignmentSubmissions,
        clearMessages,
        courseAssignments,
        courseStudents,
        createAssignment,
        createCourse,
        error,
        gradeSubmission,
        grades,
        lecturerCourses,
        loadAssignmentSubmissions,
        loadCourseAssignments,
        loadCourseStudents,
        loadGrade,
        loadLecturerCourses,
        loadStudentAssignment,
        loadStudentAssignments,
        loadStudentSubmissions,
        loading,
        releaseAssignment,
        selectedAssignment,
        studentAssignments,
        studentSubmissions,
        submitAssignment,
        validationErrors,
    };
});
