<script setup>
import { ExternalLink, FileText, LayoutDashboard, LogOut, Settings } from '@lucide/vue';
import { ConfigProvider, TooltipProvider } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import ToastHost from '../components/ui/ToastHost.vue';
import { useSession } from '../composables/useSession.js';
import { intlLocale } from '../i18n/locale.js';

const { t } = useI18n();
const session = useSession();

const SECTIONS = [
    { name: 'dashboard', icon: LayoutDashboard },
    { name: 'settings', icon: Settings },
    { name: 'legal', icon: FileText },
];
</script>

<template>
    <ConfigProvider :locale="intlLocale()">
        <TooltipProvider :delay-duration="300">
        <div class="admin">
            <header class="admin__bar">
                <a class="admin__brand" href="/" target="_blank" rel="noopener">Injust</a>
                <nav class="admin__nav" :aria-label="t('admin.nav.label')">
                    <RouterLink v-for="section in SECTIONS" :key="section.name" :to="{ name: section.name }" class="admin__tab">
                        <component :is="section.icon" size="1rem" aria-hidden="true" />
                        {{ t(`admin.nav.${section.name}`) }}
                    </RouterLink>
                </nav>
                <div class="admin__account">
                    <a class="admin__site" href="/" target="_blank" rel="noopener">
                        <ExternalLink size="0.9rem" aria-hidden="true" />{{ t('admin.nav.site') }}
                    </a>
                    <form method="post" action="/logout">
                        <input type="hidden" name="_csrf_token" :value="session.logoutToken" />
                        <button class="admin__sign-out" type="submit" :title="session.email">
                            <LogOut size="0.9rem" aria-hidden="true" />{{ t('admin.nav.signOut') }}
                        </button>
                    </form>
                </div>
            </header>
            <main id="main" class="admin__main">
                <RouterView v-slot="{ Component }">
                    <Suspense><component :is="Component" /></Suspense>
                </RouterView>
            </main>
        </div>
        <ToastHost />
        </TooltipProvider>
    </ConfigProvider>
</template>

<style scoped>
.admin { min-height: 100vh; }

.admin__bar {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2) var(--space-5);
    padding: var(--space-3) var(--space-5);
    border-bottom: 0.0625rem solid var(--color-border);
    background: var(--color-surface);
}

.admin__brand {
    color: var(--color-ink);
    font-family: var(--font-display);
    font-size: 1.25rem;
    text-decoration: none;
}

.admin__nav { display: flex; gap: var(--space-1); }

.admin__tab {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    border-radius: var(--radius);
    color: var(--color-muted);
    font-weight: 600;
    font-size: var(--font-size-md);
    text-decoration: none;
}

.admin__tab:hover { color: var(--color-ink); background: var(--color-hover); }
.admin__tab.router-link-active { color: var(--color-accent-strong); background: var(--color-accent-soft); }

.admin__account { display: flex; align-items: center; gap: var(--space-4); margin-left: auto; }

.admin__site, .admin__sign-out {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    padding: 0;
    border: none;
    background: none;
    color: var(--color-muted);
    font-size: var(--font-size-md);
    text-decoration: none;
    cursor: pointer;
}

.admin__site:hover, .admin__sign-out:hover { color: var(--color-ink); }

.admin__main {
    width: 100%;
    max-width: 52rem;
    margin-inline: auto;
    padding: var(--space-6) var(--space-5) var(--space-7);
}

@media (max-width: 40rem) {
    .admin__bar { padding-inline: var(--space-4); }
    .admin__nav { order: 3; width: 100%; }
    .admin__tab { flex: 1; justify-content: center; }
    .admin__main { padding-inline: var(--space-4); }
}
</style>
