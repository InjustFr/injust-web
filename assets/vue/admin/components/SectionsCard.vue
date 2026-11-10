<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { checkoutBlocked } from '../settingsForm.js';
import ToggleRow from './ToggleRow.vue';

const SECTIONS = ['lessons', 'production', 'beats'];

const props = defineProps({ settings: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const checkoutError = ref(null);

function switchSection(name, enabled) {
    return toast.attempt(() => api.put('/api/admin/settings/sections', { ...props.settings.sections, [name]: enabled }), t('admin.settings.saved'));
}

async function switchCheckout(enabled) {
    checkoutError.value = null;
    try {
        await api.put('/api/admin/settings/checkout', { enabled });
    } catch (error) {
        checkoutError.value = error.message;
    }
}
</script>

<template>
    <PageSection :title="t('admin.settings.sections.title')">
        <div>
            <ToggleRow :label="t('admin.settings.sections.music')" :description="t('admin.settings.sections.musicHint')" :model-value="true" disabled />
            <ToggleRow
                v-for="name in SECTIONS"
                :key="name"
                :label="t(`admin.settings.sections.${name}`)"
                :model-value="settings.sections[name]"
                @update:model-value="switchSection(name, $event)"
            />
            <ToggleRow
                :label="t('admin.settings.checkout.label')"
                :description="checkoutBlocked(settings) ? t('admin.settings.checkout.blocked') : t('admin.settings.checkout.hint')"
                :error="checkoutError"
                :model-value="settings.onlineCheckout"
                :disabled="checkoutBlocked(settings)"
                @update:model-value="switchCheckout"
            />
        </div>
    </PageSection>
</template>
