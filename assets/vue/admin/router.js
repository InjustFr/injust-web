import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import LegalPage from './pages/LegalPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import SettingsPage from './pages/SettingsPage.vue';

export const router = createRouter({
    history: createWebHistory('/admin/'),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/settings', name: 'settings', component: SettingsPage },
        { path: '/legal', name: 'legal', component: LegalPage },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
});
