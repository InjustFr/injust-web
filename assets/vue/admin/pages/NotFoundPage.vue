<script setup>
import { SearchX } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const { t } = useI18n();
</script>

<template>
    <EmptyState :icon="SearchX" :title="t('notFound.title')" :hint="t('notFound.hint')">
        <BaseButton href="/admin/">{{ t('notFound.back') }}</BaseButton>
    </EmptyState>
</template>
