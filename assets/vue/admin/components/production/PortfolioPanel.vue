<script setup>
import { Pencil, Plus, Trash2, Disc3 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { moveId } from '../../musicForm.js';
import { imageUrl } from '../../settingsForm.js';
import OrderButtons from '../music/OrderButtons.vue';
import PortfolioModal from './PortfolioModal.vue';

const props = defineProps({ items: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const editing = ref(null);
const open = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/production/portfolio/order', { ids: moveId(props.items.map((item) => item.id), id, delta) }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/production/portfolio/${id}`), t('admin.production.saved'));

function edit(id) {
    editing.value = id;
    open.value = true;
}
</script>

<template>
    <PageSection :title="t('admin.production.portfolio.title')" :count="items.length">
        <template #actions>
            <BaseButton @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.production.portfolio.new') }}</BaseButton>
        </template>
        <EmptyState v-if="items.length === 0" :icon="Disc3" :title="t('admin.production.portfolio.empty')" />
        <ul v-else class="rows">
            <li v-for="(item, index) in items" :key="item.id" class="rows__item">
                <img v-if="item.cover" class="rows__thumb" :src="imageUrl(item.cover)" alt="" />
                <span v-else class="rows__thumb rows__thumb--empty" />
                <span class="rows__label">
                    {{ item.title }}
                    <span class="rows__sub">{{ item.artist }}<template v-if="item.year !== null"> · {{ item.year }}</template> · {{ item.roles.map((role) => t(`admin.production.kinds.${role}`)).join(', ') }}</span>
                </span>
                <OrderButtons :first="index === 0" :last="index === items.length - 1" @move="(delta) => reorder(item.id, delta)" />
                <IconButton :icon="Pencil" :label="t('admin.production.edit')" @click="edit(item.id)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.production.delete')" @confirm="remove(item.id)" />
            </li>
        </ul>
        <PortfolioModal v-model:open="open" :item="items.find((item) => item.id === editing) ?? null" />
    </PageSection>
</template>
