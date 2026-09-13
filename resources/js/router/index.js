import { createRouter, createWebHistory } from 'vue-router';

import StudentDashboard from '../pages/StudentDashboard.vue';
import LecturerDashboard from '../pages/LecturerDashboard.vue';
import Results from '../pages/Results.vue';

const routes = [
    {
        path: '/',
        redirect: '/student',
    },
    {
        path: '/student',
        name: 'student',
        component: StudentDashboard,
    },
    {
        path: '/lecturer',
        name: 'lecturer',
        component: LecturerDashboard,
    },
    {
        path: '/results',
        name: 'results',
        component: Results,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;