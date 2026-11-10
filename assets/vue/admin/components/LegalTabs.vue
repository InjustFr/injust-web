<script setup>
import { useI18n } from 'vue-i18n';
import { LEGAL_KINDS } from '../settingsForm.js';

const { t } = useI18n();
const kind = defineModel({ type: String, required: true });
</script>

<template>
    <div class="legal-tabs" role="tablist" :aria-label="t('admin.legal.kinds')">
        <button v-for="name in LEGAL_KINDS" :key="name" type="button" role="tab" :aria-selected="name === kind" :class="['legal-tabs__tab', { 'legal-tabs__tab--active': name === kind }]" @click="kind = name">
            {{ t(`admin.legal.kind.${name}`) }}
        </button>
    </div>
</template>

<style scoped>
.legal-tabs { display: flex; flex-wrap: wrap; gap: var(--space-1); }
.legal-tabs__tab { padding: var(--space-2) var(--space-3); border: none; border-radius: var(--radius); background: none; color: var(--color-muted); font-weight: 600; cursor: pointer; }
.legal-tabs__tab:hover { color: var(--color-ink); background: var(--color-hover); }
.legal-tabs__tab--active { color: var(--color-accent-strong); background: var(--color-accent-soft); }
</style>
