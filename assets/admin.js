import { createApp } from 'vue';
import './styles/fonts.css';
import './styles/tokens.css';
import './styles/base.css';
import AdminShell from './vue/admin/AdminShell.vue';
import { router } from './vue/admin/router.js';
import { i18n } from './vue/i18n/index.js';

createApp(AdminShell).use(i18n).use(router).mount('#app');
