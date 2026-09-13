import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import Login from '../pages/Login.vue';
import LecturerDashboard from '../pages/LecturerDashboard.vue';
import StudentDashboard from '../pages/StudentDashboard.vue';
import StudentAssignment from '../pages/StudentAssignment.vue';
import Results from '../pages/Results.vue';
import AdminDashboard from '../pages/AdminDashboard.vue';
import NotFound from '../pages/NotFound.vue';

const routes = [
    { path: '/', name: 'home', component: { template: '<div />' } },
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    {
        path: '/lecturer',
        name: 'lecturer-dashboard',
        component: LecturerDashboard,
        meta: { requiresAuth: true, roles: ['lecturer'] },
    },
    {
        path: '/student',
        name: 'student-dashboard',
        component: StudentDashboard,
        meta: { requiresAuth: true, roles: ['student'] },
    },
    {
        path: '/student/assignments/:assignmentId',
        name: 'student-assignment',
        component: StudentAssignment,
        meta: { requiresAuth: true, roles: ['student'] },
    },
    {
        path: '/student/results/:assignmentId',
        name: 'student-result',
        component: Results,
        meta: { requiresAuth: true, roles: ['student'] },
    },
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: AdminDashboard,
        meta: { requiresAuth: true, roles: ['admin'] },
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFound },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.hydrate();

    if (to.name === 'home') return auth.isAuthenticated ? auth.landingRoute : { name: 'login' };
    if (to.meta.guest && auth.isAuthenticated) return auth.landingRoute;
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.roles?.length && !to.meta.roles.some((role) => auth.hasRole(role))) {
        return auth.landingRoute;
    }

    return true;
});

export default router;
