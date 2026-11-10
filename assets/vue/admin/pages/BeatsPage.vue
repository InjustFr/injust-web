<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import BeatsPanel from '../components/beats/BeatsPanel.vue';
import PageTextsPanel from '../components/beats/PageTextsPanel.vue';
import TiersPanel from '../components/beats/TiersPanel.vue';

const { t } = useI18n();
const beats = ref(null);
await useApi().load('/api/admin/beats', beats);
</script>

<template>
    <div class="beats-page">
        <PageHeader :title="t('admin.beats.title')" :subtitle="t('admin.beats.subtitle')" />
        <BeatsPanel :beats="beats.beats" />
        <TiersPanel :tiers="beats.tiers" />
        <PageTextsPanel :page="beats.page" />
    </div>
</template>

<style scoped>
.beats-page { display: flex; flex-direction: column; gap: var(--space-6); }
</style>
