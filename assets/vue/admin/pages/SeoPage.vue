<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import SeoCard from '../components/seo/SeoCard.vue';

const { t } = useI18n();
const seo = ref(null);
await useApi().load('/api/admin/seo', seo);
</script>

<template>
    <div class="seo-page">
        <PageHeader :title="t('admin.seo.title')" :subtitle="t('admin.seo.subtitle')" />
        <PageSection :title="t('admin.seo.pages.home')">
            <p class="seo-page__home">{{ t('admin.seo.homeHint') }}</p>
            <p v-if="seo.home.title.en || seo.home.title.fr" class="seo-page__home"><strong>{{ seo.home.title.en || seo.home.title.fr }}</strong></p>
            <BaseButton href="/admin/settings" variant="secondary">{{ t('admin.seo.editHome') }}</BaseButton>
        </PageSection>
        <SeoCard v-for="page in seo.pages" :key="page.key" :page="page" />
    </div>
</template>

<style scoped>
.seo-page { display: flex; flex-direction: column; gap: var(--space-5); }
.seo-page__home { margin: 0 0 var(--space-2); color: var(--color-muted); }
</style>
