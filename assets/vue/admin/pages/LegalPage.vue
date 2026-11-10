<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import FormField from '../../components/ui/FormField.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage, intlLocale } from '../../i18n/locale.js';
import LegalTabs from '../components/LegalTabs.vue';
import { formatDay, LEGAL_KINDS, legalDraft, legalDraftChanged, legalPublicPath } from '../settingsForm.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const documents = ref([]);
await api.load('/api/admin/legal', documents);

const kind = ref(LEGAL_KINDS[0]);
const current = computed(() => documents.value.find((document) => document.kind === kind.value));
const draft = reactive(legalDraft(current.value));
const saving = ref(false);
const changed = computed(() => legalDraftChanged(current.value, draft));

watch(current, (document) => Object.assign(draft, legalDraft(document)));

async function save() {
    saving.value = true;
    await toast.attempt(() => api.put(`/api/admin/legal/${kind.value}`, { body: { fr: draft.fr, en: draft.en } }), t('admin.legal.saved'));
    saving.value = false;
}
</script>

<template>
    <div class="legal-page">
        <PageHeader :title="t('admin.legal.title')" :subtitle="t('admin.legal.subtitle')" />
        <LegalTabs v-model="kind" />
        <form v-if="current" class="legal-page__form" @submit.prevent="save">
            <p class="legal-page__meta">
                {{ t('admin.legal.version', { version: current.version }) }}
                · {{ t('admin.legal.updated', { date: formatDay(current.updatedAt, intlLocale()) }) }}
                · <a :href="legalPublicPath(kind, currentLanguage())" target="_blank" rel="noopener">{{ t('admin.legal.viewPublic') }}</a>
            </p>
            <p class="legal-page__meta">{{ t('admin.legal.placeholders') }}</p>
            <FormField :label="t('admin.legal.french')">
                <textarea v-model="draft.fr" name="body-fr" rows="16" />
            </FormField>
            <FormField :label="t('admin.legal.english')">
                <textarea v-model="draft.en" name="body-en" rows="16" />
            </FormField>
            <BaseButton type="submit" :loading="saving" :disabled="!changed">{{ t('admin.legal.save') }}</BaseButton>
        </form>
        <p v-else class="legal-page__meta">{{ t('admin.legal.missing') }}</p>
    </div>
</template>

<style scoped>
.legal-page { display: flex; flex-direction: column; gap: var(--space-5); }
.legal-page__form { display: flex; flex-direction: column; gap: var(--space-3); }
.legal-page__form > .button { align-self: flex-start; }
.legal-page__meta { color: var(--color-muted); font-size: var(--font-size-sm); }
.legal-page__form :deep(textarea) { font-family: var(--font-mono); }
</style>
