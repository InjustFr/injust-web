<script setup>
import { CircleCheck, CircleDashed, Inbox } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import { intlLocale } from '../../i18n/locale.js';
import { launchChecklist, latestMessages, sectionRows } from '../dashboard.js';

const { t } = useI18n();
const api = useApi();
const settings = ref(null);
const music = ref(null);
const lessons = ref(null);
const production = ref(null);
const beats = ref(null);
const messages = ref(null);
await Promise.all([
    api.load('/api/admin/settings', settings),
    api.load('/api/admin/music', music),
    api.load('/api/admin/lessons', lessons),
    api.load('/api/admin/production', production),
    api.load('/api/admin/beats', beats),
    api.load('/api/admin/messages', messages),
]);

const site = computed(() => ({ settings: settings.value, music: music.value, lessons: lessons.value, production: production.value, beats: beats.value }));
const checklist = computed(() => launchChecklist(site.value));
const doneCount = computed(() => checklist.value.filter((item) => item.done).length);
const sections = computed(() => sectionRows(site.value));
const inbox = computed(() => latestMessages(messages.value));
const openCount = computed(() => messages.value.filter((message) => !message.handled).length);
const received = (date) => new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
</script>

<template>
    <div class="dashboard">
        <PageHeader :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.subtitle')" />

        <PageSection :title="t('admin.dashboard.inbox.title')" :count="openCount">
            <template #actions>
                <RouterLink class="dashboard__more" :to="{ name: 'messages' }">{{ t('admin.dashboard.inbox.all') }}</RouterLink>
            </template>
            <EmptyState v-if="inbox.length === 0" :icon="Inbox" :title="t('admin.dashboard.inbox.empty')" />
            <ul v-else class="dashboard__list">
                <li v-for="message in inbox" :key="message.id">
                    <RouterLink class="dashboard__message" :to="{ name: 'messages' }">
                        <span class="dashboard__name">{{ message.name }}</span>
                        <span class="dashboard__topic">{{ message.offerLabel ?? t(`admin.dashboard.topics.${message.topic}`) }}</span>
                        <span class="dashboard__date tabular">{{ received(message.receivedAt) }}</span>
                    </RouterLink>
                </li>
            </ul>
        </PageSection>

        <PageSection :title="t('admin.dashboard.sections.title')">
            <ul class="dashboard__list">
                <li v-for="row in sections" :key="row.key" class="dashboard__row">
                    <component :is="row.to ? 'RouterLink' : 'span'" :to="row.to" class="dashboard__label">{{ t(`admin.dashboard.sections.${row.key}`) }}</component>
                    <span v-if="row.count !== null" class="dashboard__count tabular">{{ t('admin.dashboard.sections.items', { count: row.count }, row.count) }}</span>
                    <span class="dashboard__state" :data-on="row.enabled">{{ row.enabled ? t('admin.dashboard.sections.on') : t('admin.dashboard.sections.off') }}</span>
                </li>
            </ul>
        </PageSection>

        <PageSection :title="t('admin.dashboard.checklist.title')">
            <template #actions>
                <span class="dashboard__progress tabular">{{ t('admin.dashboard.checklist.progress', { done: doneCount, total: checklist.length }) }}</span>
            </template>
            <ul class="dashboard__list">
                <li v-for="item in checklist" :key="item.key">
                    <RouterLink class="dashboard__step" :data-done="item.done" :to="item.to">
                        <component :is="item.done ? CircleCheck : CircleDashed" :size="18" aria-hidden="true" />
                        <span>{{ t(`admin.dashboard.checklist.${item.key}`) }}</span>
                        <span class="visually-hidden">{{ item.done ? t('admin.dashboard.checklist.done') : t('admin.dashboard.checklist.todo') }}</span>
                    </RouterLink>
                </li>
            </ul>
        </PageSection>
    </div>
</template>

<style scoped>
.dashboard { display: flex; flex-direction: column; gap: var(--space-7); }
.dashboard__list { display: flex; flex-direction: column; margin: 0; padding: 0; border-top: 0.0625rem solid var(--color-border); list-style: none; }
.dashboard__list > li { border-bottom: 0.0625rem solid var(--color-border); }
.dashboard__more, .dashboard__progress { color: var(--color-muted); font-size: var(--font-size-sm); }
.dashboard__message, .dashboard__row, .dashboard__step { display: flex; align-items: center; gap: var(--space-3); min-height: 3rem; padding: var(--space-2) 0; color: var(--color-text); text-decoration: none; }
.dashboard__message:hover .dashboard__name, .dashboard__step:hover span, a.dashboard__label:hover { color: var(--color-accent-strong); }
.dashboard__name, .dashboard__label { color: var(--color-ink); font-weight: 600; text-decoration: none; }
.dashboard__topic { color: var(--color-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dashboard__date, .dashboard__count { margin-left: auto; color: var(--color-muted); font-size: var(--font-size-sm); white-space: nowrap; }
.dashboard__count + .dashboard__state { margin-left: 0; }
.dashboard__state { min-width: 3rem; margin-left: auto; padding: var(--space-1) var(--space-2); border: 0.0625rem solid var(--color-border-strong); color: var(--color-muted); font-family: var(--font-mono); font-size: var(--font-size-xs); text-align: center; text-transform: uppercase; }
.dashboard__state[data-on="true"] { border-color: var(--color-accent); color: var(--color-accent-strong); }
.dashboard__step { color: var(--color-muted); }
.dashboard__step[data-done="false"] { color: var(--color-ink); }
.dashboard__step[data-done="true"] span:first-of-type { text-decoration: line-through; }
.dashboard__step[data-done="true"] svg { color: var(--color-accent-strong); }
</style>
