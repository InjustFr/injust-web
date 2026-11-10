<script setup>
import { Archive, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { intlLocale } from '../../../i18n/locale.js';
import { moveId } from '../../forms.js';
import { capsOf } from '../../beatsForm.js';
import OrderButtons from '../music/OrderButtons.vue';
import TierModal from './TierModal.vue';

const props = defineProps({ tiers: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const editing = ref(null);
const open = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/beats/tiers/order', { ids: moveId(props.tiers.map((tier) => tier.id), id, delta) }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/beats/tiers/${id}`), t('admin.beats.saved'));
const euros = (cents) => new Intl.NumberFormat(intlLocale(), { style: 'currency', currency: 'EUR' }).format(cents / 100);
const number = (count) => new Intl.NumberFormat(intlLocale()).format(count);

function edit(id) {
    editing.value = id;
    open.value = true;
}
</script>

<template>
    <PageSection :title="t('admin.beats.tiers.title')" :count="tiers.length">
        <template #actions>
            <BaseButton @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.beats.tiers.new') }}</BaseButton>
        </template>
        <EmptyState v-if="tiers.length === 0" :icon="Archive" :title="t('admin.beats.tiers.empty')" />
        <ul v-else class="rows">
            <li v-for="(tier, index) in tiers" :key="tier.id" class="rows__item">
                <span class="rows__label">
                    {{ tier.name.en || tier.name.fr }}
                    <span class="rows__sub">
                        <span class="tabular">{{ euros(tier.price.cents) }}</span>
                        · {{ tier.deliverables.map((deliverable) => t(`admin.beats.deliverables.${deliverable}`)).join(', ') }}
                        <template v-for="[cap, count] in capsOf(tier)" :key="cap"> · {{ t(`admin.beats.tiers.caps.${cap}`, { count: number(count) }) }}</template>
                    </span>
                </span>
                <span v-if="tier.exclusive" class="badge">{{ t('admin.beats.tiers.exclusiveBadge') }}</span>
                <OrderButtons :first="index === 0" :last="index === tiers.length - 1" @move="(delta) => reorder(tier.id, delta)" />
                <IconButton :icon="Pencil" :label="t('admin.beats.edit')" @click="edit(tier.id)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.beats.delete')" @confirm="remove(tier.id)" />
            </li>
        </ul>
        <TierModal v-model:open="open" :tier="tiers.find((tier) => tier.id === editing) ?? null" />
    </PageSection>
</template>

<style scoped>
.badge { padding: 0 var(--space-2); border: 0.0625rem solid var(--color-ink); border-radius: var(--radius); font-size: var(--font-size-xs); font-weight: 600; }
</style>
