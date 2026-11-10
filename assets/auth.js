import { createApp } from 'vue';
import './styles/fonts.css';
import './styles/tokens.css';
import './styles/base.css';
import LoginPage from './vue/pages/auth/LoginPage.vue';
import { i18n } from './vue/i18n/index.js';

const PAGES = { LoginPage };

const root = document.getElementById('auth');
createApp(PAGES[root.dataset.component], JSON.parse(root.dataset.props)).use(i18n).mount(root);
