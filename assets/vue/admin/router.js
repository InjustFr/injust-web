import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import LessonsPage from './pages/LessonsPage.vue';
import LegalPage from './pages/LegalPage.vue';
import LinksPage from './pages/LinksPage.vue';
import SeoPage from './pages/SeoPage.vue';
import MessagesPage from './pages/MessagesPage.vue';
import OrdersPage from './pages/OrdersPage.vue';
import MusicPage from './pages/MusicPage.vue';
import ProductionPage from './pages/ProductionPage.vue';
import NotFoundPage from './pages/NotFoundPage.vue';
import SettingsPage from './pages/SettingsPage.vue';

export const router = createRouter({
    history: createWebHistory('/admin/'),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardPage },
        { path: '/music', name: 'music', component: MusicPage },
        { path: '/lessons', name: 'lessons', component: LessonsPage },
        { path: '/production', name: 'production', component: ProductionPage },
        { path: '/links', name: 'links', component: LinksPage },
        { path: '/seo', name: 'seo', component: SeoPage },
        { path: '/messages', name: 'messages', component: MessagesPage },
        { path: '/orders', name: 'orders', component: OrdersPage, meta: { wide: true } },
        { path: '/settings', name: 'settings', component: SettingsPage },
        { path: '/legal', name: 'legal', component: LegalPage },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
});
