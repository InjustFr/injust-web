<script setup>
import { Link, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseSelect from '../../../components/ui/BaseSelect.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import FormField from '../../../components/ui/FormField.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { moveId } from '../../forms.js';
import { NETWORKS } from '../../musicForm.js';
import OrderButtons from './OrderButtons.vue';

const props = defineProps({ links: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const blank = () => ({ network: 'instagram', url: '', label: '' });
const form = ref(blank());
const errors = ref({});
const saving = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/music/links/order', { ids: moveId(props.links.map((item) => item.id), id, delta) }));
const options = NETWORKS.map((value) => ({ value, label: t(`admin.music.networks.${value}`) }));

async function add() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            await api.post('/api/admin/music/links', { network: form.value.network, url: form.value.url.trim(), label: form.value.label.trim() || null });
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.music.saved'));
    if (done) form.value = blank();
    saving.value = false;
}

const remove = (id) => toast.attempt(() => api.del(`/api/admin/music/links/${id}`), t('admin.music.saved'));
</script>

<template>
    <PageSection :title="t('admin.music.tabs.links')" :count="links.length">
        <form class="row-form" @submit.prevent="add">
            <FormField :label="t('admin.music.links.network')" as="div">
                <BaseSelect v-model="form.network" :options="options" name="network" />
            </FormField>
            <FormField :label="t('admin.music.links.url')" :error="errors.url ?? null">
                <input v-model="form.url" type="url" name="linkUrl" required />
            </FormField>
            <FormField :label="t('admin.music.links.label')" :error="errors.label ?? null">
                <input v-model="form.label" type="text" name="linkLabel" maxlength="60" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.music.add') }}</BaseButton>
        </form>
        <EmptyState :icon="Link" v-if="links.length === 0" :title="t('admin.music.links.empty')" />
        <ul v-else class="rows">
            <li v-for="(link, index) in links" :key="link.id" class="rows__item">
                <span class="rows__label">{{ link.label ?? t(`admin.music.networks.${link.network}`) }} <span class="rows__sub">{{ link.url }}</span></span>
                <OrderButtons :first="index === 0" :last="index === links.length - 1" @move="(delta) => reorder(link.id, delta)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.music.delete')" @confirm="remove(link.id)" />
            </li>
        </ul>
    </PageSection>
</template>
