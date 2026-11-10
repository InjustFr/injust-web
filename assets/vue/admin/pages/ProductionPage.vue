<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import PageTextsPanel from '../components/production/PageTextsPanel.vue';
import PortfolioPanel from '../components/production/PortfolioPanel.vue';
import ServicesPanel from '../components/production/ServicesPanel.vue';

const { t } = useI18n();
const production = ref(null);
await useApi().load('/api/admin/production', production);
</script>

<template>
    <div class="production-page">
        <PageHeader :title="t('admin.production.title')" :subtitle="t('admin.production.subtitle')" />
        <ServicesPanel :services="production.services" />
        <PortfolioPanel :items="production.portfolio" />
        <PageTextsPanel :page="production.page" />
    </div>
</template>

<style scoped>
.production-page { display: flex; flex-direction: column; gap: var(--space-6); }
</style>
