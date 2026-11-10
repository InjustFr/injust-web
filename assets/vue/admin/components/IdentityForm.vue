<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import FormField from '../../components/ui/FormField.vue';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { identityErrors, identityForm, identityPayload } from '../settingsForm.js';

const props = defineProps({ settings: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(identityForm(props.settings.legal));
const errors = ref({});
const saving = ref(false);

watch(() => props.settings.legal, (legal) => Object.assign(form, identityForm(legal)));

const FIELDS = [
    { name: 'name', type: 'text' },
    { name: 'businessForm', type: 'text' },
    { name: 'siren', type: 'text' },
    { name: 'address', type: 'text' },
    { name: 'email', type: 'email' },
    { name: 'phone', type: 'tel' },
    { name: 'publicationDirector', type: 'text' },
    { name: 'mediatorName', type: 'text' },
    { name: 'mediatorUrl', type: 'url' },
    { name: 'hostName', type: 'text' },
    { name: 'hostAddress', type: 'text' },
    { name: 'hostPhone', type: 'tel' },
];

async function save() {
    errors.value = Object.fromEntries(Object.entries(identityErrors(form)).map(([field, key]) => [field, t(key)]));
    if (Object.keys(errors.value).length > 0) return;
    saving.value = true;
    const saved = await toast.attempt(async () => {
        try {
            await api.put('/api/admin/settings/legal-identity', identityPayload(form));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.settings.saved'));
    saving.value = false;
    return saved;
}
</script>

<template>
    <PageSection :title="t('admin.settings.identity.title')">
        <form class="settings-form" @submit.prevent="save">
            <p class="settings-form__hint">{{ t(settings.legal.complete ? 'admin.settings.identity.complete' : 'admin.settings.identity.incomplete') }}</p>
            <FormField v-for="field in FIELDS" :key="field.name" :label="t(`admin.settings.identity.${field.name}`)" :error="errors[field.name] ?? null">
                <input v-model="form[field.name]" :type="field.type" :name="field.name" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.settings.save') }}</BaseButton>
        </form>
    </PageSection>
</template>

<style scoped>
.settings-form { display: flex; flex-direction: column; gap: var(--space-3); }
.settings-form__hint { color: var(--color-muted); font-size: var(--font-size-sm); }
.settings-form > .button { align-self: flex-start; }
</style>
