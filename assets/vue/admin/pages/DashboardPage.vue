<script setup>
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';

const { t } = useI18n();
</script>

<template>
    <PageHeader :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.subtitle')" />
</template>
