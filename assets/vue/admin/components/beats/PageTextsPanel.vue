<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import FormField from '../../../components/ui/FormField.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { pageForm, pagePayload } from '../../beatsForm.js';

const props = defineProps({ page: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(pageForm(props.page));
const errors = ref({});
const saving = ref(false);
const FIELDS = ['intro', 'faq'];
const LANGUAGES = ['fr', 'en'];

watch(() => props.page, (page) => Object.assign(form, pageForm(page)));

async function save() {
    errors.value = {};
    saving.value = true;
    await toast.attempt(async () => {
        try {
            await api.put('/api/admin/beats/page', pagePayload(form));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.beats.saved'));
    saving.value = false;
}
</script>

<template>
    <PageSection :title="t('admin.beats.texts.title')">
        <form class="texts" @submit.prevent="save">
            <template v-for="field in FIELDS" :key="field">
                <FormField v-for="language in LANGUAGES" :key="language" :label="t(`admin.beats.texts.${field}`, { language: language.toUpperCase() })" :error="errors[`${field}.${language}`] ?? null">
                    <textarea v-model="form[field][language]" :name="`${field}-${language}`" rows="6" />
                </FormField>
            </template>
            <BaseButton type="submit" :loading="saving">{{ t('admin.beats.save') }}</BaseButton>
        </form>
    </PageSection>
</template>

<style scoped>
.texts { display: flex; flex-direction: column; gap: var(--space-3); }
.texts > .button { align-self: flex-start; }
</style>
