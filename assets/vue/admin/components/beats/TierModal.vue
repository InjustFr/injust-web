<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { CAPS, DELIVERABLES, tierForm, tierPayload } from '../../beatsForm.js';
import ToggleRow from '../ToggleRow.vue';

const props = defineProps({ tier: { type: Object, default: null } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(tierForm(null));
const errors = ref({});
const saving = ref(false);
const LANGUAGES = ['fr', 'en'];

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, tierForm(props.tier));
});

async function save() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            const body = tierPayload(form);
            await (props.tier ? api.put(`/api/admin/beats/tiers/${props.tier.id}`, body) : api.post('/api/admin/beats/tiers', body));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.beats.saved'));
    saving.value = false;
    if (done) open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="tier ? t('admin.beats.tiers.edit') : t('admin.beats.tiers.new')">
        <form class="tier" @submit.prevent="save">
            <FormField v-for="language in LANGUAGES" :key="`name-${language}`" :label="t('admin.beats.tiers.name', { language: language.toUpperCase() })" :error="errors[`name.${language}`] ?? errors.name ?? null">
                <input v-model="form.name[language]" type="text" :name="`name-${language}`" maxlength="120" />
            </FormField>
            <FormField :label="t('admin.beats.tiers.price')" :hint="t('admin.beats.tiers.priceHint')" :error="errors['price.cents'] ?? errors.price ?? null">
                <input v-model="form.price" type="text" inputmode="decimal" name="price" required />
            </FormField>
            <fieldset class="tier__deliverables">
                <legend>{{ t('admin.beats.tiers.deliverables') }}</legend>
                <label v-for="deliverable in DELIVERABLES" :key="deliverable" class="tier__deliverable">
                    <input v-model="form.deliverables" type="checkbox" name="deliverables" :value="deliverable" />
                    {{ t(`admin.beats.deliverables.${deliverable}`) }}
                </label>
                <p v-if="errors.deliverables" class="tier__error" role="alert">{{ errors.deliverables }}</p>
            </fieldset>
            <ToggleRow v-model="form.exclusive" :label="t('admin.beats.tiers.exclusive')" :description="t('admin.beats.tiers.exclusiveHint')" :error="errors.exclusive ?? null" />
            <div class="tier__caps">
                <FormField v-for="cap in CAPS" :key="cap" :label="t(`admin.beats.tiers.${cap}`)" :hint="t('admin.beats.tiers.capsHint')" :error="errors[cap] ?? null">
                    <input v-model="form[cap]" type="number" :name="cap" min="1" />
                </FormField>
            </div>
            <FormField v-for="language in LANGUAGES" :key="`summary-${language}`" :label="t('admin.beats.tiers.summary', { language: language.toUpperCase() })" :error="errors[`summary.${language}`] ?? null">
                <textarea v-model="form.summary[language]" :name="`summary-${language}`" rows="4" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.beats.save') }}</BaseButton>
        </form>
    </BaseModal>
</template>

<style scoped>
.tier { display: flex; flex-direction: column; gap: var(--space-3); }
.tier > .button { align-self: flex-start; }
.tier__deliverables { display: flex; flex-wrap: wrap; gap: var(--space-2) var(--space-4); border: 0; padding: 0; }
.tier__deliverables legend { margin-bottom: var(--space-2); font-weight: 600; }
.tier__deliverable { display: inline-flex; align-items: center; gap: var(--space-2); }
.tier__error { width: 100%; margin: 0; color: var(--color-danger); }
.tier__caps { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: var(--space-3); }
</style>
