import { createRouter, createWebHistory } from 'vue-router';
import AgentDashboard from '@/views/AgentDashboard.vue';
import AdminDashboard from '@/views/AdminDashboard.vue';
import LoginView from '@/views/LoginView.vue';

const routes = [
    {
        path: '/',
        name: 'agent-dashboard',
        component: AgentDashboard,
        meta: { requiresAuth: true },
    },
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: AdminDashboard,
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/login',
        name: 'login',
        component: LoginView,
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('agent_token');
    const userRaw = localStorage.getItem('agent_user');
    let user = null;
    try {
        user = userRaw ? JSON.parse(userRaw) : null;
    } catch (_) {
        user = null;
    }
    const isAdmin = user?.role === 'admin';

    if (to.meta.requiresAuth && !token) {
        return next('/login');
    }

    if (to.name === 'login' && token) {
        return next(isAdmin ? '/admin' : '/');
    }

    if (to.meta.requiresAdmin && !isAdmin) {
        return next('/');
    }

    next();
});

export default router;
