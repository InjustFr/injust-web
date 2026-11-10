<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import BaseSelect from '../../../components/ui/BaseSelect.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { linkForm, linkPayload } from '../../linksForm.js';
import { NETWORKS } from '../../musicForm.js';
import ToggleRow from '../ToggleRow.vue';

const props = defineProps({ link: { type: Object, default: null } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(linkForm(null));
const errors = ref({});
const saving = ref(false);
const LANGUAGES = ['fr', 'en'];
const icons = [{ value: '', label: t('admin.links.noIcon') }, ...NETWORKS.map((value) => ({ value, label: t(`admin.music.networks.${value}`) }))];

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, linkForm(props.link));
});

async function save() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            const body = linkPayload(form);
            await (props.link ? api.put(`/api/admin/links/${props.link.id}`, body) : api.post('/api/admin/links', body));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.links.saved'));
    saving.value = false;
    if (done) open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="link ? t('admin.links.edit') : t('admin.links.new')">
        <form class="link-form" @submit.prevent="save">
            <FormField v-for="language in LANGUAGES" :key="language" :label="t('admin.links.label', { language: language.toUpperCase() })" :error="errors[`label.${language}`] ?? errors.label ?? null">
                <input v-model="form.label[language]" type="text" :name="`label-${language}`" maxlength="80" />
            </FormField>
            <FormField :label="t('admin.links.url')" :hint="t('admin.links.urlHint')" :error="errors.url ?? null">
                <input v-model="form.url" type="url" name="url" maxlength="255" required />
            </FormField>
            <FormField :label="t('admin.links.icon')" as="div" :error="errors.icon ?? null">
                <BaseSelect v-model="form.icon" :options="icons" name="icon" />
            </FormField>
            <ToggleRow v-model="form.highlighted" :label="t('admin.links.highlighted')" :description="t('admin.links.highlightedHint')" />
            <ToggleRow v-model="form.visible" :label="t('admin.links.visible')" />
            <BaseButton type="submit" :loading="saving">{{ t('admin.links.save') }}</BaseButton>
        </form>
    </BaseModal>
</template>

<style scoped>
.link-form { display: flex; flex-direction: column; gap: var(--space-3); }
.link-form > .button { align-self: flex-start; }
</style>
