<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import FormField from '../../components/ui/FormField.vue';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { generalForm, generalPayload } from '../settingsForm.js';
import ToggleRow from './ToggleRow.vue';

const props = defineProps({ settings: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(generalForm(props.settings));
const errors = ref({});
const saving = ref(false);

watch(() => props.settings, (settings) => Object.assign(form, generalForm(settings)));

const VAT = ['consumer', 'euBusiness', 'nonEuBusiness'];
const LANGUAGES = ['fr', 'en'];

async function save() {
    errors.value = {};
    saving.value = true;
    await toast.attempt(async () => {
        try {
            await api.put('/api/admin/settings/general', generalPayload(form));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.settings.saved'));
    saving.value = false;
}
</script>

<template>
    <PageSection :title="t('admin.settings.general.title')">
        <form class="settings-form" @submit.prevent="save">
            <FormField :label="t('admin.settings.general.contactEmail')" :error="errors.contactEmail ?? null">
                <input v-model="form.contactEmail" type="email" name="contactEmail" />
            </FormField>
            <FormField :label="t('admin.settings.general.bookingUrl')" :hint="t('admin.settings.general.bookingUrlHint')" :error="errors.bookingUrl ?? null">
                <input v-model="form.bookingUrl" type="url" name="bookingUrl" />
            </FormField>
            <FormField v-for="kind in VAT" :key="kind" :label="t(`admin.settings.general.vat.${kind}`)" :error="errors[`vatMentions.${kind}`] ?? null">
                <input v-model="form.vatMentions[kind]" type="text" :name="`vat-${kind}`" />
            </FormField>
            <template v-for="language in LANGUAGES" :key="language">
                <FormField :label="t('admin.settings.general.seoTitle', { language: language.toUpperCase() })">
                    <input v-model="form.seoTitle[language]" type="text" :name="`seoTitle-${language}`" />
                </FormField>
                <FormField :label="t('admin.settings.general.seoDescription', { language: language.toUpperCase() })">
                    <textarea v-model="form.seoDescription[language]" :name="`seoDescription-${language}`" />
                </FormField>
            </template>
            <ToggleRow v-model="form.searchIndexing" :label="t('admin.settings.general.searchIndexing')" :description="t('admin.settings.general.searchIndexingHint')" />
            <BaseButton type="submit" :loading="saving">{{ t('admin.settings.save') }}</BaseButton>
        </form>
    </PageSection>
</template>

<style scoped>
.settings-form { display: flex; flex-direction: column; gap: var(--space-3); }
.settings-form > .button { align-self: flex-start; }
</style>
