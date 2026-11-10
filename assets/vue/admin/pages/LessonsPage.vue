<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import OffersPanel from '../components/lessons/OffersPanel.vue';
import PageTextsPanel from '../components/lessons/PageTextsPanel.vue';

const { t } = useI18n();
const lessons = ref(null);
await useApi().load('/api/admin/lessons', lessons);
</script>

<template>
    <div class="lessons-page">
        <PageHeader :title="t('admin.lessons.title')" :subtitle="t('admin.lessons.subtitle')" />
        <OffersPanel :offers="lessons.offers" />
        <PageTextsPanel :page="lessons.page" />
    </div>
</template>

<style scoped>
.lessons-page { display: flex; flex-direction: column; gap: var(--space-6); }
</style>
