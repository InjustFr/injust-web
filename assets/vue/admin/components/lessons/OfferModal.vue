<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import BaseSelect from '../../../components/ui/BaseSelect.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { FORMATS, KINDS, offerForm, offerPayload, sessionsFor } from '../../lessonsForm.js';

const props = defineProps({ offer: { type: Object, default: null } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(offerForm(null));
const errors = ref({});
const saving = ref(false);
const LANGUAGES = ['fr', 'en'];
const kinds = KINDS.map((value) => ({ value, label: t(`admin.lessons.kinds.${value}`) }));
const formats = FORMATS.map((value) => ({ value, label: t(`admin.lessons.formats.${value}`) }));

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, offerForm(props.offer));
});

watch(() => form.kind, (kind) => { form.sessions = sessionsFor(kind, form.sessions); });

async function save() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            const body = offerPayload(form);
            await (props.offer ? api.put(`/api/admin/lessons/offers/${props.offer.id}`, body) : api.post('/api/admin/lessons/offers', body));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.lessons.saved'));
    saving.value = false;
    if (done) open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="offer ? t('admin.lessons.offers.edit') : t('admin.lessons.offers.new')">
        <form class="offer" @submit.prevent="save">
            <FormField v-for="language in LANGUAGES" :key="`title-${language}`" :label="t('admin.lessons.offers.titleField', { language: language.toUpperCase() })" :error="errors[`title.${language}`] ?? errors.title ?? null">
                <input v-model="form.title[language]" type="text" :name="`title-${language}`" maxlength="200" />
            </FormField>
            <FormField v-for="language in LANGUAGES" :key="`description-${language}`" :label="t('admin.lessons.offers.description', { language: language.toUpperCase() })" :error="errors[`description.${language}`] ?? null">
                <textarea v-model="form.description[language]" :name="`description-${language}`" rows="2" />
            </FormField>
            <FormField :label="t('admin.lessons.offers.kind')" as="div" :error="errors.kind ?? null">
                <BaseSelect v-model="form.kind" :options="kinds" name="kind" />
            </FormField>
            <FormField :label="t('admin.lessons.offers.sessions')" :hint="t('admin.lessons.offers.sessionsHint')" :error="errors.sessions ?? null">
                <input v-model="form.sessions" type="number" name="sessions" min="1" max="100" :disabled="form.kind === 'single'" />
            </FormField>
            <FormField :label="t('admin.lessons.offers.format')" as="div" :error="errors.format ?? null">
                <BaseSelect v-model="form.format" :options="formats" name="format" />
            </FormField>
            <FormField :label="t('admin.lessons.offers.minutes')" :hint="t('admin.lessons.offers.minutesHint')" :error="errors.minutes ?? null">
                <input v-model="form.minutes" type="number" name="minutes" min="15" max="240" step="15" required />
            </FormField>
            <FormField :label="t('admin.lessons.offers.price')" :hint="t('admin.lessons.offers.priceHint')" :error="errors['price.cents'] ?? errors.price ?? null">
                <input v-model="form.price" type="text" inputmode="decimal" name="price" required />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.lessons.save') }}</BaseButton>
        </form>
    </BaseModal>
</template>

<style scoped>
.offer { display: flex; flex-direction: column; gap: var(--space-3); }
.offer > .button { align-self: flex-start; }
</style>
