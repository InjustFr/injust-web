<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import GeneralForm from '../components/GeneralForm.vue';
import IdentityForm from '../components/IdentityForm.vue';
import ImagesCard from '../components/ImagesCard.vue';
import SectionsCard from '../components/SectionsCard.vue';

const { t } = useI18n();
const settings = ref(null);
await useApi().load('/api/admin/settings', settings);
</script>

<template>
    <div class="settings-page">
        <PageHeader :title="t('admin.settings.title')" :subtitle="t('admin.settings.subtitle')" />
        <SectionsCard :settings="settings" />
        <IdentityForm :settings="settings" />
        <GeneralForm :settings="settings" />
        <ImagesCard :settings="settings" />
    </div>
</template>

<style scoped>
.settings-page { display: flex; flex-direction: column; gap: var(--space-7); }
</style>
