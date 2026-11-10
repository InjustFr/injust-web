<script setup>
import { Inbox } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import { intlLocale } from '../../i18n/locale.js';
import MessageCard from '../components/messages/MessageCard.vue';
import { groupByDate, visibleMessages } from '../messagesList.js';

const { t } = useI18n();
const messages = ref(null);
const showHandled = ref(false);
await useApi().load('/api/admin/messages?status=all', messages);
const groups = computed(() => groupByDate(visibleMessages(messages.value, showHandled.value)));
const day = (date) => new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'full' }).format(new Date(`${date}T12:00:00`));
</script>

<template>
    <div class="messages-page">
        <PageHeader :title="t('admin.messages.title')" :subtitle="t('admin.messages.subtitle')" />
        <label class="messages-page__toggle">
            <input v-model="showHandled" type="checkbox" name="showHandled" />{{ t('admin.messages.showHandled') }}
        </label>
        <EmptyState v-if="groups.length === 0" :icon="Inbox" :title="t('admin.messages.empty')" />
        <section v-for="group in groups" :key="group.date" class="messages-page__day">
            <h2 class="messages-page__date">{{ day(group.date) }}</h2>
            <MessageCard v-for="message in group.items" :key="message.id" :message="message" />
        </section>
    </div>
</template>

<style scoped>
.messages-page { display: flex; flex-direction: column; gap: var(--space-4); }
.messages-page__toggle { display: inline-flex; align-items: center; gap: var(--space-2); align-self: flex-start; }
.messages-page__day { display: flex; flex-direction: column; gap: var(--space-3); }
.messages-page__date { margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
</style>
