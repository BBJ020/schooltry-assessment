import assert from 'node:assert/strict';
import test from 'node:test';
import { apiError } from '../../resources/js/services/api.js';
import { safeFilename } from '../../resources/js/utils/filename.js';
import { canAccessRoles, landingRouteForRoles } from '../../resources/js/router/access.js';
import { tenantUserPayload } from '../../resources/js/utils/management.js';
import { readFileSync } from 'node:fs';

test('safeFilename removes paths and control characters', () => {
    assert.equal(safeFilename('../private/report\u0000.pdf'), 'report.pdf');
    assert.equal(safeFilename(''), 'Attachment');
});

test('management route guards separate superadmin, admin, lecturer, and student UX', () => {
    assert.equal(landingRouteForRoles(['superadmin']).name, 'superadmin-dashboard');
    assert.equal(landingRouteForRoles(['admin']).name, 'admin-dashboard');
    assert.equal(canAccessRoles(['admin'], ['superadmin']), false);
    assert.equal(canAccessRoles(['lecturer'], ['admin']), false);
    assert.equal(canAccessRoles(['student'], ['admin']), false);
});

test('management error handling safely normalizes authorization and missing responses', () => {
    assert.equal(apiError({ response: { status: 401 } }).message, 'Your session has expired. Please sign in again.');
    assert.equal(apiError({ response: { status: 403 } }).message, 'You do not have permission to perform this action.');
    assert.equal(apiError({ response: { status: 404 } }).message, 'The requested item is unavailable.');
});

test('tenant user payload cannot submit arbitrary school or password fields', () => {
    assert.deepEqual(tenantUserPayload({ name: 'A', email: 'a@test', role: 'student', school_id: 99, password: 'bad' }), {
        name: 'A', email: 'a@test', role: 'student',
    });
});

test('management screens use escaped rendering and one-time password warning', () => {
    const files = ['AdminDashboard.vue', 'AdminCourseDetail.vue', 'SuperadminDashboard.vue', 'SchoolDetail.vue', '../components/TemporaryPassword.vue'];
    const source = files.map((file) => readFileSync(new URL(`../../resources/js/pages/${file}`, import.meta.url), 'utf8')).join('\n');
    assert.equal(source.includes('v-html'), false);
    assert.equal(source.includes('innerHTML'), false);
    assert.match(source, /Copy this temporary password now\. It will not be shown again\./);
});

test('lecturer workflow requires an administrator-assigned course selection', () => {
    const dashboard = readFileSync(new URL('../../resources/js/pages/LecturerDashboard.vue', import.meta.url), 'utf8');
    const store = readFileSync(new URL('../../resources/js/stores/assignments.js', import.meta.url), 'utf8');

    assert.equal(dashboard.includes('Create course'), false);
    assert.equal(dashboard.includes('store.createCourse'), false);
    assert.match(dashboard, /:disabled="!selectedCourse \|\| store\.loading"/);
    assert.match(dashboard, /Selected course/);
    assert.match(dashboard, /Assignment file/);
    assert.match(dashboard, /new FormData\(\)/);
    assert.match(dashboard, /\.pdf,\.doc,\.docx,\.xls,\.xlsx,\.ppt,\.pptx,\.txt,\.zip/);
    assert.equal(store.includes("api.post('/lecturer/courses'"), false);
});

test('assignment attachment downloads stay behind authenticated API calls', () => {
    const studentPage = readFileSync(new URL('../../resources/js/pages/StudentAssignment.vue', import.meta.url), 'utf8');
    const store = readFileSync(new URL('../../resources/js/stores/assignments.js', import.meta.url), 'utf8');

    assert.match(studentPage, /downloadStudentAssignmentAttachment/);
    assert.match(store, /\/student\/assignments\/\$\{assignmentId\}\/attachment/);
    assert.match(store, /\/lecturer\/assignments\/\$\{assignmentId\}\/attachment/);
    assert.equal(studentPage.includes('file_path'), false);
});

test('lecturer assignment mutations preserve course selection and submission locks', () => {
    const dashboard = readFileSync(new URL('../../resources/js/pages/LecturerDashboard.vue', import.meta.url), 'utf8');
    const store = readFileSync(new URL('../../resources/js/stores/assignments.js', import.meta.url), 'utf8');

    assert.match(dashboard, /window\.confirm/);
    assert.match(dashboard, /assignment\.has_submissions/);
    assert.match(dashboard, /Replace assignment file/);
    assert.match(dashboard, /Remove the current assignment file/);
    assert.match(store, /api\.patch\(`\/lecturer\/assignments\/\$\{assignmentId\}`/);
    assert.match(store, /api\.delete\(`\/lecturer\/assignments\/\$\{assignmentId\}`/);
    assert.equal(dashboard.includes('Create course'), false);
});

test('lecturer submissions distinguish loading from empty and show grade status', () => {
    const dashboard = readFileSync(new URL('../../resources/js/pages/LecturerDashboard.vue', import.meta.url), 'utf8');
    const store = readFileSync(new URL('../../resources/js/stores/assignments.js', import.meta.url), 'utf8');

    assert.match(store, /assignmentSubmissionsLoaded/);
    assert.match(dashboard, /submissionsLoaded && !submissions\.length/);
    assert.match(dashboard, /submission\.grade_status/);
    assert.match(dashboard, /submission\.student\?\.name/);
    assert.match(dashboard, /safeFilename\(submission\.file\.original_filename\)/);
    assert.equal(dashboard.includes('submission.file_path'), false);
});

test('apiError exposes normalized messages and validation fields, not backend debug details', () => {
    const normalized = apiError({
        response: {
            status: 422,
            data: {
                message: 'SQLSTATE secret',
                trace: ['internal stack'],
                errors: { title: ['The title field is required.'] },
            },
        },
    });

    assert.equal(normalized.message, 'Please correct the highlighted fields.');
    assert.deepEqual(normalized.validation, { title: ['The title field is required.'] });
    assert.equal('trace' in normalized, false);
});
