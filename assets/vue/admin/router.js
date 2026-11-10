import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';

export const router = createRouter({
    history: createWebHistory('/admin/'),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
});
