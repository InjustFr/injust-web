<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import BaseSelect from '../../../components/ui/BaseSelect.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { KINDS, PRICINGS, serviceForm, servicePayload } from '../../productionForm.js';

const props = defineProps({ service: { type: Object, default: null } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(serviceForm(null));
const errors = ref({});
const saving = ref(false);
const LANGUAGES = ['fr', 'en'];
const kinds = KINDS.map((value) => ({ value, label: t(`admin.production.kinds.${value}`) }));
const pricings = PRICINGS.map((value) => ({ value, label: t(`admin.production.pricings.${value}`) }));

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, serviceForm(props.service));
});

async function save() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            const body = servicePayload(form);
            await (props.service ? api.put(`/api/admin/production/services/${props.service.id}`, body) : api.post('/api/admin/production/services', body));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.production.saved'));
    saving.value = false;
    if (done) open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="service ? t('admin.production.services.edit') : t('admin.production.services.new')">
        <form class="service" @submit.prevent="save">
            <FormField :label="t('admin.production.services.kind')" as="div" :error="errors.kind ?? null">
                <BaseSelect v-model="form.kind" :options="kinds" name="kind" />
            </FormField>
            <FormField v-for="language in LANGUAGES" :key="`title-${language}`" :label="t('admin.production.services.titleField', { language: language.toUpperCase() })" :error="errors[`title.${language}`] ?? errors.title ?? null">
                <input v-model="form.title[language]" type="text" :name="`title-${language}`" maxlength="200" />
            </FormField>
            <FormField v-for="language in LANGUAGES" :key="`description-${language}`" :label="t('admin.production.services.description', { language: language.toUpperCase() })" :error="errors[`description.${language}`] ?? null">
                <textarea v-model="form.description[language]" :name="`description-${language}`" rows="2" />
            </FormField>
            <FormField :label="t('admin.production.services.pricing')" as="div" :error="errors.pricing ?? null">
                <BaseSelect v-model="form.pricing" :options="pricings" name="pricing" />
            </FormField>
            <FormField :label="form.pricing === 'fixed' ? t('admin.production.services.price') : t('admin.production.services.priceFrom')" :hint="form.pricing === 'fixed' ? t('admin.production.services.priceHint') : t('admin.production.services.priceFromHint')" :error="errors['price.cents'] ?? errors.price ?? null">
                <input v-model="form.price" type="text" inputmode="decimal" name="price" :required="form.pricing === 'fixed'" />
            </FormField>
            <FormField :label="t('admin.production.services.deliveryDays')" :hint="t('admin.production.services.deliveryDaysHint')" :error="errors.deliveryDays ?? null">
                <input v-model="form.deliveryDays" type="number" name="deliveryDays" min="1" max="120" />
            </FormField>
            <FormField :label="t('admin.production.services.revisions')" :hint="t('admin.production.services.revisionsHint')" :error="errors.revisions ?? null">
                <input v-model="form.revisions" type="number" name="revisions" min="0" max="10" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.production.save') }}</BaseButton>
        </form>
    </BaseModal>
</template>

<style scoped>
.service { display: flex; flex-direction: column; gap: var(--space-3); }
.service > .button { align-self: flex-start; }
</style>
